<?php

namespace Tests\Unit\Printing;

use App\Printing\Exceptions\PrintValidationFailed;
use App\Printing\Printer;
use App\Printing\PrinterState;
use App\Printing\PrintJob;
use App\Printing\PrintSubmissionRequest;
use PHPUnit\Framework\TestCase;

class ValueObjectsTest extends TestCase
{
    public function test_value_objects_preserve_printing_details(): void
    {
        $printer = new Printer('office', 'Office printer', PrinterState::Ready);
        $request = new PrintSubmissionRequest('office', 'documents/report.pdf');
        $job = new PrintJob('office-42');

        $this->assertSame('office', $printer->name);
        $this->assertSame('Office printer', $printer->displayName);
        $this->assertSame(PrinterState::Ready, $printer->state);
        $this->assertSame('office', $request->printerName);
        $this->assertSame('documents/report.pdf', $request->documentPath);
        $this->assertSame('office-42', $job->backendJobId);
    }

    public function test_value_objects_reject_missing_identifiers(): void
    {
        $this->expectException(PrintValidationFailed::class);

        new PrintSubmissionRequest('', 'documents/report.pdf');
    }
}
