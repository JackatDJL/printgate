<?php

namespace Tests\Feature;

use App\Models\PrintJob as StoredPrintJob;
use App\Printing\CancelPrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsJobState;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\Exceptions\PrintValidationFailed;
use App\Printing\FakePrintBackend;
use App\Printing\PrintJob;
use App\Printing\PrintJobState;
use App\Printing\PrintSubmissionRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CancelPrintJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cancels_a_printgate_owned_active_job(): void
    {
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Submitted,
            'backend_job_id' => 'office-42',
        ]);
        $backend = new FakePrintBackend(returnedJobState: CupsJobState::Active);
        $this->app->instance(PrintBackend::class, $backend);

        $cancelledJob = $this->app->make(CancelPrintJob::class)->cancel($job->id);

        $this->assertSame(PrintJobState::Cancelled, $cancelledJob->state);
        $this->assertNull($cancelledJob->failure_message);
        $this->assertSame(['office-42'], $backend->cancelledBackendJobIds());
    }

    public function test_rejects_a_missing_printgate_job_before_reaching_cups(): void
    {
        $backend = new FakePrintBackend;
        $this->app->instance(PrintBackend::class, $backend);

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->app->make(CancelPrintJob::class)->cancel('01J00000000000000000000000');
        } finally {
            $this->assertSame([], $backend->cancelledBackendJobIds());
        }
    }

    public function test_marks_a_completed_or_absent_cups_job_without_attempting_cancellation(): void
    {
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Submitted,
            'backend_job_id' => 'office-42',
        ]);
        $backend = new FakePrintBackend(returnedJobState: CupsJobState::CompletedOrGone);
        $this->app->instance(PrintBackend::class, $backend);

        $result = $this->app->make(CancelPrintJob::class)->cancel($job->id);

        $this->assertSame(PrintJobState::CompletedOrGone, $result->state);
        $this->assertSame([], $backend->cancelledBackendJobIds());
    }

    public function test_keeps_the_job_submitted_when_cups_state_lookup_fails(): void
    {
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Submitted,
            'backend_job_id' => 'office-42',
        ]);
        $this->app->instance(PrintBackend::class, new class implements PrintBackend
        {
            public function printers(): array
            {
                return [];
            }

            public function submit(PrintSubmissionRequest $request): PrintJob
            {
                throw new PrintSubmissionFailed('not used');
            }

            public function jobState(string $backendJobId): CupsJobState
            {
                throw new PrintSubmissionFailed('raw CUPS output must not reach the browser');
            }

            public function cancel(string $backendJobId): void
            {
                throw new PrintSubmissionFailed('not used');
            }
        });

        $this->expectException(PrintSubmissionFailed::class);
        $this->expectExceptionMessage('CUPS job state lookup failed.');

        try {
            $this->app->make(CancelPrintJob::class)->cancel($job->id);
        } finally {
            $job->refresh();
            $this->assertSame(PrintJobState::Submitted, $job->state);
            $this->assertSame('CUPS job state lookup failed.', $job->failure_message);
            $this->assertStringNotContainsString('raw CUPS output', $job->failure_message);
        }
    }

    public function test_repeats_cancellation_without_reaching_cups_when_the_job_is_already_cancelled(): void
    {
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Cancelled,
            'backend_job_id' => 'office-42',
        ]);
        $backend = new FakePrintBackend;
        $this->app->instance(PrintBackend::class, $backend);

        $result = $this->app->make(CancelPrintJob::class)->cancel($job->id);

        $this->assertSame(PrintJobState::Cancelled, $result->state);
        $this->assertSame([], $backend->cancelledBackendJobIds());
    }

    public function test_rejects_an_invalid_stored_cups_identifier_without_passing_it_to_the_backend(): void
    {
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Submitted,
            'backend_job_id' => 'office-42; cancel another-7',
        ]);
        $backend = new FakePrintBackend;
        $this->app->instance(PrintBackend::class, $backend);

        $this->expectException(PrintValidationFailed::class);

        try {
            $this->app->make(CancelPrintJob::class)->cancel($job->id);
        } finally {
            $this->assertSame([], $backend->cancelledBackendJobIds());
        }
    }
}
