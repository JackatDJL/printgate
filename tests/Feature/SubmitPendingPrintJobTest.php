<?php

namespace Tests\Feature;

use App\Models\PrintJob as StoredPrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsJobState;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\Exceptions\PrintValidationFailed;
use App\Printing\FakePrintBackend;
use App\Printing\Printer;
use App\Printing\PrinterState;
use App\Printing\PrintJob;
use App\Printing\PrintJobState;
use App\Printing\PrintSubmissionRequest;
use App\Printing\SubmitPendingPrintJob;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmitPendingPrintJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_submits_an_owned_pending_document_to_an_available_printer(): void
    {
        Storage::fake('printgate-documents');
        $job = $this->createPendingJob();
        $backend = new FakePrintBackend(
            [new Printer('office', 'Office printer', PrinterState::Ready)],
            new PrintJob('office-42'),
        );
        $this->app->instance(PrintBackend::class, $backend);

        $submittedJob = $this->app->make(SubmitPendingPrintJob::class)->submit($job->id, 'office');

        $this->assertSame(PrintJobState::Submitted, $submittedJob->state);
        $this->assertSame('office', $submittedJob->printer_name);
        $this->assertSame('office-42', $submittedJob->backend_job_id);
        $this->assertNull($submittedJob->failure_message);
        $requests = $backend->submittedRequests();

        $this->assertCount(1, $requests);
        $this->assertSame('office', $requests[0]->printerName);
        $this->assertSame(Storage::disk('printgate-documents')->path($job->document_path), $requests[0]->documentPath);
    }

    public function test_rejects_a_stale_printer_name_without_submitting_the_pending_job(): void
    {
        Storage::fake('printgate-documents');
        $job = $this->createPendingJob();
        $backend = new FakePrintBackend([new Printer('office', 'Office printer', PrinterState::Ready)]);
        $this->app->instance(PrintBackend::class, $backend);

        $this->expectException(PrintValidationFailed::class);
        $this->expectExceptionMessage('not currently available');

        try {
            $this->app->make(SubmitPendingPrintJob::class)->submit($job->id, 'stale-printer');
        } finally {
            $this->assertSame(PrintJobState::Pending, $job->refresh()->state);
            $this->assertSame([], $backend->submittedRequests());
        }
    }

    public function test_marks_the_job_failed_without_persisting_backend_error_output(): void
    {
        Storage::fake('printgate-documents');
        $job = $this->createPendingJob();
        $this->app->instance(PrintBackend::class, new class implements PrintBackend
        {
            public function printers(): array
            {
                return [new Printer('office', 'Office printer', PrinterState::Ready)];
            }

            public function submit(PrintSubmissionRequest $request): PrintJob
            {
                throw new PrintSubmissionFailed('lp: document contents leaked');
            }

            public function jobState(string $backendJobId): CupsJobState
            {
                throw new PrintSubmissionFailed('not used');
            }

            public function cancel(string $backendJobId): void
            {
                throw new PrintSubmissionFailed('not used');
            }
        });

        $failedJob = $this->app->make(SubmitPendingPrintJob::class)->submit($job->id, 'office');

        $this->assertSame(PrintJobState::Failed, $failedJob->state);
        $this->assertSame('CUPS could not accept the document.', $failedJob->failure_message);
        $this->assertStringNotContainsString('contents leaked', $failedJob->failure_message);
        $this->assertNull($failedJob->backend_job_id);
    }

    public function test_rejects_a_second_submission_after_the_pending_job_has_been_claimed(): void
    {
        Storage::fake('printgate-documents');
        $job = $this->createPendingJob();
        $backend = new FakePrintBackend(
            [new Printer('office', 'Office printer', PrinterState::Ready)],
            new PrintJob('office-42'),
        );
        $this->app->instance(PrintBackend::class, $backend);
        $service = $this->app->make(SubmitPendingPrintJob::class);

        $service->submit($job->id, 'office');

        $this->expectException(PrintValidationFailed::class);
        $this->expectExceptionMessage('no longer pending');

        try {
            $service->submit($job->id, 'office');
        } finally {
            $this->assertCount(1, $backend->submittedRequests());
        }
    }

    public function test_rejects_a_job_with_a_non_owned_storage_reference_before_reaching_cups(): void
    {
        Storage::fake('printgate-documents');
        $job = StoredPrintJob::factory()->create([
            'state' => PrintJobState::Pending,
            'document_path' => '/tmp/untrusted.pdf',
        ]);
        $backend = new FakePrintBackend([new Printer('office', 'Office printer', PrinterState::Ready)]);
        $this->app->instance(PrintBackend::class, $backend);

        $failedJob = $this->app->make(SubmitPendingPrintJob::class)->submit($job->id, 'office');

        $this->assertSame(PrintJobState::Failed, $failedJob->state);
        $this->assertSame('CUPS could not accept the document.', $failedJob->failure_message);
        $this->assertSame([], $backend->submittedRequests());
    }

    private function createPendingJob(): StoredPrintJob
    {
        $documentPath = 'documents/'.str_repeat('a', 40).'.pdf';
        Storage::disk('printgate-documents')->put($documentPath, '%PDF-1.4');

        return StoredPrintJob::factory()->create([
            'state' => PrintJobState::Pending,
            'document_path' => $documentPath,
        ]);
    }
}
