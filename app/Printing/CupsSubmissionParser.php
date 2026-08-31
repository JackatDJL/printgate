<?php

namespace App\Printing;

use App\Printing\Exceptions\PrintSubmissionFailed;

final class CupsSubmissionParser
{
    public function parse(string $output): string
    {
        if (preg_match('/^request id is (?<jobId>[A-Za-z0-9][A-Za-z0-9._-]*-\d+) \(\d+ file\(s\)\)$/m', trim($output), $matches) !== 1) {
            throw new PrintSubmissionFailed('CUPS did not confirm a print job identifier.');
        }

        return $matches['jobId'];
    }
}
