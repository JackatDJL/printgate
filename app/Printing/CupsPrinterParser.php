<?php

namespace App\Printing;

final class CupsPrinterParser
{
    /**
     * Parse the output of `LC_ALL=C lpstat -l -p`.
     *
     * @return list<Printer>
     */
    public function parse(string $output): array
    {
        if (trim($output) === '') {
            return [];
        }

        $printers = [];
        $currentPrinter = null;

        foreach (preg_split('/\R/', $output) as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^printer\s+(?<name>\S+)\s+is\s+(?<status>.+)$/', $line, $matches) === 1) {
                $name = $matches['name'];

                if (array_key_exists($name, $printers)) {
                    throw new CupsPrinterOutputMalformed("CUPS reported printer [{$name}] more than once.");
                }

                $printers[$name] = new Printer($name, $name, $this->stateFor($matches['status']));
                $currentPrinter = $name;

                continue;
            }

            if ($currentPrinter !== null && preg_match('/^\s+Description:\s*(?<description>.+?)\s*$/', $line, $matches) === 1) {
                $printer = $printers[$currentPrinter];
                $printers[$currentPrinter] = new Printer($printer->name, $matches['description'], $printer->state);

                continue;
            }

            if ($currentPrinter !== null && preg_match('/^\s+\S/', $line) === 1) {
                continue;
            }

            throw new CupsPrinterOutputMalformed('CUPS returned printer output that Printgate could not parse.');
        }

        return array_values($printers);
    }

    private function stateFor(string $status): PrinterState
    {
        $status = mb_strtolower($status);

        if (str_contains($status, 'disabled') || str_contains($status, 'stopped')) {
            return PrinterState::Stopped;
        }

        if (str_contains($status, 'idle') || str_contains($status, 'printing') || str_contains($status, 'processing')) {
            return PrinterState::Ready;
        }

        return PrinterState::Unknown;
    }
}
