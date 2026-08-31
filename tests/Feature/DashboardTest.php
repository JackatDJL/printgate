<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Printgate is ready.');
    }
}
