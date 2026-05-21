<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionFilterAmountRangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_filters_by_absolute_amount_range(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -25,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => 100,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -500,
        ]);

        $response = $this->actingAs($user->fresh())->get(route('transactions.index', [
            'amount_min' => '30',
            'amount_max' => '120',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->where('filters.amount_min', '30')
            ->where('filters.amount_max', '120')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.amount', 100));
    }
}
