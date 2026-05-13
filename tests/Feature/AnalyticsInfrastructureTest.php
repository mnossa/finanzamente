<?php

namespace Tests\Feature;

use App\Models\Consent;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica che l'infrastruttura analytics (Umami) sia correttamente
 * configurata e gated sul consenso dell'utente.
 *
 * Non testa l'invio effettivo degli eventi Umami (client-side),
 * ma garantisce che le props Inertia necessarie siano presenti e coerenti.
 */
class AnalyticsInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    /** Crea un utente con household attivo (necessario per la dashboard Inertia). */
    private function makeUser(): User
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role'        => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        return $user;
    }

    // ─── Shared Inertia props ─────────────────────────────────────────────────

    public function test_umami_website_id_is_shared_as_inertia_prop(): void
    {
        config(['services.umami.website_id' => 'test-website-id-123']);

        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('umami.websiteId', 'test-website-id-123')
        );
    }

    public function test_privacy_analytics_disabled_without_consent(): void
    {
        $user = $this->makeUser();

        $this->assertFalse(
            Consent::where('user_id', $user->id)
                ->where('purpose', 'analytics_tracking')
                ->where('status', 'granted')
                ->exists()
        );

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('privacy.analytics_enabled', false)
        );
    }

    public function test_privacy_analytics_enabled_after_consent(): void
    {
        $user = $this->makeUser();

        Consent::create([
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status'  => 'granted',
            'source'         => 'test',
            'policy_version' => config('legal.privacy_policy_version', '1.0'),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('privacy.analytics_enabled', true)
        );
    }

    public function test_privacy_analytics_disabled_after_consent_revoked(): void
    {
        $user = $this->makeUser();

        Consent::create([
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status'  => 'denied',
            'source'         => 'test',
            'policy_version' => config('legal.privacy_policy_version', '1.0'),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('privacy.analytics_enabled', false)
        );
    }

    public function test_umami_websiteid_is_empty_when_not_configured(): void
    {
        config(['services.umami.website_id' => '']);

        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('umami.websiteId', '')
        );
    }

    // ─── Consent sync endpoint ────────────────────────────────────────────────

    public function test_sync_analytics_consent_grants_consent(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('profile.consents.sync-analytics'), [
                'analytics_tracking' => true,
            ])
            ->assertOk();

        $this->assertTrue(
            Consent::where('user_id', $user->id)
                ->where('purpose', 'analytics_tracking')
                ->where('status', 'granted')
                ->exists()
        );
    }

    public function test_sync_analytics_consent_denies_consent(): void
    {
        $user = $this->makeUser();

        Consent::create([
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status'  => 'granted',
            'source'         => 'test',
            'policy_version' => config('legal.privacy_policy_version', '1.0'),
        ]);

        $this->actingAs($user)
            ->post(route('profile.consents.sync-analytics'), [
                'analytics_tracking' => false,
            ])
            ->assertOk();

        $this->assertFalse(
            Consent::where('user_id', $user->id)
                ->where('purpose', 'analytics_tracking')
                ->where('status', 'granted')
                ->exists()
        );
    }

    public function test_guest_cannot_access_sync_analytics_endpoint(): void
    {
        $this->post(route('profile.consents.sync-analytics'), [
            'analytics_tracking' => true,
        ])->assertRedirect(route('login'));
    }
}
