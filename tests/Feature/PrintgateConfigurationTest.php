<?php

namespace Tests\Feature;

use App\Printing\PrintgateSettings;
use InvalidArgumentException;
use Tests\TestCase;

class PrintgateConfigurationTest extends TestCase
{
    public function test_default_settings_use_loopback_and_document_limits(): void
    {
        $settings = $this->app->make(PrintgateSettings::class);

        $this->assertSame('127.0.0.1', $settings->bindHost);
        $this->assertSame(5901, $settings->bindPort);
        $this->assertSame(50, $settings->maximumUploadMegabytes);
        $this->assertSame(60, $settings->retentionMinutes);
        $this->assertSame('local', $settings->authenticationMode);
    }

    public function test_temporary_document_disk_is_private(): void
    {
        $settings = $this->app->make(PrintgateSettings::class);
        $disk = config("filesystems.disks.{$settings->documentDisk}");

        $this->assertSame('local', $disk['driver']);
        $this->assertSame(storage_path('app/private/printgate-documents'), $disk['root']);
        $this->assertSame(false, $disk['serve']);
        $this->assertSame('private', $disk['visibility']);
        $this->assertFalse(str_starts_with($disk['root'], public_path()));
    }

    public function test_invalid_numeric_limits_are_rejected(): void
    {
        $config = config('printgate');
        $config['documents']['retention_minutes'] = 0;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('documents.retention_minutes');

        PrintgateSettings::fromConfig($config);
    }
}
