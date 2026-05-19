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
        $start = Carbon::today()->subMonths(2)->startOfMonth();

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
            'date' => $start->toDateString(),
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $orphanDate = Carbon::today()->subDays(5)->toDateString();
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
    }

    #[Test]
    public function saturday_occurrence_is_postponed_to_monday(): void
    {
        $saturday = Carbon::parse('2026-05-16');
        $adjusted = app(BusinessDayService::class)->adjustToNextWorkingDay($saturday);

        $this->assertSame('2026-05-18', $adjusted->toDateString());
    }
}
