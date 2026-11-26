<?php

namespace Tests\Feature;

use App\Connect\ConnectPrintJobState;
use App\Models\ConnectPrintJob;
use App\Models\ConnectSite;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ConnectPrintTransportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['printgate.role' => 'hub', 'printgate.connect.max_active_jobs_per_owner' => 10]);
        Storage::fake('printgate-documents');
        URL::forceScheme('https');
    }

    public function test_poll_reports_printers_and_redelivers_the_same_queued_and_submitting_job_id(): void
    {
        [$site, $credential] = $this->site();
        $job = $this->job($site);

        $first = $this->asSite($credential)->postJson('/api/connect/v1/poll', [
            'protocol' => 1,
            'printers' => [['name' => 'office', 'display_name' => 'Office', 'state' => 'idle']],
        ])->assertOk()->assertJsonPath('jobs.0.id', $job->id)->assertJsonPath('jobs.0.printer', 'office');
        $first->assertJsonPath('jobs.0.sha256', $job->sha256);

        $this->asSite($credential)->postJson("/api/connect/v1/jobs/{$job->id}/status", ['state' => 'submitting'])->assertOk();
        $this->asSite($credential)->postJson('/api/connect/v1/poll', [
            'protocol' => 1,
            'printers' => [['name' => 'office', 'display_name' => 'Office', 'state' => 'idle']],
        ])->assertJsonPath('jobs.0.id', $job->id);
        $this->assertSame([['name' => 'office', 'display_name' => 'Office', 'state' => 'idle']], $site->fresh()->reported_printers);
    }

    public function test_poll_rotates_submitted_jobs_through_the_bounded_response(): void
    {
        [$site, $credential] = $this->site();
        $this->freezeTime();
        $jobs = collect(range(1, 21))->map(function () use ($site): ConnectPrintJob {
            $job = $this->job($site);
            $job->update(['state' => ConnectPrintJobState::Submitted]);

            return $job;
        })->sortBy('id')->values();
        $oldestJob = $jobs->first();
        $lastJob = $jobs->last();
        $payload = ['protocol' => 1, 'printers' => []];

        $firstPoll = $this->asSite($credential)->postJson('/api/connect/v1/poll', $payload)
            ->assertOk()->assertJsonCount(20, 'jobs');
        $this->assertNotContains($lastJob->id, array_column($firstPoll->json('jobs'), 'id'));

        $this->travel(15)->seconds();
        $this->asSite($credential)->postJson("/api/connect/v1/jobs/{$oldestJob->id}/status", [
            'state' => 'submitted',
        ])->assertOk();

        $secondPoll = $this->asSite($credential)->postJson('/api/connect/v1/poll', $payload)->assertOk();
        $returnedIds = array_column($secondPoll->json('jobs'), 'id');
        $this->assertContains($lastJob->id, $returnedIds);
        $this->assertNotContains($oldestJob->id, $returnedIds);
    }

    public function test_job_document_and_status_are_limited_to_the_assigned_active_site(): void
    {
        [$site, $credential] = $this->site();
        [$otherSite, $otherCredential] = $this->site();
        $job = $this->job($site);
        Storage::disk('printgate-documents')->put($job->document_path, '%PDF-1.4 content');

        $this->asSite($otherCredential)->get("/api/connect/v1/jobs/{$job->id}/document")->assertNotFound();
        $this->asSite($otherCredential)->postJson("/api/connect/v1/jobs/{$job->id}/status", ['state' => 'submitted'])->assertNotFound();
        $response = $this->asSite($credential)->get("/api/connect/v1/jobs/{$job->id}/document");
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertStreamedContent('%PDF-1.4 content');

        $this->actingAs($site->owner)->delete(route('connect.sites.revoke', $site));
        $this->asSite($credential)->postJson('/api/connect/v1/poll', ['protocol' => 1, 'printers' => []])->assertUnauthorized();
    }

    public function test_status_is_idempotent_and_rejects_backward_or_terminal_transitions(): void
    {
        [$site, $credential] = $this->site();
        $job = $this->job($site);
        $url = "/api/connect/v1/jobs/{$job->id}/status";

        $this->asSite($credential)->postJson($url, ['state' => 'submitting'])->assertOk();
        $this->asSite($credential)->postJson($url, ['state' => 'submitting'])->assertOk();
        $this->asSite($credential)->postJson($url, ['state' => 'queued'])->assertStatus(409);
        $this->asSite($credential)->postJson($url, ['state' => 'submitted', 'backend_job_id' => 'cups-123'])->assertOk();
        $this->asSite($credential)->postJson($url, ['state' => 'completed_or_gone'])->assertOk();
        $this->asSite($credential)->postJson($url, ['state' => 'submitted'])->assertStatus(409);
        $this->assertSame(ConnectPrintJobState::CompletedOrGone, $job->fresh()->state);
    }

    public function test_owner_quota_expiry_and_scheduled_cleanup_are_enforced(): void
    {
        config(['printgate.connect.max_active_jobs_per_owner' => 1]);
        [$site, $credential] = $this->site();
        $this->asSite($credential)->postJson('/api/connect/v1/poll', [
            'protocol' => 1,
            'printers' => [['name' => 'office', 'display_name' => 'Office', 'state' => 'idle']],
        ]);
        $this->actingAs($site->owner)->post(route('connect.sites.jobs.store', $site), [
            'printer_name' => 'office',
            'document' => UploadedFile::fake()->createWithContent('one.pdf', "%PDF-1.4\nfirst"),
        ])->assertRedirect('/connect');
        $job = ConnectPrintJob::query()->sole();
        Storage::disk('printgate-documents')->assertExists($job->document_path);

        $this->actingAs($site->owner)->post(route('connect.sites.jobs.store', $site), [
            'printer_name' => 'office',
            'document' => UploadedFile::fake()->createWithContent('two.pdf', "%PDF-1.4\nsecond"),
        ])->assertTooManyRequests();

        $job->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->asSite($credential)->get("/api/connect/v1/jobs/{$job->id}/document")->assertGone();
        Artisan::call('connect:cleanup-print-jobs');
        $this->assertDatabaseMissing('connect_print_jobs', ['id' => $job->id]);
        Storage::disk('printgate-documents')->assertMissing($job->document_path);
    }

    public function test_web_owner_can_queue_only_a_reported_printer_and_a_pdf(): void
    {
        [$site, $credential] = $this->site();
        $this->asSite($credential)->postJson('/api/connect/v1/poll', [
            'protocol' => 1,
            'printers' => [['name' => 'office', 'display_name' => 'Office', 'state' => 'idle']],
        ]);

        $this->actingAs($site->owner)->post(route('connect.sites.jobs.store', $site), [
            'printer_name' => 'unreported',
            'document' => UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\ncontent"),
        ])->assertUnprocessable();
        $this->actingAs($site->owner)->post(route('connect.sites.jobs.store', $site), [
            'printer_name' => 'office',
            'document' => UploadedFile::fake()->createWithContent('not-a-pdf.txt', 'plain text'),
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('connect_print_jobs', 0);
    }

    /** @return array{ConnectSite, string} */
    private function site(): array
    {
        $owner = User::factory()->create();
        $credential = str_pad((string) $owner->id, 64, '0', STR_PAD_LEFT);
        $site = $owner->connectSites()->create([
            'name' => 'Office',
            'credential_digest' => hash('sha256', $credential),
            'credential_issued_at' => now(),
        ]);

        return [$site, $credential];
    }

    private function job(ConnectSite $site): ConnectPrintJob
    {
        $path = 'documents/'.str_repeat('a', 40).'.pdf';

        return ConnectPrintJob::query()->create([
            'owner_id' => $site->owner_id,
            'site_id' => $site->id,
            'printer_name' => 'office',
            'original_filename' => 'document.pdf',
            'document_path' => $path,
            'sha256' => hash('sha256', '%PDF-1.4 content'),
            'size' => strlen('%PDF-1.4 content'),
            'state' => ConnectPrintJobState::Queued,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function asSite(string $credential): static
    {
        return $this->withHeader('Authorization', "Bearer {$credential}");
    }
}
