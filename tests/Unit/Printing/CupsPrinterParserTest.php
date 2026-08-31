<?php

namespace Tests\Unit\Printing;

use App\Printing\CupsPrinterOutputMalformed;
use App\Printing\CupsPrinterParser;
use App\Printing\PrinterState;
use PHPUnit\Framework\TestCase;

class CupsPrinterParserTest extends TestCase
{
    public function test_returns_printers_with_names_descriptions_and_normalized_states(): void
    {
        $output = file_get_contents(__DIR__.'/../../Fixtures/Cups/printers.txt');

        $printers = (new CupsPrinterParser)->parse($output);

        $this->assertCount(3, $printers);
        $this->assertSame('Office_Printer', $printers[0]->name);
        $this->assertSame('Main office printer', $printers[0]->displayName);
        $this->assertSame(PrinterState::Ready, $printers[0]->state);
        $this->assertSame('Shipping_Label', $printers[1]->name);
        $this->assertSame('Shipping labels', $printers[1]->displayName);
        $this->assertSame(PrinterState::Stopped, $printers[1]->state);
        $this->assertSame('Receipt', $printers[2]->displayName);
        $this->assertSame(PrinterState::Ready, $printers[2]->state);
    }

    public function test_uses_the_printer_name_when_cups_does_not_report_a_description(): void
    {
        $printers = (new CupsPrinterParser)->parse('printer Receipt is unavailable. enabled since now');

        $this->assertCount(1, $printers);
        $this->assertSame('Receipt', $printers[0]->displayName);
        $this->assertSame(PrinterState::Unknown, $printers[0]->state);
    }

    public function test_returns_an_empty_list_when_no_printers_are_configured(): void
    {
        $printers = (new CupsPrinterParser)->parse('');

        $this->assertSame([], $printers);
    }

    public function test_rejects_unrecognized_cups_output(): void
    {
        $output = file_get_contents(__DIR__.'/../../Fixtures/Cups/malformed-printers.txt');

        $this->expectException(CupsPrinterOutputMalformed::class);

        (new CupsPrinterParser)->parse($output);
    }
}
