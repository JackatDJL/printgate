<?php

namespace App\Console\Commands;

use App\Connect\SiteAgent\CredentialStore;
use App\Printing\Contracts\PrintBackend;
use App\Printing\Printer;
use Illuminate\Console\Command;

class ConnectSiteAllowPrinterCommand extends Command
{
    protected $signature = 'connect:site-allow-printer {name : Exact local CUPS printer name}';

    protected $description = 'Allow a locally published printer for Printgate Connect jobs';

    public function handle(CredentialStore $store, PrintBackend $backend): int
    {
        $name = (string) $this->argument('name');
        $printer = collect($backend->printers())->first(fn (Printer $candidate): bool => $candidate->name === $name);
        if (! $printer) {
            $this->error('That printer is not currently published by the local print backend.');

            return self::FAILURE;
        }

        $configuration = $store->read();
        $allowed = array_values(array_unique([...$configuration['allowed_printers'], $name]));
        $store->updateAllowedPrinters($allowed);
        $this->info("{$name} is now allowlisted for Printgate Connect.");

        return self::SUCCESS;
    }
}
