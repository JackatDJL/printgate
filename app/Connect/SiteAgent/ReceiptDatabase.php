<?php

namespace App\Connect\SiteAgent;

use Illuminate\Support\Facades\DB;

final class ReceiptDatabase
{
    public const CONNECTION = 'connect_site_agent';

    public static function configure(): void
    {
        $directory = storage_path('app/private');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        chmod($directory, 0700);

        $databasePath = $directory.'/printgate-connect.sqlite';
        if (! is_file($databasePath)) {
            touch($databasePath);
        }
        chmod($databasePath, 0600);

        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
        ]]);
        DB::purge(self::CONNECTION);
    }
}
