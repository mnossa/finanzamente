<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Test per le tre modalità operative dell'applicazione:
 *
 * 1. Modalità NORMALE        — PRE_LAUNCH_MODE=false, PRO_WAITLIST_ENABLED=false
 * 2. Modalità PRE-LANCIO      — PRE_LAUNCH_MODE=true, PRE_LAUNCH_OWNER_EMAIL=...
 * 3. Modalità WAITLIST        — PRO_WAITLIST_ENABLED=true
 *
 * I flag di configurazione vengono sovrascritti via Config::set() per ogni scenario,
 * senza toccare il file .env o il server in esecuzione.
 */
class AppModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('prelaunch.enabled', false);
        Config::set('prelaunch.owner_email', '');
        Config::set('prelaunch.waitlist_enabled', false);
    }

    /**
     * Crea un utente con household attiva, pronto per accedere alla dashboard.
     */
    private function createUserWithHousehold(array $userAttributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
        ], $userAttributes));

        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role'        => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        return $user;
    }

    // ═══════════════════════════════════════════════════════════════
    // MODALITÀ NORMALE
    // ═══════════════════════════════════════════════════════════════

    public function test_normal_mode_homepage_is_accessible(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_normal_mode_register_page_is_accessible(): void
    {
        $response = $this->get('/registrati');
        $response->assertStatus(200);
    }

    public function test_normal_mode_allows_new_registration(): void
    {
        $response = $this->post('/registrati', [
            'name'                  => 'Utente Normale',
            'email'                 => 'normale@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'normale@example.com']);
    }

    public function test_normal_mode_homepage_exposes_false_prelaunch_flag(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewHas('preLaunchMode', false);
    }

    public function test_normal_mode_homepage_exposes_false_waitlist_flag(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewHas('waitlistEnabled', false);
    }

    public function test_normal_mode_login_page_shows_register_link(): void
    {
        $response = $this->get('/accedi');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('canRegister', true)
        );
    }

    public function test_normal_mode_authenticated_user_can_access_dashboard(): void
    {
        $user = $this->createUserWithHousehold();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    // ═══════════════════════════════════════════════════════════════
    // MODALITÀ PRE-LANCIO
    // ═══════════════════════════════════════════════════════════════

    public function test_prelaunch_mode_register_page_redirects_non_owner_to_home(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $response = $this->get('/registrati');

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('info');
    }

    public function test_prelaunch_mode_register_page_accessible_for_owner_email(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $response = $this->get('/registrati?email=owner@example.com');

        $response->assertStatus(200);
    }

    public function test_prelaunch_mode_blocks_registration_for_non_owner(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $response = $this->post('/registrati', [
            'name'                  => 'Intruso',
            'email'                 => 'intruso@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('info');
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_prelaunch_mode_allows_owner_registration(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $response = $this->post('/registrati', [
            'name'                  => 'Owner',
            'email'                 => 'owner@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com']);
    }

    public function test_prelaunch_mode_blocks_dashboard_for_non_owner_and_redirects(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $user = $this->createUserWithHousehold(['email' => 'utente@example.com']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('info');
    }

    public function test_prelaunch_mode_allows_owner_to_access_dashboard(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $owner = $this->createUserWithHousehold(['email' => 'owner@example.com']);

        $response = $this->actingAs($owner)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_prelaunch_mode_logs_out_non_owner_already_authenticated(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $user = $this->createUserWithHousehold(['email' => 'utente@example.com']);

        $this->actingAs($user)->get('/dashboard');

        $this->assertGuest();
    }

    public function test_prelaunch_mode_homepage_exposes_true_prelaunch_flag(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewHas('preLaunchMode', true);
    }

    public function test_prelaunch_mode_login_page_hides_register_link(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');

        $response = $this->get('/accedi');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('canRegister', false)
        );
    }

    public function test_prelaunch_mode_owner_email_comparison_is_case_insensitive(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'Owner@Example.COM');

        $owner = $this->createUserWithHousehold(['email' => 'owner@example.com']);

        $response = $this->actingAs($owner)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_prelaunch_mode_empty_owner_email_blocks_everyone(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', '');

        $response = $this->post('/registrati', [
            'name'                  => 'Chiunque',
            'email'                 => 'chiunque@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseMissing('users', ['email' => 'chiunque@example.com']);
    }

    // ═══════════════════════════════════════════════════════════════
    // MODALITÀ WAITLIST
    // ═══════════════════════════════════════════════════════════════

    public function test_waitlist_mode_homepage_exposes_true_waitlist_flag(): void
    {
        Config::set('prelaunch.waitlist_enabled', true);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertViewHas('waitlistEnabled', true);
    }

    public function test_waitlist_mode_register_page_is_still_accessible(): void
    {
        Config::set('prelaunch.waitlist_enabled', true);

        $response = $this->get('/registrati');
        $response->assertStatus(200);
    }

    public function test_waitlist_mode_allows_registration(): void
    {
        Config::set('prelaunch.waitlist_enabled', true);

        $response = $this->post('/registrati', [
            'name'                  => 'Utente Waitlist',
            'email'                 => 'waitlist@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'waitlist@example.com']);
    }

    public function test_waitlist_mode_authenticated_user_can_access_dashboard(): void
    {
        Config::set('prelaunch.waitlist_enabled', true);

        $user = $this->createUserWithHousehold();
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_waitlist_mode_plan_selection_exposes_waitlist_flag(): void
    {
        Config::set('prelaunch.waitlist_enabled', true);

        // /scegli-piano è una rotta guest-only che restituisce una view Blade
        $response = $this->get('/scegli-piano');
        $response->assertStatus(200);
        $response->assertViewHas('waitlistEnabled', true);
    }

    public function test_waitlist_mode_is_independent_from_prelaunch_mode(): void
    {
        Config::set('prelaunch.enabled', false);
        Config::set('prelaunch.waitlist_enabled', true);

        $response = $this->post('/registrati', [
            'name'                  => 'Utente Combinato',
            'email'                 => 'combinato@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'combinato@example.com']);
    }

    // ═══════════════════════════════════════════════════════════════
    // COMBINAZIONE PRE-LANCIO + WAITLIST
    // ═══════════════════════════════════════════════════════════════

    public function test_combined_prelaunch_and_waitlist_blocks_non_owner(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');
        Config::set('prelaunch.waitlist_enabled', true);

        $response = $this->post('/registrati', [
            'name'                  => 'Intruso',
            'email'                 => 'intruso2@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseMissing('users', ['email' => 'intruso2@example.com']);
    }

    public function test_combined_prelaunch_and_waitlist_allows_owner(): void
    {
        Config::set('prelaunch.enabled', true);
        Config::set('prelaunch.owner_email', 'owner@example.com');
        Config::set('prelaunch.waitlist_enabled', true);

        $response = $this->post('/registrati', [
            'name'                  => 'Owner Combinato',
            'email'                 => 'owner@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'user_type'             => 'persona',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com']);
    }
}
