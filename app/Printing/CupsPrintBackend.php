<?php

namespace App\Printing;

use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\Exceptions\PrintSubmissionFailed;
use Illuminate\Process\Factory;
use Throwable;

final class CupsPrintBackend implements PrintBackend
{
    public function __construct(
        private readonly Factory $processes,
        private readonly CupsPrinterParser $parser,
        private readonly CupsPrinterCapabilitiesParser $capabilitiesParser,
        private readonly CupsSubmissionParser $submissionParser,
        private readonly CupsQueueStateParser $queueStateParser,
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
        try {
            $result = $this->processes
                ->timeout(30)
                ->env(['LC_ALL' => 'C', 'LANG' => 'C'])
                ->run(['lp', '-d', $request->printerName, $request->documentPath]);
        } catch (Throwable) {
            throw new PrintSubmissionFailed('CUPS print submission could not be started.');
        }

        if ($result->failed()) {
            throw new PrintSubmissionFailed('CUPS could not accept the document.');
        }

        return new PrintJob($this->submissionParser->parse($result->output()));
    }

    public function capabilities(string $printerName): PrinterCapabilities
    {
        try {
            $result = $this->processes
                ->timeout(5)
                ->env(['LC_ALL' => 'C', 'LANG' => 'C'])
                ->run(['lpoptions', '-p', $printerName, '-l']);
        } catch (Throwable $exception) {
            throw new PrintBackendUnavailable('CUPS printer capabilities could not be started.', previous: $exception);
        }

        if ($result->failed()) {
            throw new PrintBackendUnavailable('CUPS printer capabilities lookup failed.');
        }

        return $this->capabilitiesParser->parse($result->output());
    }

    public function jobState(string $backendJobId): CupsJobState
    {
        try {
            $result = $this->processes
                ->timeout(5)
                ->env(['LC_ALL' => 'C', 'LANG' => 'C'])
                ->run(['lpstat', '-W', 'not-completed', '-o', $backendJobId]);
        } catch (Throwable) {
            throw new PrintSubmissionFailed('CUPS job state lookup could not be started.');
        }

        if ($result->failed()) {
            throw new PrintSubmissionFailed('CUPS job state lookup failed.');
        }

        return $this->queueStateParser->parse($result->output(), $backendJobId);
    }

    public function cancel(string $backendJobId): void
    {
        try {
            $result = $this->processes
                ->timeout(30)
                ->env(['LC_ALL' => 'C', 'LANG' => 'C'])
                ->run(['cancel', $backendJobId]);
        } catch (Throwable) {
            throw new PrintSubmissionFailed('CUPS cancellation could not be started.');
        }

        if ($result->failed()) {
            throw new PrintSubmissionFailed('CUPS could not cancel the job.');
        }
    }
}
