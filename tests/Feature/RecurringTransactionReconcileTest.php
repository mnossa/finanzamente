<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BusinessDayService;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringTransactionReconcileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $category;

    private RecurringTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->service = app(RecurringTransactionService::class);
    }

    #[Test]
    public function reconcile_adds_missing_and_removes_extra_linked_transactions(): void
    {
        Carbon::setTestNow('2025-06-01');
        $start = Carbon::parse('2025-01-10');

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => $start->toDateString(),
            'description' => 'Test',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'date' => '2025-01-10',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $orphanDate = '2025-03-25';
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'date' => $orphanDate,
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $result = $this->service->reconcileLinkedTransactions($recurring);

        $this->assertGreaterThanOrEqual(1, $result->removed);
        $this->assertGreaterThanOrEqual(1, $result->created);

        $this->assertNull(
            Transaction::where('recurring_transaction_id', $recurring->id)
                ->whereDate('date', $orphanDate)
                ->first()
        );

        Carbon::setTestNow();
    }

    #[Test]
    public function saturday_occurrence_is_postponed_to_monday(): void
    {
        $saturday = Carbon::parse('2026-05-16');
        $adjusted = app(BusinessDayService::class)->adjustToNextWorkingDay($saturday);

        $this->assertSame('2026-05-18', $adjusted->toDateString());
    }

    #[Test]
    public function monthly_last_day_rule_generates_end_of_each_month(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'day_of_month_mode' => RecurringTransaction::DAY_OF_MONTH_MODE_LAST_DAY,
            'non_working_day_policy' => RecurringTransaction::NON_WORKING_DAY_POLICY_KEEP,
            'start_date' => '2026-01-10',
            'description' => 'Fine mese',
        ]);

        $generated = $this->service->generateTransactionsUntil($recurring, Carbon::parse('2026-04-30'));

        $this->assertSame(4, $generated);
        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
            Transaction::where('recurring_transaction_id', $recurring->id)
                ->orderBy('date')
                ->pluck('date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all()
        );
    }

    #[Test]
    public function fixed_day_rule_can_anticipate_holidays(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'day_of_month_mode' => RecurringTransaction::DAY_OF_MONTH_MODE_FIXED,
            'day_of_month' => 25,
            'non_working_day_policy' => RecurringTransaction::NON_WORKING_DAY_POLICY_ANTICIPATE,
            'start_date' => '2026-12-01',
            'description' => 'Festivo',
        ]);

        $generated = $this->service->generateTransactionsUntil($recurring, Carbon::parse('2026-12-25'));

        $this->assertSame(1, $generated);
        $this->assertSame(
            '2026-12-24',
            Transaction::where('recurring_transaction_id', $recurring->id)
                ->firstOrFail()
                ->date
                ->toDateString()
        );
    }
}
