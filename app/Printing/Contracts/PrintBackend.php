<?php

namespace App\Printing\Contracts;

use App\Printing\Printer;
use App\Printing\PrintJob;
use App\Printing\PrintSubmissionRequest;

interface PrintBackend
{
    /**
     * @return list<Printer>
     */
    public function printers(): array;

    public function submit(PrintSubmissionRequest $request): PrintJob;
}
