<?php

namespace App\Printing;

use App\Printing\Contracts\PrintBackend;

final class FakePrintBackend implements PrintBackend
{
    /**
     * @var list<PrintSubmissionRequest>
     */
    private array $submittedRequests = [];

    /**
     * @var list<string>
     */
    private array $cancelledBackendJobIds = [];

    /**
     * @param  list<Printer>  $printers
     */
    public function __construct(
        private readonly array $printers = [],
        private readonly ?PrintJob $returnedJob = null,
        private readonly CupsJobState $returnedJobState = CupsJobState::Active,
        private readonly array $capabilities = [],
    ) {}

    public function printers(): array
    {
        return $this->printers;
    }

    public function submit(PrintSubmissionRequest $request): PrintJob
    {
        $this->submittedRequests[] = $request;

        return $this->returnedJob ?? new PrintJob('fake-job-'.count($this->submittedRequests));
    }

    public function capabilities(string $printerName): PrinterCapabilities
    {
        return $this->capabilities[$printerName] ?? new PrinterCapabilities;
    }

    /**
     * @return list<PrintSubmissionRequest>
     */
    public function submittedRequests(): array
    {
        return $this->submittedRequests;
    }

    public function jobState(string $backendJobId): CupsJobState
    {
        return $this->returnedJobState;
    }

    public function cancel(string $backendJobId): void
    {
        $this->cancelledBackendJobIds[] = $backendJobId;
    }

    /**
     * @return list<string>
     */
    public function cancelledBackendJobIds(): array
    {
        return $this->cancelledBackendJobIds;
    }
}
