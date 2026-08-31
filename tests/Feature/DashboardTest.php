<?php

namespace Tests\Feature;

use App\Models\PrintJob as StoredPrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsJobState;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\FakePrintBackend;
use App\Printing\Printer;
use App\Printing\PrinterCapabilities;
use App\Printing\PrinterState;
use App\Printing\PrintJob;
use App\Printing\PrintJobState;
use App\Printing\PrintSubmissionRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Printgate is ready.');
    }

    public function test_dashboard_lists_printers_from_the_print_backend(): void
    {
        $this->app->instance(PrintBackend::class, new FakePrintBackend([
            new Printer('office', 'Office printer', PrinterState::Ready),
        ]));

        $response = $this->get('/');

        $response->assertSee('Office printer');
        $response->assertSee('office');
        $response->assertSee('Ready');
    }

    public function test_dashboard_explains_when_printer_discovery_is_unavailable(): void
    {
        $this->app->instance(PrintBackend::class, new class implements PrintBackend
        {
            public function printers(): array
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }

            public function capabilities(string $printerName): PrinterCapabilities
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }

            public function submit(PrintSubmissionRequest $request): PrintJob
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }

            public function jobState(string $backendJobId): CupsJobState
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }

            public function cancel(string $backendJobId): void
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }
        });

        $response = $this->get('/');

        $response->assertSee('Printer discovery is unavailable.');
    }

    public function test_dashboard_lists_recent_job_history_without_exposing_the_document_path(): void
    {
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Submitted,
            'original_filename' => 'meeting-notes.pdf',
            'printer_name' => 'office',
            'document_path' => 'documents/'.str_repeat('a', 40).'.pdf',
            'backend_job_id' => 'office-42',
        ]);

        $response = $this->get('/');

        $response->assertSee('Recent jobs');
        $response->assertSee($job->id);
        $response->assertSee('meeting-notes.pdf');
        $response->assertSee('office');
        $response->assertSee('Submitted');
        $response->assertSee('office-42');
        $response->assertSee($job->created_at->toDateTimeString());
        $response->assertDontSee($job->document_path);
    }
}
