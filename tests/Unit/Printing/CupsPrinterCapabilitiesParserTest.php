<?php

namespace Tests\Unit\Printing;

use App\Printing\CupsPrinterCapabilitiesParser;
use App\Printing\PrinterColorMode;
use App\Printing\PrinterMediaSize;
use App\Printing\PrinterOrientation;
use App\Printing\PrinterSides;
use PHPUnit\Framework\TestCase;

class CupsPrinterCapabilitiesParserTest extends TestCase
{
    public function test_parses_a_standard_cups_capability_listing(): void
    {
        $output = file_get_contents(__DIR__.'/../../Fixtures/Cups/capabilities-office.txt');

        $capabilities = (new CupsPrinterCapabilitiesParser)->parse($output);

        $this->assertSame([PrinterMediaSize::Letter, PrinterMediaSize::A4, PrinterMediaSize::A5], $capabilities->mediaSizes);
        $this->assertSame(PrinterMediaSize::A4, $capabilities->defaultMediaSize);
        $this->assertSame([PrinterSides::OneSided, PrinterSides::TwoSidedLongEdge, PrinterSides::TwoSidedShortEdge], $capabilities->sides);
        $this->assertSame(PrinterSides::OneSided, $capabilities->defaultSides);
        $this->assertSame([PrinterColorMode::Grayscale, PrinterColorMode::Color], $capabilities->colorModes);
        $this->assertSame(PrinterColorMode::Color, $capabilities->defaultColorMode);
        $this->assertSame([PrinterOrientation::Portrait, PrinterOrientation::Landscape], $capabilities->orientations);
        $this->assertSame(PrinterOrientation::Portrait, $capabilities->defaultOrientation);
        $this->assertSame(['Custom_4x6'], $capabilities->unknownValues['media']);
    }

    public function test_accepts_driver_aliases_and_separate_defaults(): void
    {
        $output = file_get_contents(__DIR__.'/../../Fixtures/Cups/capabilities-label.txt');

        $capabilities = (new CupsPrinterCapabilitiesParser)->parse($output, 'media=iso_a4_210x297mm duplex=DuplexNoTumble print-color-mode=monochrome');

        $this->assertSame([PrinterMediaSize::A4, PrinterMediaSize::Letter], $capabilities->mediaSizes);
        $this->assertSame(PrinterMediaSize::A4, $capabilities->defaultMediaSize);
        $this->assertSame([PrinterSides::TwoSidedLongEdge, PrinterSides::TwoSidedShortEdge], $capabilities->sides);
        $this->assertSame(PrinterSides::TwoSidedLongEdge, $capabilities->defaultSides);
        $this->assertSame([PrinterColorMode::Grayscale, PrinterColorMode::Color], $capabilities->colorModes);
        $this->assertSame(PrinterColorMode::Grayscale, $capabilities->defaultColorMode);
    }

    public function test_missing_capabilities_are_empty_and_unknown_values_are_diagnostic_only(): void
    {
        $capabilities = (new CupsPrinterCapabilitiesParser)->parse('PageSize/Media Size: *Custom_7x9');

        $this->assertSame([], $capabilities->mediaSizes);
        $this->assertNull($capabilities->defaultMediaSize);
        $this->assertSame(['Custom_7x9'], $capabilities->unknownValues['media']);
        $this->assertSame([], $capabilities->sides);
        $this->assertNull($capabilities->defaultSides);
        $this->assertSame([], $capabilities->colorModes);
        $this->assertNull($capabilities->defaultColorMode);
        $this->assertSame([], $capabilities->orientations);
        $this->assertNull($capabilities->defaultOrientation);
    }
}
