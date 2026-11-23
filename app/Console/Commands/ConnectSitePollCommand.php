<?php

namespace App\Console\Commands;

use App\Connect\SiteAgent\PollWorker;
use Illuminate\Console\Command;
use Throwable;

class ConnectSitePollCommand extends Command
{
    protected $signature = 'connect:site-poll {--once : Run one poll cycle and exit} {--interval=15 : Seconds between poll cycles}';

    protected $description = 'Poll the Printgate Hub for jobs and submit allowlisted jobs to CUPS';

    public function handle(PollWorker $worker): int
    {
        do {
            try {
                $count = $worker->poll();
                $this->line("Polled Printgate Hub; received {$count} job(s).");
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());
                if ($this->option('once')) {
                    return self::FAILURE;
                }
            }

            if (! $this->option('once')) {
                sleep(max(2, (int) $this->option('interval')));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
