<?php

namespace App\Printing;

use App\Printing\Exceptions\PrintValidationFailed;

final readonly class PrintJob
{
    public function __construct(public string $backendJobId)
    {
        if (trim($this->backendJobId) === '') {
            throw new PrintValidationFailed('A backend job ID is required.');
        }
    }
}
