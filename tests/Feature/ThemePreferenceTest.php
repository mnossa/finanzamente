<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_user_can_update_theme_to_dark(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->patchJson('/utente/preferenze/tema', ['theme' => 'dark']);

        $response->assertOk()->assertJson(['theme' => 'dark']);

        $user->refresh();
        $this->assertSame('dark', $user->preferences['theme']);
    }

    public function test_user_can_update_theme_to_light(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $user->update(['preferences' => ['theme' => 'dark']]);

        $response = $this
            ->actingAs($user)
            ->patchJson('/utente/preferenze/tema', ['theme' => 'light']);

        $response->assertOk()->assertJson(['theme' => 'light']);

        $user->refresh();
        $this->assertSame('light', $user->preferences['theme']);
    }

    public function test_theme_update_preserves_other_preferences(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $user->update(['preferences' => ['notifications' => true, 'theme' => 'light']]);

        $this
            ->actingAs($user)
            ->patchJson('/utente/preferenze/tema', ['theme' => 'dark']);

        $user->refresh();
        $this->assertSame('dark', $user->preferences['theme']);
        $this->assertTrue($user->preferences['notifications']);
    }

    public function test_invalid_theme_value_is_rejected(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->patchJson('/utente/preferenze/tema', ['theme' => 'invalid']);

        $response->assertUnprocessable();
    }

    public function test_unauthenticated_user_cannot_update_theme(): void
    {
        $response = $this->patchJson('/utente/preferenze/tema', ['theme' => 'dark']);

        $response->assertUnauthorized();
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Test Household',
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
