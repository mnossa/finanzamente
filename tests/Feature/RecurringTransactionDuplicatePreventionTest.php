<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringTransactionDuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $category;

    private RecurringTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
        ]);

        $this->service = app(RecurringTransactionService::class);
    }

    #[Test]
    public function generate_skips_occurrence_when_linked_transaction_is_within_monthly_window(): void
    {
        $start = Carbon::parse('2025-01-05');

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => $start->toDateString(),
            'description' => 'Abbonamento',
            'last_generated_date' => null,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'date' => '2025-02-07',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $generated = $this->service->generateTransactionsUntil($recurring, Carbon::parse('2025-02-28'));

        $this->assertSame(0, $generated);
        $this->assertNull(
            Transaction::where('recurring_transaction_id', $recurring->id)
                ->whereDate('date', '2025-02-05')
                ->first()
        );
    }

    #[Test]
    public function reconcile_keeps_linked_transaction_within_tolerance_of_expected_date(): void
    {
        Carbon::setTestNow('2025-04-01');
        $start = Carbon::parse('2025-01-05');

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => $start->toDateString(),
            'description' => 'Abbonamento',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'date' => $start->toDateString(),
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $nearExpected = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'date' => '2025-02-07',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $result = $this->service->reconcileLinkedTransactions($recurring);

        $this->assertSame(0, $result->removed);
        $this->assertNotNull(Transaction::find($nearExpected->id));

        Carbon::setTestNow();
    }

    #[Test]
    public function reconcile_removes_duplicate_in_same_period_and_keeps_closest_to_expected(): void
    {
        Carbon::setTestNow('2025-03-15');

        $start = Carbon::parse('2025-01-05');

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => $start->toDateString(),
            'description' => 'Abbonamento',
        ]);

        $kept = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'date' => '2025-02-05',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $duplicate = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'date' => '2025-02-07',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $result = $this->service->reconcileLinkedTransactions($recurring);

        $this->assertSame(1, $result->removed);
        $this->assertSoftDeleted($duplicate);
        $this->assertNotNull(Transaction::find($kept->id));
        $this->assertNull(Transaction::find($duplicate->id));
        $this->assertTrue(
            Transaction::where('recurring_transaction_id', $recurring->id)
                ->whereMonth('date', 2)
                ->whereYear('date', 2025)
                ->exists()
        );

        Carbon::setTestNow();
    }
}
