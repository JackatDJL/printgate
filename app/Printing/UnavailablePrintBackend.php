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

    public function submit(PrintSubmissionRequest $request): PrintJob
    {
        throw new PrintBackendUnavailable('The Printgate print backend is not configured.');
    }
}
