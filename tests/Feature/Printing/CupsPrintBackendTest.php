<?php

namespace Tests\Feature\Printing;

use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
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
}
