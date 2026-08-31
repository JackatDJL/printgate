<?php

namespace App\Printing;

use App\Models\PrintJob as StoredPrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\Exceptions\PrintValidationFailed;
use Throwable;

final class CancelPrintJob
{
    public function __construct(private readonly PrintBackend $printBackend) {}

    public function cancel(string $jobId): StoredPrintJob
    {
        // Milestone 3 can scope this lookup to the authenticated job owner.
        $job = StoredPrintJob::query()->findOrFail($jobId);

        if (in_array($job->state, [PrintJobState::Cancelled, PrintJobState::CompletedOrGone], true)) {
            return $job;
        }

        $backendJobId = $job->backend_job_id;

        if (! is_string($backendJobId) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*-\d+$/', $backendJobId) !== 1) {
            throw new PrintValidationFailed('This print job has no valid CUPS job identifier.');
        }

        try {
            $state = $this->printBackend->jobState($backendJobId);
        } catch (Throwable) {
            $job->forceFill(['failure_message' => 'CUPS job state lookup failed.'])->save();

            throw new PrintSubmissionFailed('CUPS job state lookup failed.');
        }

        if ($state === CupsJobState::CompletedOrGone) {
            $job->forceFill([
                'state' => PrintJobState::CompletedOrGone,
                'failure_message' => null,
            ])->save();

            return $job;
        }

        try {
            $this->printBackend->cancel($backendJobId);
        } catch (Throwable) {
            $job->forceFill(['failure_message' => 'CUPS could not cancel this job.'])->save();

            throw new PrintSubmissionFailed('CUPS could not cancel this job.');
        }

        $job->forceFill([
            'state' => PrintJobState::Cancelled,
            'failure_message' => null,
        ])->save();

        return $job;
    }
}
