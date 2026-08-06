<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringTransactionAmountForkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

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
    }

    #[Test]
    public function amount_change_closes_old_recurring_and_creates_new_with_future_amount(): void
    {
        Carbon::setTestNow('2026-05-21');

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-02-01',
            'last_generated_date' => '2026-04-01',
        ]);

        foreach (['2026-02-01', '2026-03-01', '2026-04-01'] as $date) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -10,
                'date' => $date,
                'recurring_transaction_id' => $recurring->id,
            ]);
        }

        $this->actingAs($this->user)
            ->put(route('recurring-transactions.update', $recurring), [
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => 12,
                'frequency' => 'monthly',
                'start_date' => '2026-02-01',
                'end_date' => null,
                'description' => 'Abbonamento',
                'effective_date' => '2026-05-01',
            ])
            ->assertRedirect();

        $recurring->refresh();
        $this->assertNotNull($recurring->end_date);
        $this->assertNotNull($recurring->successor_recurring_transaction_id);

        $service = app(RecurringTransactionService::class);
        $expectedStart = $service->resolveOccurrenceDate(Carbon::parse('2026-05-01'))->toDateString();

        $successor = RecurringTransaction::find($recurring->successor_recurring_transaction_id);
        $this->assertNotNull($successor);
        $this->assertSame('-12.00', (string) $successor->amount);
        $this->assertSame($expectedStart, $successor->start_date->toDateString());

        $historical = Transaction::where('recurring_transaction_id', $recurring->id)->get();
        $this->assertCount(3, $historical);
        foreach ($historical as $tx) {
            $this->assertEquals(-10, (float) $tx->amount);
        }

        $this->assertTrue(
            Transaction::where('recurring_transaction_id', $successor->id)->where('amount', -12)->exists()
        );

        Carbon::setTestNow();
    }

    #[Test]
    public function category_only_update_syncs_linked_without_fork(): void
    {
        $newCategory = Category::factory()->create([
            'household_id' => $this->account->household_id,
            'type' => 'expense',
        ]);

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-03-01',
            'last_generated_date' => '2026-03-01',
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10,
            'date' => '2026-03-01',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $this->actingAs($this->user)
            ->put(route('recurring-transactions.update', $recurring), [
                'account_id' => $this->account->id,
                'category_id' => $newCategory->id,
                'amount' => 10,
                'frequency' => 'monthly',
                'start_date' => '2026-03-01',
                'end_date' => null,
                'description' => 'Test',
            ])
            ->assertRedirect(route('recurring-transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'recurring_transaction_id' => $recurring->id,
            'category_id' => $newCategory->id,
        ]);
        $this->assertNull($recurring->fresh()->successor_recurring_transaction_id);
    }
}
