<?php

namespace Tests\Feature;

use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\FakePrintBackend;
use App\Printing\Printer;
use App\Printing\PrinterState;
use App\Printing\PrintJob;
use App\Printing\PrintSubmissionRequest;
use Tests\TestCase;

class PrintBackendContainerTest extends TestCase
{
    public function test_default_backend_reports_that_printing_is_unavailable(): void
    {
        $backend = $this->app->make(PrintBackend::class);

        $this->expectException(PrintBackendUnavailable::class);

        $backend->printers();
    }

    public function test_fake_backend_can_replace_the_application_binding(): void
    {
        $request = new PrintSubmissionRequest('office', 'documents/report.pdf');
        $returnedJob = new PrintJob('office-42');
        $fakeBackend = new FakePrintBackend(
            [new Printer('office', 'Office printer', PrinterState::Ready)],
            $returnedJob,
        );

        $this->app->instance(PrintBackend::class, $fakeBackend);

        $backend = $this->app->make(PrintBackend::class);
        $job = $backend->submit($request);

        $this->assertSame($fakeBackend, $backend);
        $this->assertSame($returnedJob, $job);
        $this->assertSame([$request], $fakeBackend->submittedRequests());
        $this->assertSame('office', $backend->printers()[0]->name);
    }
}
