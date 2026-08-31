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
     * @param  list<Printer>  $printers
     */
    public function __construct(
        private readonly array $printers = [],
        private readonly ?PrintJob $returnedJob = null,
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

    /**
     * @return list<PrintSubmissionRequest>
     */
    public function submittedRequests(): array
    {
        return $this->submittedRequests;
    }
}
