<?php

namespace App\Printing;

use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;

final class UnavailablePrintBackend implements PrintBackend
{
    public function printers(): array
    {
        throw new PrintBackendUnavailable('The Printgate print backend is not configured.');
    }

    public function capabilities(string $printerName): PrinterCapabilities
    {
        throw new PrintBackendUnavailable('The Printgate print backend is not configured.');
    }

    public function submit(PrintSubmissionRequest $request): PrintJob
    {
        throw new PrintBackendUnavailable('The Printgate print backend is not configured.');
    }

    public function jobState(string $backendJobId): CupsJobState
    {
        throw new PrintBackendUnavailable('The Printgate print backend is not configured.');
    }

    public function cancel(string $backendJobId): void
    {
        throw new PrintBackendUnavailable('The Printgate print backend is not configured.');
    }
}
