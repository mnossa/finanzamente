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
        $this->get(route('magazine.index'))->assertOk();
        $this->get(route('waitlist.confirmed'))->assertOk();
    }

    #[Test]
    public function protected_routes_redirect_guests_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('profile.subscription'))->assertRedirect(route('login'));
        $this->get(route('admin.magazine.index'))->assertRedirect(route('login'));
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

    #[Test]
    public function magazine_admin_route_is_accessible_for_owner_user(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner-route-smoke@example.com',
            'email_verified_at' => now(),
        ]);

        config(['prelaunch.magazine_admin_email' => $owner->email]);

        $this->actingAs($owner)
            ->get(route('admin.magazine.index'))
            ->assertOk();
    }
}
