<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_registration_is_blocked_if_honeypot_field_filled(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Bot User',
            'email' => 'bot@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
            'my_name' => 'I am a bot', // honeypot field
            'my_time' => now()->subMinutes(2)->timestamp, // tempo valido
        ]);
        $response->assertStatus(200);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'bot@example.com',
        ]);
    }

    public function test_registration_is_blocked_if_honeypot_time_too_short(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Fast Bot',
            'email' => 'fastbot@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
            'my_name' => '', // honeypot vuoto
            'my_time' => now()->timestamp, // tempo troppo breve
        ]);
        $response->assertStatus(200);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'fastbot@example.com',
        ]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/registrati');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ]);

        $this->assertDatabaseHas('consents', [
            'user_id' => auth()->id(),
            'purpose' => 'privacy_policy_ack',
            'status' => 'granted',
            'policy_version' => config('legal.privacy_policy_version'),
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id' => auth()->id(),
            'purpose' => 'terms_ack',
            'status' => 'granted',
            'policy_version' => config('legal.privacy_policy_version'),
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id' => auth()->id(),
            'purpose' => 'marketing_email',
            'status' => 'revoked',
        ]);
    }

    public function test_optional_consents_can_be_granted_during_registration(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Consent User',
            'email' => 'consent@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'marketing_email' => true,
            'analytics_tracking' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $userId = auth()->id();
        $this->assertDatabaseHas('consents', [
            'user_id' => $userId,
            'purpose' => 'marketing_email',
            'status' => 'granted',
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id' => $userId,
            'purpose' => 'analytics_tracking',
            'status' => 'granted',
        ]);
    }

    public function test_new_users_with_vat_can_register(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test Company',
            'email' => 'company@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'partita_iva',
            'vat_number' => '12345678901',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'company@example.com',
            'user_type' => 'partita_iva',
            'vat_number' => '12345678901',
        ]);
    }

    public function test_new_users_can_register_without_fiscal_code(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test2@example.com',
            'user_type' => 'persona',
            'fiscal_code' => null,
        ]);
    }

    public function test_new_users_can_register_without_vat_number(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test Company',
            'email' => 'company2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'partita_iva',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'company2@example.com',
            'user_type' => 'partita_iva',
            'vat_number' => null,
        ]);
    }

    public function test_fiscal_code_format_is_validated(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'INVALID',
        ]);

        $response->assertSessionHasErrors('fiscal_code');
    }

    public function test_vat_number_format_is_validated(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test Company',
            'email' => 'company@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'partita_iva',
            'vat_number' => 'INVALID',
        ]);

        $response->assertSessionHasErrors('vat_number');
    }
}
