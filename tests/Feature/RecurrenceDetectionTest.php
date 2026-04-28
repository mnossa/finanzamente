<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionSuggestion;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurrenceDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurrenceDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $category;

    private RecurrenceDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'persona']);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
            'active' => true,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->service = app(RecurrenceDetectionService::class);
    }

    #[Test]
    public function it_detects_monthly_recurring_pattern(): void
    {
        // 4 transazioni mensili dello stesso importo e categoria
        foreach (range(1, 4) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -50.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(1, $created);

        $suggestion = RecurringTransactionSuggestion::first();
        $this->assertNotNull($suggestion);
        $this->assertSame('monthly', $suggestion->detected_frequency);
        $this->assertSame('pending', $suggestion->status);
        $this->assertEquals(-50.00, (float) $suggestion->amount);
        $this->assertCount(4, $suggestion->transaction_ids);
    }

    #[Test]
    public function it_does_not_detect_pattern_with_fewer_than_3_transactions(): void
    {
        foreach (range(1, 2) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -30.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(2 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('recurring_transaction_suggestions', 0);
    }

    #[Test]
    public function it_skips_already_linked_recurring_transactions(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -80.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => Carbon::now()->subMonths(4),
            'last_generated_date' => Carbon::now()->subMonth(),
        ]);

        foreach (range(1, 4) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -80.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => true,
                'recurring_transaction_id' => $recurring->id,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(0, $created);
    }

    #[Test]
    public function it_does_not_create_duplicate_suggestions(): void
    {
        foreach (range(1, 3) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -25.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(3 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $this->service->detectForHousehold($this->household->id);
        $this->service->detectForHousehold($this->household->id);

        $this->assertDatabaseCount('recurring_transaction_suggestions', 1);
    }

    #[Test]
    public function it_detects_yearly_recurring_pattern(): void
    {
        foreach (range(1, 3) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -200.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subYears(3 - $i)->startOfYear(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(1, $created);
        $this->assertSame('yearly', RecurringTransactionSuggestion::first()->detected_frequency);
    }
}
