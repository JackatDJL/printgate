<?php

namespace Tests\Feature;

use App\Connect\SiteAgent\ConnectSiteReceipt;
use App\Connect\SiteAgent\CredentialStore;
use App\Connect\SiteAgent\PollWorker;
use App\Printing\Contracts\PrintBackend;
use App\Printing\FakePrintBackend;
use App\Printing\Printer;
use App\Printing\PrinterState;
use App\Printing\PrintJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConnectSitePollWorkerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_retries_after_a_temporary_hub_error_and_prints_only_once(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/printgate-test-'.bin2hex(random_bytes(8)));
        Http::preventStrayRequests();
        ConnectSiteReceipt::query()->where('site_id', 'site-test-worker')->delete();
        $this->app->make(CredentialStore::class)->save('https://hub.test', str_repeat('d', 64), 'site-test-worker', ['office']);
        $pdf = '%PDF-1.7 test content';
        Http::fake([
            'https://hub.test/api/connect/v1/poll' => Http::response(['jobs' => [[
                'id' => 'job-one', 'printer' => 'office', 'sha256' => hash('sha256', $pdf), 'size' => strlen($pdf),
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ]]]),
            'https://hub.test/api/connect/v1/jobs/job-one/document' => Http::response($pdf, 200, ['Content-Type' => 'application/pdf']),
            'https://hub.test/api/connect/v1/jobs/job-one/status' => Http::sequence()->push([], 503)->whenEmpty(Http::response([], 200)),
        ]);
        $backend = new FakePrintBackend(
            [new Printer('office', 'Office printer', PrinterState::Ready)],
            new PrintJob('cups-42'),
        );
        $this->app->instance(PrintBackend::class, $backend);

        $worker = $this->app->make(PollWorker::class);
        try {
            $worker->poll();
            $this->fail('The hub should return a temporary error before printing.');
        } catch (RequestException $exception) {
            $this->assertSame(503, $exception->response->status());
        }
        $this->assertCount(0, $backend->submittedRequests());
        $worker->poll();
        $worker->poll();

        $this->assertCount(1, $backend->submittedRequests());
        $this->assertFileDoesNotExist($backend->submittedRequests()[0]->documentPath);
        $receipt = ConnectSiteReceipt::query()->where('site_id', 'site-test-worker')->where('remote_job_id', 'job-one')->firstOrFail();
        $this->assertSame('submitted', $receipt->state);
        $this->assertSame('cups-42', $receipt->backend_job_id);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/status')
            && $request['state'] === 'submitted' && $request['backend_job_id'] === 'cups-42');

        ConnectSiteReceipt::query()->where('site_id', 'site-test-worker')->delete();
        @unlink($this->app->make(CredentialStore::class)->path());
    }
}
