<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\DuplicateTransactionCandidate;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DuplicateTransactionCandidateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DetectDuplicateEndedRecurringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detect_skips_cluster_of_ended_recurring_occurrences_in_different_months(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        $recurringA = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
            'description' => 'Abbonamento chiuso',
        ]);

        $recurringB = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'description' => 'Abbonamento chiuso',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Abbonamento chiuso',
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'date' => '2025-03-01',
            'recurring_transaction_id' => $recurringA->id,
            'recurring' => true,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Abbonamento chiuso',
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'date' => '2025-04-01',
            'recurring_transaction_id' => $recurringB->id,
            'recurring' => true,
        ]);

        $this->artisan('transactions:detect-duplicates', ['--days' => 35])
            ->assertSuccessful();

        $this->assertSame(0, DuplicateTransactionCandidate::where('user_id', $user->id)->count());
    }

    #[Test]
    public function index_hides_ended_recurring_history_pair(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        $recA = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'description' => 'Storico',
        ]);

        $recB = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
            'description' => 'Storico',
        ]);

        $txA = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Storico',
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'date' => '2024-03-01',
            'recurring_transaction_id' => $recA->id,
        ]);

        $txB = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Storico',
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'date' => '2024-04-01',
            'recurring_transaction_id' => $recB->id,
        ]);

        DuplicateTransactionCandidate::create([
            'user_id' => $user->id,
            'primary_transaction_id' => min($txA->id, $txB->id),
            'candidate_transaction_id' => max($txA->id, $txB->id),
            'status' => 'pending',
            'distance_days' => 31,
            'cluster_transaction_ids' => [$txA->id, $txB->id],
        ]);

        $this->actingAs($user)
            ->get(route('transactions.duplicates.index'))
            ->assertInertia(fn ($page) => $page
                ->has('items', 0)
                ->where('pendingCount', 0)
            );
    }

    #[Test]
    public function classify_marks_ended_recurring_history_pair(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['household_id' => Household::factory()->create(['owner_user_id' => $user->id])->id]);
        $category = Category::factory()->create(['household_id' => $account->household_id]);

        $recA = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'description' => 'Storico',
        ]);

        $recB = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
            'description' => 'Storico',
        ]);

        $primary = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Storico',
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'date' => '2024-03-01',
            'recurring_transaction_id' => $recA->id,
        ]);

        $candidate = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Storico',
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'date' => '2024-04-01',
            'recurring_transaction_id' => $recB->id,
        ]);

        $primary->load('recurringTransaction');
        $candidate->load('recurringTransaction');

        $pair = app(DuplicateTransactionCandidateService::class)->classifyPair($primary, $candidate);

        $this->assertSame(DuplicateTransactionCandidateService::PAIR_ENDED_RECURRING_HISTORY, $pair['type']);
    }
}
