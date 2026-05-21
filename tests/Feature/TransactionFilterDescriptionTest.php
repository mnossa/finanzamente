<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionFilterDescriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_filters_by_description_tokens_with_partial_match(): void
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
            'description' => 'Spesa Supermercato Esselunga',
            'amount' => -50,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'description' => 'Bolletta luce',
            'amount' => -80,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'description' => 'supermercato esselunga',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.description', 'Spesa Supermercato Esselunga'));
    }
}
