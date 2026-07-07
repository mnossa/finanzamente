<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountDepositTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_create_savings_deposit_account_with_interest_rate(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $this->withoutMiddleware()
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'name' => 'Deposito BBVA',
                'type' => 'savings_deposit',
                'initial_balance' => 1000,
                'interest_rate' => 3.25,
                'currency_code' => 'EUR',
                'is_private' => false,
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'household_id' => $household->id,
            'name' => 'Deposito BBVA',
            'type' => 'bank',
            'interest_rate' => 3.25,
            'currency_code' => 'EUR',
        ]);
    }
}
