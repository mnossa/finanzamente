<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DebtCreditUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

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
    }

    #[Test]
    public function update_syncs_initial_amount_when_nothing_paid(): void
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Mario',
            'amount' => 500,
            'initial_amount' => 500,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $this->actingAs($this->user)->put(route('debts-credits.update', $debt), [
            'counterparty' => 'Mario',
            'amount' => 600,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'interest_rate' => 3.5,
            'interest_type' => 'simple',
        ])->assertRedirect();

        $debt->refresh();
        $this->assertSame(600.0, (float) $debt->initial_amount);
        $this->assertSame(3.5, (float) $debt->interest_rate);
    }

    #[Test]
    public function cannot_change_type_when_transactions_linked(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);
        $category = Category::factory()->create(['household_id' => $this->household->id]);

        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Mario',
            'amount' => 500,
            'initial_amount' => 500,
            'paid_amount' => 100,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -100,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'debt_credit_id' => $debt->id,
        ]);

        $this->actingAs($this->user)->put(route('debts-credits.update', $debt), [
            'counterparty' => 'Mario',
            'amount' => 500,
            'currency_code' => 'EUR',
            'type' => 'credit',
            'status' => 'open',
        ])->assertSessionHasErrors('type');
    }
}
