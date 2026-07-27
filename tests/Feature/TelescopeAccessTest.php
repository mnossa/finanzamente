<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelescopeAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function telescope_is_not_registered_when_disabled(): void
    {
        // phpunit.xml force TELESCOPE_ENABLED=false and APP_ENV=testing.
        $this->assertFalse(filter_var(env('TELESCOPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse($this->app->providerIsLoaded(TelescopeServiceProvider::class));
    }

    #[Test]
    public function telescope_route_is_unavailable_in_testing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/telescope')
            ->assertNotFound();
    }
}
