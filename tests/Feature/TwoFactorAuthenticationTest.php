<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_user_can_enable_and_confirm_two_factor_authentication(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $google2fa = app(Google2FA::class);
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => time(),
                'two_factor_setup_secret' => $secret,
            ])
            ->post('/profilo/sicurezza/mfa/conferma', [
                'code' => $code,
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('two_factor_recovery_codes');

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue($service->hasConfirmedTwoFactor($user));
    }

    public function test_login_requires_two_factor_challenge_when_enabled(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $recoveryCodes = $service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->post('/accedi', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_two_factor_challenge_completes_login_with_totp_code(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $google2fa = app(Google2FA::class);
        $code = $google2fa->getCurrentOtp($secret);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($service->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->withSession(['login.id' => $user->id])
            ->post('/verifica-2fa', ['code' => $code]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_disable_two_factor_authentication(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(TwoFactorAuthenticationService::class);
        $secret = $service->generateSecret();
        $google2fa = app(Google2FA::class);
        $code = $google2fa->getCurrentOtp($secret);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $service->hashRecoveryCodes($service->generateRecoveryCodes()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/profilo/sicurezza/mfa/disabilita', [
                'password' => 'password',
                'code' => $code,
            ]);

        $response->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse($service->hasConfirmedTwoFactor($user));
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create(['profile_completed' => true]);

        $household = Household::create([
            'name' => 'Household MFA',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        return $user->fresh();
    }
}
