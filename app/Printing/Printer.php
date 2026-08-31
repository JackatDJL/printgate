<?php

namespace App\Printing;

use App\Printing\Exceptions\PrintValidationFailed;

final readonly class Printer
{
    public function __construct(
        public string $name,
        public string $displayName,
        public PrinterState $state,
    ) {
        if (trim($this->name) === '' || trim($this->displayName) === '') {
            throw new PrintValidationFailed('A printer name and display name are required.');
        }
    }
}
