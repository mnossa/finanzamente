<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DebtCreditTransactionPrefillFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transaction_create_includes_debt_credit_prefill_payload(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€']);

        $account = Account::create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'name' => 'Conto Corrente',
            'currency_code' => 'EUR',
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'active' => true,
            'is_private' => false,
        ]);

        $category = Category::query()
            ->where('household_id', $household->id)
            ->where('type', 'expense')
            ->where('name', 'Mutuo')
            ->first();

        $this->assertNotNull($category);

        $debt = DebtCredit::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'counterparty' => 'Amico Mario',
            'amount' => 150,
            'initial_amount' => 150,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('transactions.create', ['debt_credit_id' => $debt->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('defaultDebtCreditId', (string) $debt->id)
                ->has('debtCreditPrefill')
                ->where('debtCreditPrefill.debt_credit_id', (string) $debt->id)
                ->where('debtCreditPrefill.transaction_type', 'expense')
                ->where('debtCreditPrefill.category_id', (string) $category->id)
                ->where('debtCreditPrefill.account_id', (string) $account->id)
                ->where('debtCreditPrefill.amount', '150.00')
                ->where('debtCreditPrefill.counterparty', 'Amico Mario'));
    }
}
