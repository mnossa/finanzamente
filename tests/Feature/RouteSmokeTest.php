<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_routes_are_reachable(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('waitlist.confirmed'))->assertOk();
    }

    #[Test]
    public function legacy_magazine_urls_return_not_found(): void
    {
        $this->get('/magazine')->assertNotFound();
        $this->get('/magazine/some-article')->assertNotFound();
        $this->get('/admin/magazine')->assertNotFound();
        $this->get('/admin/product-analytics')->assertNotFound();
        $this->post('/product-analytics/events')->assertNotFound();
    }

    #[Test]
    public function protected_routes_redirect_guests_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('profile.subscription'))->assertRedirect(route('login'));
    }

    #[Test]
    public function dashboard_route_is_accessible_for_verified_user_with_active_household(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $household = Household::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'can-modify' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    #[Test]
    public function webhook_routes_are_reachable_and_return_expected_status_codes(): void
    {
        // Telegram: update senza "message" viene ignorato con 200.
        $this->postJson(route('telegram.webhook'), ['update_id' => 123])->assertOk();

        // Mollie: payload senza id viene ignorato con 200.
        $this->post(route('mollie.webhook'), [])->assertOk();

        // Tally: senza secret configurato il webhook è volutamente disabilitato (501).
        config(['services.tally.webhook_secret' => '']);
        $this->postJson(route('webhooks.tally'), [])->assertStatus(501);
    }
}
