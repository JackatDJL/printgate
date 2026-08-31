<?php

namespace App\Printing;

use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use Illuminate\Process\Factory;
use Throwable;

final class CupsPrintBackend implements PrintBackend
{
    public function __construct(
        private readonly Factory $processes,
        private readonly CupsPrinterParser $parser,
    ) {}

    /**
     * @return list<Printer>
     */
    public function printers(): array
    {
        try {
            $result = $this->processes
                ->timeout(5)
                ->env(['LC_ALL' => 'C', 'LANG' => 'C'])
                ->run(['lpstat', '-l', '-p']);
        } catch (Throwable $exception) {
            throw new PrintBackendUnavailable('CUPS printer discovery could not be started.', previous: $exception);
        }

        if ($result->failed()) {
            throw new PrintBackendUnavailable('CUPS printer discovery failed.');
        }

        try {
            return $this->parser->parse($result->output());
        } catch (CupsPrinterOutputMalformed $exception) {
            throw new PrintBackendUnavailable('CUPS printer discovery returned invalid output.', previous: $exception);
        }
    }

    public function submit(PrintSubmissionRequest $request): PrintJob
    {
        throw new PrintBackendUnavailable('CUPS print submission is not available yet.');
    }
}
