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

class DashboardTest extends TestCase
{
    public function test_dashboard_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Printgate is ready.');
    }

    public function test_dashboard_lists_printers_from_the_print_backend(): void
    {
        $this->app->instance(PrintBackend::class, new FakePrintBackend([
            new Printer('office', 'Office printer', PrinterState::Ready),
        ]));

        $response = $this->get('/');

        $response->assertSee('Office printer');
        $response->assertSee('office');
        $response->assertSee('Ready');
    }

    public function test_dashboard_explains_when_printer_discovery_is_unavailable(): void
    {
        $this->app->instance(PrintBackend::class, new class implements PrintBackend
        {
            public function printers(): array
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }

            public function submit(PrintSubmissionRequest $request): PrintJob
            {
                throw new PrintBackendUnavailable('CUPS is unavailable.');
            }
        });

        $response = $this->get('/');

        $response->assertSee('Printer discovery is unavailable.');
    }
}
