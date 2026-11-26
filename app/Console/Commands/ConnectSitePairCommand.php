<?php

namespace App\Console\Commands;

use App\Connect\SiteAgent\SitePairer;
use Illuminate\Console\Command;
use Throwable;

class ConnectSitePairCommand extends Command
{
    protected $signature = 'connect:site-pair {url : HTTPS base URL for the Printgate Hub} {name : Display name for this site}';

    protected $description = 'Pair this Printgate site with its Hub';

    public function handle(SitePairer $pairer): int
    {
        $url = (string) $this->argument('url');
        $invitation = $this->secret('Hub invitation token');
        if (! is_string($invitation) || trim($invitation) === '') {
            $this->error('An invitation token is required.');

            return self::FAILURE;
        }

        $code = (string) random_int(100000, 999999);
        $this->line("Confirm this comparison code in the Hub: {$code}");
        $this->line('Waiting for the Hub owner to confirm pairing...');

        try {
            $siteId = $pairer->pair($url, (string) $this->argument('name'), $invitation, $code);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Printgate site paired ({$siteId}). The credential was saved with private file permissions.");

        return self::SUCCESS;
    }
}
