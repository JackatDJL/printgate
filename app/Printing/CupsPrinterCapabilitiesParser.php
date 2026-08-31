<?php

namespace App\Printing;

final class CupsPrinterCapabilitiesParser
{
    /**
     * Parse the output of `LC_ALL=C lpoptions -p PRINTER -l`.
     *
     * CUPS marks the default value in a supported-value line with `*`. The
     * optional defaults output is useful for fixture variants and drivers that
     * report defaults separately.
     */
    public function parse(string $supportedOutput, string $defaultsOutput = ''): PrinterCapabilities
    {
        $values = [
            'media' => [],
            'sides' => [],
            'color' => [],
            'orientation' => [],
        ];
        $defaults = [];

        foreach (preg_split('/\R/', $supportedOutput) as $line) {
            if (preg_match('/^\s*(?<option>[^:]+):\s*(?<values>.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $category = $this->categoryFor($matches['option']);

            if ($category === null) {
                continue;
            }

            foreach ($this->tokens($matches['values']) as $token) {
                $isDefault = str_starts_with($token, '*');
                $token = ltrim($token, '*');

                if ($token === '') {
                    continue;
                }

                $values[$category][] = $token;

                if ($isDefault) {
                    $defaults[$category] = $token;
                }
            }
        }

        foreach (preg_split('/\s+/', trim($defaultsOutput)) as $token) {
            if ($token === '' || ! str_contains($token, '=')) {
                continue;
            }

            [$option, $value] = explode('=', $token, 2);
            $category = $this->categoryFor($option);

            if ($category !== null && $value !== '') {
                $defaults[$category] = $value;
            }
        }

        return new PrinterCapabilities(
            mediaSizes: $this->knownMedia($values['media']),
            defaultMediaSize: $this->knownMediaValue($defaults['media'] ?? null),
            sides: $this->knownSides($values['sides']),
            defaultSides: $this->knownSidesValue($defaults['sides'] ?? null),
            colorModes: $this->knownColors($values['color']),
            defaultColorMode: $this->knownColorValue($defaults['color'] ?? null),
            orientations: $this->knownOrientations($values['orientation']),
            defaultOrientation: $this->knownOrientationValue($defaults['orientation'] ?? null),
            unknownValues: [
                'media' => $this->unknown($values['media'], fn (string $value): ?PrinterMediaSize => $this->mediaValue($value)),
                'sides' => $this->unknown($values['sides'], fn (string $value): ?PrinterSides => $this->sidesValue($value)),
                'color' => $this->unknown($values['color'], fn (string $value): ?PrinterColorMode => $this->colorValue($value)),
                'orientation' => $this->unknown($values['orientation'], fn (string $value): ?PrinterOrientation => $this->orientationValue($value)),
            ],
        );
    }

    /** @return list<string> */
    private function tokens(string $values): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($values)) ?: []));
    }

    private function categoryFor(string $option): ?string
    {
        $option = mb_strtolower(trim(explode('/', $option, 2)[0]));

        return match (true) {
            str_contains($option, 'pagesize'), str_contains($option, 'media') => 'media',
            str_contains($option, 'sides'), str_contains($option, 'duplex') => 'sides',
            str_contains($option, 'colormodel'), str_contains($option, 'color'), str_contains($option, 'print-color-mode') => 'color',
            str_contains($option, 'orientation') => 'orientation',
            default => null,
        };
    }

    /** @param list<string> $values @return list<PrinterMediaSize> */
    private function knownMedia(array $values): array
    {
        return $this->known($values, fn (string $value): ?PrinterMediaSize => $this->mediaValue($value));
    }

    private function knownMediaValue(?string $value): ?PrinterMediaSize
    {
        return $value === null ? null : $this->mediaValue($value);
    }

    private function mediaValue(string $value): ?PrinterMediaSize
    {
        return match (mb_strtolower($value)) {
            'a4', 'iso_a4_210x297mm' => PrinterMediaSize::A4,
            'a5', 'iso_a5_148x210mm' => PrinterMediaSize::A5,
            'letter', 'na_letter_8.5x11in' => PrinterMediaSize::Letter,
            'legal', 'na_legal_8.5x14in' => PrinterMediaSize::Legal,
            default => null,
        };
    }

    /** @param list<string> $values @return list<PrinterSides> */
    private function knownSides(array $values): array
    {
        return $this->known($values, fn (string $value): ?PrinterSides => $this->sidesValue($value));
    }

    private function knownSidesValue(?string $value): ?PrinterSides
    {
        return $value === null ? null : $this->sidesValue($value);
    }

    private function sidesValue(string $value): ?PrinterSides
    {
        return match (mb_strtolower($value)) {
            'one-sided', 'onesided', 'simplex', 'none' => PrinterSides::OneSided,
            'two-sided-long-edge', 'duplexnotumble', 'duplexno-tumble' => PrinterSides::TwoSidedLongEdge,
            'two-sided-short-edge', 'duplextumble', 'duplex-tumble' => PrinterSides::TwoSidedShortEdge,
            default => null,
        };
    }

    /** @param list<string> $values @return list<PrinterColorMode> */
    private function knownColors(array $values): array
    {
        return $this->known($values, fn (string $value): ?PrinterColorMode => $this->colorValue($value));
    }

    private function knownColorValue(?string $value): ?PrinterColorMode
    {
        return $value === null ? null : $this->colorValue($value);
    }

    private function colorValue(string $value): ?PrinterColorMode
    {
        return match (mb_strtolower($value)) {
            'color', 'rgb', 'cmyk' => PrinterColorMode::Color,
            'gray', 'grey', 'grayscale', 'monochrome', 'black' => PrinterColorMode::Grayscale,
            default => null,
        };
    }

    /** @param list<string> $values @return list<PrinterOrientation> */
    private function knownOrientations(array $values): array
    {
        return $this->known($values, fn (string $value): ?PrinterOrientation => $this->orientationValue($value));
    }

    private function knownOrientationValue(?string $value): ?PrinterOrientation
    {
        return $value === null ? null : $this->orientationValue($value);
    }

    private function orientationValue(string $value): ?PrinterOrientation
    {
        return PrinterOrientation::tryFrom(mb_strtolower($value));
    }

    /**
     * @template T of BackedEnum
     *
     * @param  list<string>  $values
     * @param  callable(string): ?T  $mapper
     * @return list<T>
     */
    private function known(array $values, callable $mapper): array
    {
        $known = [];

        foreach ($values as $value) {
            $mapped = $mapper($value);

            if ($mapped !== null && ! in_array($mapped, $known, true)) {
                $known[] = $mapped;
            }
        }

        return $known;
    }

    /**
     * @template T of BackedEnum
     *
     * @param  list<string>  $values
     * @param  callable(string): ?T  $mapper
     * @return list<string>
     */
    private function unknown(array $values, callable $mapper): array
    {
        $unknown = [];

        foreach ($values as $value) {
            if ($mapper($value) === null && ! in_array($value, $unknown, true)) {
                $unknown[] = $value;
            }
        }

        return $unknown;
    }
}
