<?php

namespace App\Printing\Contracts;

use App\Printing\CupsJobState;
use App\Printing\Printer;
use App\Printing\PrinterCapabilities;
use App\Printing\PrintJob;
use App\Printing\PrintSubmissionRequest;

interface PrintBackend
{
    /**
     * @return list<Printer>
     */
    public function printers(): array;

    public function capabilities(string $printerName): PrinterCapabilities;

    public function submit(PrintSubmissionRequest $request): PrintJob;

    public function jobState(string $backendJobId): CupsJobState;

    public function cancel(string $backendJobId): void;
}
