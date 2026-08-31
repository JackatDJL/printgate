<?php

namespace App\Printing;

use InvalidArgumentException;

final readonly class PrintgateSettings
{
    public function __construct(
        public string $bindHost,
        public int $bindPort,
        public string $documentDisk,
        public int $maximumUploadMegabytes,
        public int $retentionMinutes,
        public string $authenticationMode,
    ) {}

    /**
     * @param  array{
     *     bind: array{host: mixed, port: mixed},
     *     documents: array{disk: mixed, max_upload_megabytes: mixed, retention_minutes: mixed},
     *     authentication: array{mode: mixed}
     * }  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            self::nonEmptyString($config['bind']['host'], 'bind.host'),
            self::positiveInteger($config['bind']['port'], 'bind.port', 65_535),
            self::nonEmptyString($config['documents']['disk'], 'documents.disk'),
            self::positiveInteger($config['documents']['max_upload_megabytes'], 'documents.max_upload_megabytes'),
            self::positiveInteger($config['documents']['retention_minutes'], 'documents.retention_minutes'),
            self::nonEmptyString($config['authentication']['mode'], 'authentication.mode'),
        );
    }

    private static function nonEmptyString(mixed $value, string $name): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Printgate configuration [{$name}] must be a non-empty string.");
        }

        return $value;
    }

    private static function positiveInteger(mixed $value, string $name, ?int $maximum = null): int
    {
        $options = ['min_range' => 1];

        if ($maximum !== null) {
            $options['max_range'] = $maximum;
        }

        $validatedValue = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => $options,
        ]);

        if ($validatedValue === false) {
            $range = $maximum === null ? 'a positive integer' : "an integer between 1 and {$maximum}";

            throw new InvalidArgumentException("Printgate configuration [{$name}] must be {$range}.");
        }

        return $validatedValue;
    }
}
