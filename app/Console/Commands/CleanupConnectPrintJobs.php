<?php

namespace App\Console\Commands;

use App\Models\ConnectPrintJob;
use App\Printing\PrintgateSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupConnectPrintJobs extends Command
{
    protected $signature = 'connect:cleanup-print-jobs';

    protected $description = 'Delete expired Printgate Connect jobs and their private documents';

    public function handle(): int
    {
        $disk = Storage::disk(app(PrintgateSettings::class)->documentDisk);
        ConnectPrintJob::query()->where('expires_at', '<=', now())->lazyById()->each(function (ConnectPrintJob $job) use ($disk): void {
            if ($disk->exists($job->document_path)) {
                $disk->delete($job->document_path);
            }

            if (! $disk->exists($job->document_path)) {
                $job->delete();
            }
        });

        return self::SUCCESS;
    }
}
