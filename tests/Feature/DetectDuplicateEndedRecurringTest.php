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

    #[Test]
    public function detect_flags_monthly_recurring_pair_only_one_day_apart(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2023-05-31',
            'end_date' => null,
            'description' => 'Mutuo',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-11-30',
            'recurring_transaction_id' => $recurring->id,
            'recurring' => true,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-12-01',
            'recurring_transaction_id' => $recurring->id,
            'recurring' => true,
        ]);

        $this->artisan('transactions:detect-duplicates', ['--days' => 3])
            ->assertSuccessful();

        $this->assertSame(1, DuplicateTransactionCandidate::where('user_id', $user->id)->count());
    }

    #[Test]
    public function detect_skips_legitimate_monthly_occurrences_about_one_month_apart(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2023-05-01',
            'end_date' => null,
            'description' => 'Mutuo',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-10-01',
            'recurring_transaction_id' => $recurring->id,
            'recurring' => true,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-11-01',
            'recurring_transaction_id' => $recurring->id,
            'recurring' => true,
        ]);

        $this->artisan('transactions:detect-duplicates', ['--days' => 35])
            ->assertSuccessful();

        $this->assertSame(0, DuplicateTransactionCandidate::where('user_id', $user->id)->count());
    }

    #[Test]
    public function detect_flags_mutuo_like_pair_without_recurring_foreign_key(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2023-05-31',
            'end_date' => null,
            'description' => 'Mutuo',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-11-30',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-12-01',
        ]);

        $this->artisan('transactions:detect-duplicates', ['--days' => 3])
            ->assertSuccessful();

        $this->assertSame(1, DuplicateTransactionCandidate::where('user_id', $user->id)->count());
    }

    #[Test]
    public function index_lists_pending_mutuo_pair_as_duplicate(): void
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

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2023-05-31',
            'description' => 'Mutuo',
        ]);

        $txA = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-11-30',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $txB = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-12-01',
            'recurring_transaction_id' => $recurring->id,
        ]);

        DuplicateTransactionCandidate::create([
            'user_id' => $user->id,
            'primary_transaction_id' => min($txA->id, $txB->id),
            'candidate_transaction_id' => max($txA->id, $txB->id),
            'status' => 'pending',
            'distance_days' => 1,
            'cluster_transaction_ids' => [$txA->id, $txB->id],
        ]);

        $this->actingAs($user)
            ->get(route('transactions.duplicates.index'))
            ->assertInertia(fn ($page) => $page
                ->has('items', 1)
                ->where('pendingCount', 1)
                ->where('items.0.pair_type', DuplicateTransactionCandidateService::PAIR_SAME_RECURRING)
            );
    }

    #[Test]
    public function classify_marks_one_day_apart_same_recurring_as_duplicate_not_occurrences(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['household_id' => Household::factory()->create(['owner_user_id' => $user->id])->id]);
        $category = Category::factory()->create(['household_id' => $account->household_id]);

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2023-05-31',
            'description' => 'Mutuo',
        ]);

        $primary = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-11-30',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $candidate = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mutuo',
            'amount' => -274.14,
            'currency_code' => 'EUR',
            'date' => '2025-12-01',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $primary->load('recurringTransaction');
        $candidate->load('recurringTransaction');

        $pair = app(DuplicateTransactionCandidateService::class)->classifyPair($primary, $candidate);

        $this->assertSame(DuplicateTransactionCandidateService::PAIR_SAME_RECURRING, $pair['type']);
    }

    #[Test]
    public function classify_marks_monthly_occurrences_one_month_apart_as_scheduled_not_duplicate(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['household_id' => Household::factory()->create(['owner_user_id' => $user->id])->id]);
        $category = Category::factory()->create(['household_id' => $account->household_id]);

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -60.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2025-01-01',
            'description' => 'Mesoterapia',
        ]);

        $primary = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mesoterapia',
            'amount' => -60.00,
            'currency_code' => 'EUR',
            'date' => '2025-10-05',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $candidate = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'Mesoterapia',
            'amount' => -60.00,
            'currency_code' => 'EUR',
            'date' => '2025-11-05',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $primary->load('recurringTransaction');
        $candidate->load('recurringTransaction');

        $pair = app(DuplicateTransactionCandidateService::class)->classifyPair($primary, $candidate);

        $this->assertSame(DuplicateTransactionCandidateService::PAIR_RECURRING_OCCURRENCES, $pair['type']);
    }
}
