<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionStoreBalanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function store_updates_account_balance_once(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

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
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'type' => 'bank',
        ]);

        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'amount' => 100,
                'date' => now()->toDateString(),
                'description' => 'Spesa test',
            ])
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function store_future_transaction_does_not_change_stored_balance(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

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
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'type' => 'bank',
        ]);

        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
        ]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'amount' => 100,
                'date' => now()->addDays(10)->toDateString(),
                'description' => 'Spesa futura',
            ])
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);
    }
}
