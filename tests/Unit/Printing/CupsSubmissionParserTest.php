<?php

namespace Tests\Unit\Printing;

use App\Printing\CupsSubmissionParser;
use App\Printing\Exceptions\PrintSubmissionFailed;
use PHPUnit\Framework\TestCase;

class CupsSubmissionParserTest extends TestCase
{
    public function test_returns_the_cups_job_identifier_from_successful_submission_output(): void
    {
        $output = file_get_contents(__DIR__.'/../../Fixtures/Cups/submission.txt');

        $jobId = (new CupsSubmissionParser)->parse($output);

        $this->assertSame('Office_Printer-42', $jobId);
    }

    public function test_rejects_submission_output_without_a_cups_job_identifier(): void
    {
        $output = file_get_contents(__DIR__.'/../../Fixtures/Cups/malformed-submission.txt');

        $this->expectException(PrintSubmissionFailed::class);

        (new CupsSubmissionParser)->parse($output);
    }
}
