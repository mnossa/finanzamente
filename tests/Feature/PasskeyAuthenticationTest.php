<?php

namespace Tests\Feature;

use App\Actions\Passkeys\GeneratePlatformRegistrationOptions;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Support\WebAuthn;
use Tests\TestCase;
use Webauthn\AuthenticatorSelectionCriteria;

class PasskeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_profile_includes_passkeys_payload(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Edit')
                ->has('passkeys')
                ->where('passkeys', [])
            );
    }

    public function test_passkey_manage_page_requires_password_confirmation(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $this->actingAs($user)
            ->get(route('profile.passkeys.manage'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_passkey_manage_page_lists_existing_passkeys(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $user->passkeys()->create([
            'name' => 'iPhone di prova',
            'credential_id' => 'test-credential-id',
            'credential' => [
                'publicKeyCredentialId' => 'test-credential-id',
                'type' => 'public-key',
                'transports' => [],
                'attestationType' => 'none',
                'trustPath' => ['type' => 'empty'],
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'credentialPublicKey' => 'dGVzdA',
                'userHandle' => 'handle',
                'counter' => 0,
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('profile.passkeys.manage'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/PasskeyManage')
                ->has('passkeys', 1)
                ->where('passkeys.0.name', 'iPhone di prova')
            );
    }

    public function test_guest_can_fetch_passkey_login_options(): void
    {
        $response = $this->getJson('/passkeys/login/options');

        $response->assertOk();
        $response->assertJsonStructure(['options']);
        $this->assertNotEmpty(session('passkey.verification_options'));
    }

    public function test_authenticated_user_can_fetch_registration_options_after_password_confirm(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson('/user/passkeys/options');

        $response->assertOk();
        $response->assertJsonStructure(['options']);
        $this->assertNotEmpty(session('passkey.registration_options'));

        $options = $response->json('options');
        $this->assertSame('platform', data_get($options, 'authenticatorSelection.authenticatorAttachment'));
        $this->assertSame('required', data_get($options, 'authenticatorSelection.residentKey'));
        $this->assertTrue((bool) data_get($options, 'authenticatorSelection.requireResidentKey'));
        $this->assertSame('preferred', data_get($options, 'authenticatorSelection.userVerification'));
        $this->assertTrue(empty(data_get($options, 'hints')));
        $this->assertSame(config('app.name'), data_get($options, 'rp.name'));
        $this->assertNotSame('', data_get($options, 'user.displayName'));
    }

    public function test_registration_options_compatibility_mode_relaxes_authenticator_attachment(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson('/user/passkeys/options?compatibility=1');

        $response->assertOk();

        $options = $response->json('options');
        $attachment = data_get($options, 'authenticatorSelection.authenticatorAttachment');
        $this->assertTrue($attachment === null || $attachment === 'null' || $attachment === '');
        $this->assertSame('required', data_get($options, 'authenticatorSelection.residentKey'));
        $this->assertSame('preferred', data_get($options, 'authenticatorSelection.userVerification'));
    }

    public function test_user_can_delete_own_passkey(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $passkey = $user->passkeys()->create([
            'name' => 'Da eliminare',
            'credential_id' => 'delete-me-credential',
            'credential' => [
                'publicKeyCredentialId' => 'delete-me-credential',
                'type' => 'public-key',
                'transports' => [],
                'attestationType' => 'none',
                'trustPath' => ['type' => 'empty'],
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'credentialPublicKey' => 'dGVzdA',
                'userHandle' => 'handle',
                'counter' => 0,
            ],
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/passkeys/'.$passkey->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_platform_registration_options_require_platform_authenticator(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $options = app(GeneratePlatformRegistrationOptions::class)($user);

        $this->assertSame(
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            $options->authenticatorSelection?->authenticatorAttachment
        );
        $this->assertTrue($options->authenticatorSelection?->requireResidentKey);
        $this->assertSame(
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            $options->authenticatorSelection?->userVerification
        );
        $this->assertTrue($options->hints === null || $options->hints === []);
        $this->assertSame(config('app.name'), $options->rp->name);

        $browser = WebAuthn::toBrowserArray($options);
        $this->assertSame('platform', data_get($browser, 'authenticatorSelection.authenticatorAttachment'));
        $this->assertTrue((bool) data_get($browser, 'authenticatorSelection.requireResidentKey'));
        $this->assertSame('preferred', data_get($browser, 'authenticatorSelection.userVerification'));
    }

    public function test_blank_user_name_still_produces_non_empty_passkey_display_name(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $user->forceFill(['name' => ''])->save();

        $options = app(GeneratePlatformRegistrationOptions::class)($user->fresh());

        $this->assertNotSame('', $options->user->displayName);
        $this->assertNotSame('', $options->user->name);
    }

    public function test_allowed_origins_include_www_sibling_of_app_url(): void
    {
        config([
            'app.url' => 'https://example.com',
        ]);

        // Reload passkeys config with the new app URL.
        $config = require base_path('config/passkeys.php');

        $this->assertContains('https://example.com', $config['allowed_origins']);
        $this->assertContains('https://www.example.com', $config['allowed_origins']);
        $this->assertSame('example.com', $config['relying_party_id']);
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create(['profile_completed' => true]);
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->households()->attach($household->id, ['role' => 'owner']);
        $user->forceFill(['active_household_id' => $household->id])->save();

        return $user->fresh();
    }
}
