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

class SavingsDepositExpenseTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $spendingAccount;

    private Account $depositAccount;

    private Category $expenseCategory;

    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->spendingAccount = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Conto Corrente',
            'type' => 'bank',
            'interest_rate' => null,
        ]);

        $this->depositAccount = Account::factory()->savingsDeposit()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Deposito Vincolato',
        ]);

        $this->expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
        ]);
    }

    #[Test]
    public function cannot_store_expense_on_savings_deposit_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $this->depositAccount->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 50,
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('account_id');
    }

    #[Test]
    public function can_store_income_on_savings_deposit_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $this->depositAccount->id,
                'category_id' => $this->incomeCategory->id,
                'amount' => 50,
                'date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function cannot_store_split_expense_using_savings_deposit_account(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $this->spendingAccount->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 100,
                'date' => now()->toDateString(),
                'splits' => [
                    ['account_id' => $this->depositAccount->id, 'amount' => 40],
                    ['account_id' => $this->spendingAccount->id, 'amount' => 60],
                ],
            ])
            ->assertSessionHasErrors('account_id');
    }

    #[Test]
    public function transaction_create_page_marks_savings_deposit_accounts(): void
    {
        $this->actingAs($this->user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Create')
                ->has('accounts', 2)
                ->where('accounts.0.is_savings_deposit', false)
                ->where('accounts.1.is_savings_deposit', true));
    }
}
