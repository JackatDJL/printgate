<?php

namespace App\Printing;

use App\Printing\Exceptions\PrintValidationFailed;

final readonly class PrintSubmissionRequest
{
    public function __construct(
        public string $printerName,
        public string $documentPath,
    ) {
        if (trim($this->printerName) === '' || trim($this->documentPath) === '') {
            throw new PrintValidationFailed('A printer name and document path are required.');
        }
    }
}
