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
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DuplicateTransactionCandidateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

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

        $this->account = Account::factory()->zeroBalance()->create([
            'household_id' => $this->household->id,
        ]);
    }

    #[Test]
    public function index_lists_pending_candidates_for_current_user(): void
    {
        [$primary, $candidate] = $this->createDuplicatePair();

        DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $primary->id,
            'candidate_transaction_id' => $candidate->id,
            'status' => 'pending',
            'distance_days' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('transactions.duplicates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Duplicates')
                ->has('items', 1)
                ->where('pendingCount', 1)
                ->where('items.0.primary.description', 'Esselunga')
                ->has('items.0.primary.category')
                ->has('items.0.primary.tags')
            );
    }

    #[Test]
    public function dismiss_keeps_both_transactions_and_hides_candidate(): void
    {
        [$primary, $candidate] = $this->createDuplicatePair();

        $duplicate = DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $primary->id,
            'candidate_transaction_id' => $candidate->id,
            'status' => 'pending',
            'distance_days' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.duplicates.dismiss', $duplicate))
            ->assertRedirect();

        $this->assertDatabaseHas('duplicate_transaction_candidates', [
            'id' => $duplicate->id,
            'status' => DuplicateTransactionCandidateService::STATUS_DISMISSED,
        ]);
        $this->assertNotSoftDeleted($primary);
        $this->assertNotSoftDeleted($candidate);
    }

    #[Test]
    public function remove_deletes_chosen_transaction_and_updates_account_balance(): void
    {
        [$primary, $candidate] = $this->createDuplicatePair();
        $this->account->recalculateBalance();
        $balanceBefore = (float) $this->account->fresh()->current_balance;

        $duplicate = DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $primary->id,
            'candidate_transaction_id' => $candidate->id,
            'status' => 'pending',
            'distance_days' => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.duplicates.remove', $duplicate), [
                'transaction_to_remove' => 'candidate',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($candidate);
        $this->assertNotSoftDeleted($primary);
        $this->assertDatabaseMissing('duplicate_transaction_candidates', ['id' => $duplicate->id]);
        $this->account->recalculateBalance();
        $this->assertEqualsWithDelta(-60.8, (float) $this->account->fresh()->current_balance, 0.01);
        $this->assertEqualsWithDelta($balanceBefore - (-60.80), (float) $this->account->current_balance, 0.01);
    }

    #[Test]
    public function keep_recurring_deletes_manual_transaction(): void
    {
        $category = Category::factory()->create(['household_id' => $this->household->id]);

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => -60.80,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2025-12-01',
            'description' => 'Fastweb',
        ]);

        $fromRecurring = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Fastweb',
            'amount' => -60.80,
            'date' => '2025-12-25',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $manual = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Fastweb',
            'amount' => -60.80,
            'date' => '2025-12-24',
            'recurring_transaction_id' => null,
        ]);

        $duplicate = DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $fromRecurring->id,
            'candidate_transaction_id' => $manual->id,
            'status' => 'pending',
            'distance_days' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('transactions.duplicates.index'))
            ->assertInertia(fn ($page) => $page
                ->where('items.0.pair_type', DuplicateTransactionCandidateService::PAIR_RECURRING_VS_MANUAL)
                ->where('recurringDuplicateCount', 1)
            );

        $this->actingAs($this->user)
            ->post(route('transactions.duplicates.keep-recurring', $duplicate))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted($fromRecurring);
        $this->assertSoftDeleted($manual);
        $this->assertDatabaseMissing('duplicate_transaction_candidates', ['id' => $duplicate->id]);
    }

    #[Test]
    public function resolve_all_recurring_resolves_only_recurring_vs_manual_pairs(): void
    {
        $category = Category::factory()->create(['household_id' => $this->household->id]);

        [$manualPairPrimary, $manualPairCandidate] = $this->createDuplicatePair();

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => -23.95,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2025-01-01',
            'description' => 'Abbonamento',
        ]);

        $recurringTx = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Abbonamento',
            'amount' => -23.95,
            'date' => '2026-01-25',
            'recurring_transaction_id' => $recurring->id,
        ]);

        $manualDup = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Abbonamento',
            'amount' => -23.95,
            'date' => '2026-01-24',
        ]);

        DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $manualPairPrimary->id,
            'candidate_transaction_id' => $manualPairCandidate->id,
            'status' => 'pending',
            'distance_days' => 1,
        ]);

        $recurringDuplicate = DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $recurringTx->id,
            'candidate_transaction_id' => $manualDup->id,
            'status' => 'pending',
            'distance_days' => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.duplicates.resolve-all-recurring'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($manualDup);
        $this->assertNotSoftDeleted($recurringTx);
        $this->assertDatabaseMissing('duplicate_transaction_candidates', ['id' => $recurringDuplicate->id]);
        $manualDuplicate = DuplicateTransactionCandidate::query()
            ->where('primary_transaction_id', $manualPairPrimary->id)
            ->first();
        $this->assertNotNull($manualDuplicate);
        $this->assertSame('pending', $manualDuplicate->status);
    }

    #[Test]
    public function index_classifies_recurring_vs_manual_when_both_transactions_lack_recurring_fk(): void
    {
        $category = Category::factory()->create(['household_id' => $this->household->id]);

        RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => -45.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2025-06-01',
            'last_generated_date' => '2025-12-25',
            'description' => 'Bolletta luce',
        ]);

        $generatedLike = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Bolletta luce',
            'amount' => -45.00,
            'date' => '2025-12-25',
            'recurring_transaction_id' => null,
            'recurring' => true,
        ]);

        $manual = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Bolletta luce',
            'amount' => -45.00,
            'date' => '2025-12-24',
            'recurring_transaction_id' => null,
            'recurring' => false,
        ]);

        DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $generatedLike->id,
            'candidate_transaction_id' => $manual->id,
            'status' => 'pending',
            'distance_days' => 1,
        ]);

        $this->actingAs($this->user)
            ->get(route('transactions.duplicates.index'))
            ->assertInertia(fn ($page) => $page
                ->where('items.0.pair_type', DuplicateTransactionCandidateService::PAIR_RECURRING_VS_MANUAL)
                ->where('items.0.primary.entry_source', 'recurring')
                ->where('items.0.candidate.entry_source', 'manual')
                ->where('recurringDuplicateCount', 1)
            );
    }

    #[Test]
    public function remove_requires_transaction_to_remove_field(): void
    {
        [$primary, $candidate] = $this->createDuplicatePair();

        $duplicate = DuplicateTransactionCandidate::create([
            'user_id' => $this->user->id,
            'primary_transaction_id' => $primary->id,
            'candidate_transaction_id' => $candidate->id,
            'status' => 'pending',
            'distance_days' => 0,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.duplicates.remove', $duplicate), [])
            ->assertSessionHasErrors('transaction_to_remove');
    }

    #[Test]
    public function manual_detect_route_creates_duplicate_candidate_for_current_user(): void
    {
        [$primary, $candidate] = $this->createDuplicatePair();
        $otherUser = User::factory()->create();
        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $this->account->id,
            'description' => $primary->description,
            'amount' => $primary->amount,
            'date' => $primary->date,
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.duplicates.detect'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('duplicate_transaction_candidates', [
            'user_id' => $this->user->id,
            'primary_transaction_id' => min($primary->id, $candidate->id),
            'candidate_transaction_id' => max($primary->id, $candidate->id),
            'status' => DuplicateTransactionCandidateService::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('duplicate_transaction_candidates', [
            'user_id' => $otherUser->id,
        ]);
    }

    /**
     * @return array{0: Transaction, 1: Transaction}
     */
    private function createDuplicatePair(): array
    {
        $category = Category::factory()->create(['household_id' => $this->household->id]);

        $primary = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Esselunga',
            'amount' => -60.80,
            'date' => '2025-12-20',
        ]);

        $candidate = Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'description' => 'Esselunga',
            'amount' => -60.80,
            'date' => '2025-12-21',
        ]);

        return [$primary, $candidate];
    }
}
