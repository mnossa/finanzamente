<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\BrevoMarketingService;
use App\Services\ConsentService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->get('/profilo');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->patch('/profilo', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profilo');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->patch('/profilo', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profilo');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->delete('/profilo', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_user_can_update_optional_consents_from_profile(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(ConsentService::class);
        $this->mock(BrevoMarketingService::class, function ($mock) {
            $mock->shouldReceive('syncMarketingConsent')->once();
        });
        $service->setConsent($user, 'marketing_email', 'revoked', ['source' => 'seed', 'policy_version' => 'v1']);
        $service->setConsent($user, 'analytics_tracking', 'revoked', ['source' => 'seed', 'policy_version' => 'v1']);

        $response = $this
            ->actingAs($user)
            ->patch('/profilo/consensi', [
                'marketing_email' => true,
                'analytics_tracking' => false,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profilo');

        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'purpose' => 'marketing_email',
            'status' => 'granted',
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status' => 'revoked',
        ]);
        $this->assertDatabaseHas('consent_events', [
            'user_id' => $user->id,
            'source' => 'profile_settings',
        ]);
    }

    public function test_user_can_export_consent_history_as_json(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(ConsentService::class);
        $service->setConsent($user, 'marketing_email', 'granted', [
            'source' => 'profile_settings',
            'policy_version' => config('legal.privacy_policy_version'),
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profilo/consensi/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertHeader('content-disposition');
        $content = $response->streamedContent();
        $this->assertStringContainsString('marketing_email', $content);
        $this->assertStringContainsString('generated_at', $content);
    }

    public function test_user_can_revoke_all_optional_consents_with_single_action(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(ConsentService::class);
        $this->mock(BrevoMarketingService::class, function ($mock) {
            $mock->shouldReceive('syncMarketingConsent')->once();
        });
        $service->setConsent($user, 'marketing_email', 'granted', ['source' => 'seed', 'policy_version' => 'v1']);
        $service->setConsent($user, 'analytics_tracking', 'granted', ['source' => 'seed', 'policy_version' => 'v1']);

        $response = $this->actingAs($user)->post('/profilo/consensi/revoca-opzionali');

        $response->assertRedirect('/profilo');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'purpose' => 'marketing_email',
            'status' => 'revoked',
        ]);
        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status' => 'revoked',
        ]);
    }

    public function test_user_can_sync_analytics_consent_from_public_blade_choice(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $service = app(ConsentService::class);
        $service->setConsent($user, 'analytics_tracking', 'revoked', ['source' => 'seed', 'policy_version' => 'v1']);

        $response = $this
            ->actingAs($user)
            ->post('/profilo/consensi/sync-analytics', [
                'analytics_tracking' => true,
            ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status' => 'granted',
            'source' => 'public_blade_sync',
        ]);
        $this->assertDatabaseHas('consent_events', [
            'user_id' => $user->id,
            'source' => 'public_blade_sync',
        ]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->from('/profilo')
            ->delete('/profilo', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profilo');

        $this->assertNotNull($user->fresh());
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Household Profilo',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        return $user;
    }
}
