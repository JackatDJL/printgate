<?php

namespace App\Printing;

use App\Models\PrintJob as StoredPrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\Exceptions\PrintValidationFailed;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SubmitPendingPrintJob
{
    public function __construct(
        private readonly PrintBackend $printBackend,
        private readonly PrintgateSettings $settings,
    ) {}

    public function submit(string $jobId, string $printerName): StoredPrintJob
    {
        $job = StoredPrintJob::query()->findOrFail($jobId);
        $printerName = trim($printerName);

        $this->ensurePrinterIsAvailable($printerName);

        $claimed = StoredPrintJob::query()
            ->whereKey($job->id)
            ->where('state', PrintJobState::Pending->value)
            ->update([
                'state' => PrintJobState::Submitting->value,
                'printer_name' => $printerName,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            throw new PrintValidationFailed('This print job is no longer pending.');
        }

        $job->refresh();

        try {
            $backendJob = $this->printBackend->submit(new PrintSubmissionRequest(
                $printerName,
                $this->resolveDocumentPath($job),
            ));
        } catch (PrintSubmissionFailed) {
            return $this->markFailed($job, 'CUPS could not accept the document.');
        } catch (Throwable) {
            return $this->markFailed($job, 'CUPS could not accept the document.');
        }

        $job->forceFill([
            'state' => PrintJobState::Submitted,
            'backend_job_id' => $backendJob->backendJobId,
            'failure_message' => null,
        ])->save();

        return $job;
    }

    private function ensurePrinterIsAvailable(string $printerName): void
    {
        if ($printerName === '') {
            throw new PrintValidationFailed('Choose a printer before submitting this job.');
        }

        try {
            $isAvailable = collect($this->printBackend->printers())
                ->contains(fn (Printer $printer): bool => hash_equals($printer->name, $printerName));
        } catch (PrintBackendUnavailable) {
            throw new PrintSubmissionFailed('CUPS printer discovery is unavailable.');
        }

        if (! $isAvailable) {
            throw new PrintValidationFailed('The selected printer is not currently available.');
        }
    }

    private function resolveDocumentPath(StoredPrintJob $job): string
    {
        if (preg_match('/^documents\/[A-Za-z0-9]{40}\.pdf$/', $job->document_path) !== 1) {
            throw new PrintSubmissionFailed('The stored document is unavailable.');
        }

        $disk = Storage::disk($this->settings->documentDisk);

        if (! $disk->exists($job->document_path)) {
            throw new PrintSubmissionFailed('The stored document is unavailable.');
        }

        return $disk->path($job->document_path);
    }

    private function markFailed(StoredPrintJob $job, string $message): StoredPrintJob
    {
        $job->forceFill([
            'state' => PrintJobState::Failed,
            'failure_message' => $message,
        ])->save();

        return $job;
    }
}
