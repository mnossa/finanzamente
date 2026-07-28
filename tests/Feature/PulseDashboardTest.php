<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PulseDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('pulse.enabled', true);
    }

    #[Test]
    public function pulse_tables_exist_after_migrations(): void
    {
        $this->assertTrue(Schema::connection('pulse')->hasTable('pulse_values'));
        $this->assertTrue(Schema::connection('pulse')->hasTable('pulse_entries'));
        $this->assertTrue(Schema::connection('pulse')->hasTable('pulse_aggregates'));
    }

    #[Test]
    public function owner_can_open_pulse_dashboard(): void
    {
        Config::set('prelaunch.magazine_admin_email', 'owner@example.com');

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/pulse')
            ->assertOk();
    }

    #[Test]
    public function non_owner_cannot_open_pulse_dashboard(): void
    {
        Config::set('prelaunch.magazine_admin_email', 'owner@example.com');

        $user = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/pulse')
            ->assertForbidden();
    }

    #[Test]
    public function pulse_user_resolver_anonymizes_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
            'email_verified_at' => now(),
        ]);

        $resolved = \Laravel\Pulse\Facades\Pulse::resolveUsers(collect([$user->id]));
        $payload = $resolved->find($user->id);

        $this->assertSame('Utente #'.$user->id, $payload->name);
        $this->assertSame('', $payload->extra);
        $this->assertSame('', $payload->avatar);
        $this->assertStringNotContainsString('Mario', $payload->name);
        $this->assertStringNotContainsString('mario.rossi@example.com', json_encode($payload));
    }
}
