<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $options = app(\App\Actions\Passkeys\GeneratePlatformRegistrationOptions::class)($user);

        $this->assertSame(
            \Webauthn\AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            $options->authenticatorSelection?->authenticatorAttachment
        );
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
