<?php

namespace App\Printing;

use App\Printing\Exceptions\PrintSubmissionFailed;

final class CupsQueueStateParser
{
    public function parse(string $output, string $expectedJobId): CupsJobState
    {
        $output = trim($output);

        if ($output === '') {
            return CupsJobState::CompletedOrGone;
        }

        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^(?<jobId>[A-Za-z0-9][A-Za-z0-9._-]*-\d+)\s+/', $line, $matches) !== 1) {
                throw new PrintSubmissionFailed('CUPS returned invalid job state output.');
            }

            if (hash_equals($expectedJobId, $matches['jobId'])) {
                return CupsJobState::Active;
            }
        }

        throw new PrintSubmissionFailed('CUPS returned an unexpected job state.');
    }
}
