<?php

namespace App\Connect\SiteAgent;

use RuntimeException;

final class CredentialStore
{
    public function path(): string
    {
        return storage_path('app/private/printgate-connect.json');
    }

    /** @return array{base_url: string, credential: string, site_id: string, allowed_printers: list<string>} */
    public function read(): array
    {
        $path = $this->path();
        $contents = @file_get_contents($path);
        $configuration = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($configuration)
            || ! is_string($configuration['base_url'] ?? null)
            || ! is_string($configuration['credential'] ?? null)
            || ! is_string($configuration['site_id'] ?? null)
            || ! is_array($configuration['allowed_printers'] ?? null)) {
            throw new RuntimeException('Printgate Connect is not paired. Run connect:site-pair first.');
        }

        return $configuration;
    }

    /** @param list<string> $allowedPrinters */
    public function save(string $baseUrl, string $credential, string $siteId, array $allowedPrinters = []): void
    {
        $directory = dirname($this->path());
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the private Printgate Connect directory.');
        }
        chmod($directory, 0700);

        $path = $this->path();
        $temporaryPath = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $contents = json_encode([
            'base_url' => $baseUrl,
            'credential' => $credential,
            'site_id' => $siteId,
            'allowed_printers' => array_values(array_unique($allowedPrinters)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException('Could not write the Printgate Connect credential.');
            }
            chmod($temporaryPath, 0600);
            if (! rename($temporaryPath, $path)) {
                throw new RuntimeException('Could not install the Printgate Connect credential.');
            }
            chmod($path, 0600);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @param list<string> $allowedPrinters */
    public function updateAllowedPrinters(array $allowedPrinters): void
    {
        $configuration = $this->read();
        $this->save($configuration['base_url'], $configuration['credential'], $configuration['site_id'], $allowedPrinters);
    }
}
