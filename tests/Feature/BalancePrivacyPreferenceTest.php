<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalancePrivacyPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_user_can_hide_balances(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this
            ->actingAs($user)
            ->patchJson('/utente/preferenze/saldi', ['hide_balances' => true]);

        $response->assertOk()->assertJson(['hide_balances' => true]);

        $user->refresh();
        $this->assertTrue($user->preferences['hide_balances']);
    }

    public function test_user_can_show_balances_again(): void
    {
        $user = $this->createUserWithActiveHousehold();
        $user->update(['preferences' => ['hide_balances' => true]]);

        $response = $this
            ->actingAs($user)
            ->patchJson('/utente/preferenze/saldi', ['hide_balances' => false]);

        $response->assertOk()->assertJson(['hide_balances' => false]);

        $user->refresh();
        $this->assertFalse($user->preferences['hide_balances']);
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
