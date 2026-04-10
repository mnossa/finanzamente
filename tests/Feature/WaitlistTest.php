<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WaitlistService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    // ─── WaitlistService ───────────────────────────────────────────

    public function test_signature_generation_is_deterministic(): void
    {
        $service = app(WaitlistService::class);
        $email = 'test@example.com';

        $sig1 = $service->generateSignature($email);
        $sig2 = $service->generateSignature($email);

        $this->assertSame($sig1, $sig2);
        $this->assertSame(64, strlen($sig1)); // sha256 hex = 64 chars
    }

    public function test_signature_verification_passes_for_valid_signature(): void
    {
        $service = app(WaitlistService::class);
        $email = 'test@example.com';

        $sig = $service->generateSignature($email);

        $this->assertTrue($service->verifySignature($email, $sig));
    }

    public function test_signature_verification_fails_for_wrong_email(): void
    {
        $service = app(WaitlistService::class);

        $sig = $service->generateSignature('a@example.com');

        $this->assertFalse($service->verifySignature('b@example.com', $sig));
    }

    public function test_signature_verification_fails_for_tampered_signature(): void
    {
        $service = app(WaitlistService::class);
        $email = 'test@example.com';

        $sig = str_repeat('0', 64); // signature azzerata

        $this->assertFalse($service->verifySignature($email, $sig));
    }

    public function test_signature_is_case_insensitive_on_email(): void
    {
        $service = app(WaitlistService::class);

        $sig = $service->generateSignature('Test@Example.COM');
        $this->assertTrue($service->verifySignature('test@example.com', $sig));
    }

    // ─── WaitlistController ────────────────────────────────────────

    public function test_waitlist_store_returns_redirect_back_on_success(): void
    {
        // Usa un mock del WaitlistService per evitare chiamate Brevo reali
        $this->mock(WaitlistService::class, function ($mock) {
            $mock->shouldReceive('subscribe')->once()->andReturn(true);
        });

        $response = $this->from('/')->post(route('waitlist.store'), [
            'email' => 'nuovo@example.com',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('waitlist_success', true);
    }

    public function test_waitlist_store_returns_success_even_when_brevo_fails(): void
    {
        // Anche se Brevo fallisce, l'utente deve vedere un messaggio positivo
        $this->mock(WaitlistService::class, function ($mock) {
            $mock->shouldReceive('subscribe')->once()->andReturn(false);
        });

        $response = $this->from('/')->post(route('waitlist.store'), [
            'email' => 'nuovo@example.com',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('waitlist_success', true);
    }

    public function test_waitlist_store_validates_email(): void
    {
        $response = $this->from('/')->post(route('waitlist.store'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_waitlist_store_requires_email(): void
    {
        $response = $this->from('/')->post(route('waitlist.store'), []);

        $response->assertSessionHasErrors('email');
    }

    // ─── Pre-Launch Mode ────────────────────────────────────────────

    public function test_pre_launch_mode_blocks_dashboard_for_non_owner(): void
    {
        config(['prelaunch.enabled' => true, 'prelaunch.owner_email' => 'owner@example.com']);

        $user = User::factory()->create(['email' => 'other@example.com']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_pre_launch_mode_allows_owner_access(): void
    {
        config(['prelaunch.enabled' => true, 'prelaunch.owner_email' => 'owner@example.com']);

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
        ]);
        // Crea una household per evitare redirect da EnsureHasActiveHousehold
        $household = \App\Models\Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        // Il proprietario deve passare il middleware pre-launch (200 o redirect per altri motivi)
        $this->assertAuthenticated();
        $response->assertStatus(200);
    }

    public function test_pre_launch_mode_disabled_allows_all_users(): void
    {
        config(['prelaunch.enabled' => false]);

        $user = User::factory()->create(['email' => 'other@example.com', 'email_verified_at' => now()]);
        $household = \App\Models\Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertAuthenticated();
        $response->assertStatus(200);
    }

    public function test_pre_launch_mode_blocks_registration_for_non_owner(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['prelaunch.enabled' => true, 'prelaunch.owner_email' => 'owner@example.com']);

        $response = $this->post('/registrati', [
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }

    // ─── Early Bird Flag ────────────────────────────────────────────

    public function test_registration_with_valid_signature_sets_early_bird(): void
    {
        $service = app(WaitlistService::class);
        $email = 'earlybird@example.com';
        $sig = $service->generateSignature($email);

        $response = $this->post('/registrati', [
            'name' => 'Early Bird',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'sig' => $sig,
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'is_early_bird' => true,
        ]);
    }

    public function test_registration_without_signature_does_not_set_early_bird(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Normal User',
            'email' => 'normal@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'normal@example.com',
            'is_early_bird' => false,
        ]);
    }

    public function test_registration_with_invalid_signature_does_not_set_early_bird(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Normal User',
            'email' => 'normal2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'sig' => str_repeat('a', 64), // firma non valida
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'normal2@example.com',
            'is_early_bird' => false,
        ]);
    }

    // ─── Tally Webhook ─────────────────────────────────────────────

    private function tallyPayload(string $email): string
    {
        return json_encode([
            'eventType' => 'FORM_RESPONSE',
            'data'      => [
                'fields' => [
                    ['type' => 'INPUT_EMAIL', 'value' => $email],
                ],
            ],
        ]);
    }

    public function test_tally_webhook_accepts_x_tally_signature_header(): void
    {
        config(['services.tally.webhook_secret' => 'test-secret']);

        $this->mock(WaitlistService::class, function ($mock) {
            $mock->shouldReceive('subscribe')->once()->andReturn(true);
        });

        $body = $this->tallyPayload('survey@example.com');
        $sig  = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));

        $response = $this->postJson('/webhooks/tally', json_decode($body, true), [
            'X-Tally-Signature' => $sig,
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_tally_webhook_accepts_tally_signature_header_without_x_prefix(): void
    {
        config(['services.tally.webhook_secret' => 'test-secret']);

        $this->mock(WaitlistService::class, function ($mock) {
            $mock->shouldReceive('subscribe')->once()->andReturn(true);
        });

        $body = $this->tallyPayload('survey2@example.com');
        $sig  = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));

        $response = $this->postJson('/webhooks/tally', json_decode($body, true), [
            'Tally-Signature' => $sig,
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_tally_webhook_returns_401_with_invalid_signature(): void
    {
        config(['services.tally.webhook_secret' => 'test-secret']);

        $body = $this->tallyPayload('bad@example.com');

        $response = $this->postJson('/webhooks/tally', json_decode($body, true), [
            'X-Tally-Signature' => 'invalidsignature',
        ]);

        $response->assertStatus(401);
    }

    public function test_tally_webhook_returns_501_when_secret_not_configured(): void
    {
        config(['services.tally.webhook_secret' => '']);

        $body = $this->tallyPayload('no@example.com');

        $response = $this->postJson('/webhooks/tally', json_decode($body, true));

        $response->assertStatus(501);
    }

    // ─── DOI Conferma ──────────────────────────────────────────────

    public function test_waitlist_confirm_with_valid_signature_calls_confirm_subscription(): void
    {
        $this->mock(WaitlistService::class, function ($mock) {
            $mock->shouldReceive('confirmSubscription')
                ->once()
                ->with('test@example.com')
                ->andReturn(true);
        });

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'waitlist.confirm',
            now()->addDays(7),
            ['email' => 'test@example.com']
        );

        $response = $this->get($url);

        $response->assertRedirect(route('waitlist.confirmed'));
    }

    public function test_waitlist_confirm_with_expired_signature_returns_403(): void
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'waitlist.confirm',
            now()->subSecond(), // scaduto
            ['email' => 'test@example.com']
        );

        $response = $this->get($url);

        $response->assertStatus(403);
    }

    public function test_waitlist_confirm_without_signature_returns_403(): void
    {
        $response = $this->get(route('waitlist.confirm', ['email' => 'test@example.com']));

        $response->assertStatus(403);
    }

    public function test_waitlist_confirm_redirects_to_confirmed_even_when_brevo_fails(): void
    {
        $this->mock(WaitlistService::class, function ($mock) {
            $mock->shouldReceive('confirmSubscription')
                ->once()
                ->andReturn(false);
        });

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'waitlist.confirm',
            now()->addDays(7),
            ['email' => 'test@example.com']
        );

        $response = $this->get($url);

        $response->assertRedirect(route('waitlist.confirmed'));
    }
}
