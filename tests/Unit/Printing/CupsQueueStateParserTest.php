<?php

namespace Tests\Unit\Printing;

use App\Printing\CupsJobState;
use App\Printing\CupsQueueStateParser;
use App\Printing\Exceptions\PrintSubmissionFailed;
use Tests\TestCase;

class CupsQueueStateParserTest extends TestCase
{
    public function test_returns_active_when_the_requested_job_appears_in_the_queue(): void
    {
        $output = file_get_contents(base_path('tests/Fixtures/Cups/active-job.txt'));

        $state = (new CupsQueueStateParser)->parse($output, 'Office_Printer-42');

        $this->assertSame(CupsJobState::Active, $state);
    }

    public function test_returns_completed_or_gone_when_cups_has_no_active_queue_entry(): void
    {
        $state = (new CupsQueueStateParser)->parse('', 'Office_Printer-42');

        $this->assertSame(CupsJobState::CompletedOrGone, $state);
    }

    public function test_rejects_malformed_queue_output(): void
    {
        $output = file_get_contents(base_path('tests/Fixtures/Cups/malformed-job-state.txt'));

        $this->expectException(PrintSubmissionFailed::class);

        (new CupsQueueStateParser)->parse($output, 'Office_Printer-42');
    }
}
