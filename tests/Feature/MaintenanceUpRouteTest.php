<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceUpRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_route_stays_available_during_maintenance_with_deploy_view(): void
    {
        $this->artisan('down', [
            '--retry' => '60',
            '--render' => 'maintenance.deploy',
        ]);

        try {
            $this->get('/up')->assertOk();
            $this->get('/')
                ->assertStatus(503)
                ->assertSee('Torniamo tra un attimo', false)
                ->assertSee('finanzamente-logo', false);
        } finally {
            $this->artisan('up');
        }
    }
}
