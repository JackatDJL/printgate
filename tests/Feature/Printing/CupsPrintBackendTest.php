<?php

namespace Tests\Feature\Printing;

use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsJobState;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\PrintSubmissionRequest;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class CupsPrintBackendTest extends TestCase
{
    public function test_discovers_printers_with_an_argument_array_and_stable_locale(): void
    {
        $output = file_get_contents(base_path('tests/Fixtures/Cups/printers.txt'));
        Process::fake(['*' => Process::result(output: $output)]);

        $printers = $this->app->make(PrintBackend::class)->printers();

        $this->assertCount(3, $printers);
        $this->assertSame('Main office printer', $printers[0]->displayName);
        Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
            return $process->command === ['lpstat', '-l', '-p']
                && $process->environment === ['LC_ALL' => 'C', 'LANG' => 'C']
                && $process->timeout === 5;
        });
    }

    public function test_reports_a_backend_error_when_cups_exits_unsuccessfully(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'lpstat: command not found', exitCode: 127)]);

        $this->expectException(PrintBackendUnavailable::class);

        $this->app->make(PrintBackend::class)->printers();
    }

    public function test_reports_a_backend_error_when_the_cups_utility_cannot_start(): void
    {
        Process::fake(['*' => new RuntimeException('Unable to find the "lpstat" command.')]);

        $this->expectException(PrintBackendUnavailable::class);

        $this->app->make(PrintBackend::class)->printers();
    }

    public function test_reports_a_backend_error_when_cups_output_is_malformed(): void
    {
        $output = file_get_contents(base_path('tests/Fixtures/Cups/malformed-printers.txt'));
        Process::fake(['*' => Process::result(output: $output)]);

        $this->expectException(PrintBackendUnavailable::class);

        $this->app->make(PrintBackend::class)->printers();
    }

    public function test_submits_a_document_with_an_argument_array_and_returns_the_cups_job_identifier(): void
    {
        $output = file_get_contents(base_path('tests/Fixtures/Cups/submission.txt'));
        Process::fake(['*' => Process::result(output: $output)]);

        $job = $this->app->make(PrintBackend::class)->submit(new PrintSubmissionRequest(
            'Office_Printer',
            '/private/printgate-documents/documents/owned.pdf',
        ));

        $this->assertSame('Office_Printer-42', $job->backendJobId);
        Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
            return $process->command === [
                'lp',
                '-d',
                'Office_Printer',
                '/private/printgate-documents/documents/owned.pdf',
            ]
                && $process->environment === ['LC_ALL' => 'C', 'LANG' => 'C']
                && $process->timeout === 30;
        });
    }

    public function test_reports_a_submission_error_when_cups_rejects_the_document(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'lp: rejected', exitCode: 1)]);

        $this->expectException(PrintSubmissionFailed::class);

        $this->app->make(PrintBackend::class)->submit(new PrintSubmissionRequest('Office_Printer', '/private/document.pdf'));
    }

    public function test_looks_up_an_active_job_with_an_argument_array_and_stable_locale(): void
    {
        $output = file_get_contents(base_path('tests/Fixtures/Cups/active-job.txt'));
        Process::fake(['*' => Process::result(output: $output)]);

        $state = $this->app->make(PrintBackend::class)->jobState('Office_Printer-42');

        $this->assertSame(CupsJobState::Active, $state);
        Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
            return $process->command === ['lpstat', '-W', 'not-completed', '-o', 'Office_Printer-42']
                && $process->environment === ['LC_ALL' => 'C', 'LANG' => 'C']
                && $process->timeout === 5;
        });
    }

    public function test_reports_completed_or_gone_when_cups_has_no_active_queue_entry(): void
    {
        Process::fake(['*' => Process::result()]);

        $state = $this->app->make(PrintBackend::class)->jobState('Office_Printer-42');

        $this->assertSame(CupsJobState::CompletedOrGone, $state);
    }

    public function test_cancels_a_job_with_an_argument_array(): void
    {
        Process::fake(['*' => Process::result()]);

        $this->app->make(PrintBackend::class)->cancel('Office_Printer-42');

        Process::assertRan(function (PendingProcess $process, ProcessResult $result): bool {
            return $process->command === ['cancel', 'Office_Printer-42']
                && $process->environment === ['LC_ALL' => 'C', 'LANG' => 'C']
                && $process->timeout === 30;
        });
    }
}
