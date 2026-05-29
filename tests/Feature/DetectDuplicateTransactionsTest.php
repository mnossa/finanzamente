<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\DuplicateTransactionCandidate;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DetectDuplicateTransactionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detect_command_creates_one_candidate_for_three_similar_transactions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-21'));

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        foreach (['2026-04-17', '2026-04-19', '2026-04-20'] as $date) {
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'description' => 'ETA - Visto Scozia',
                'amount' => -23.88,
                'currency_code' => 'EUR',
                'date' => $date,
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $this->assertSame(3, Transaction::where('user_id', $user->id)->count());

        $this->artisan('transactions:detect-duplicates', ['--days' => 3])
            ->assertSuccessful();

        $this->assertSame(1, DuplicateTransactionCandidate::where('user_id', $user->id)->count());

        $candidate = DuplicateTransactionCandidate::first();
        $this->assertNotNull($candidate);
        $this->assertCount(3, $candidate->cluster_transaction_ids);
        $this->assertSame('pending', $candidate->status);

        Carbon::setTestNow();
    }

    #[Test]
    public function detect_command_consolidates_legacy_pairwise_pending_rows(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'active' => true,
        ]);
        $category = Category::factory()->create(['household_id' => $household->id]);

        $txA = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'ETA - Visto Scozia',
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'date' => '2026-04-17',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);
        $txB = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'ETA - Visto Scozia',
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'date' => '2026-04-19',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);
        $txC = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'description' => 'ETA - Visto Scozia',
            'amount' => -23.88,
            'currency_code' => 'EUR',
            'date' => '2026-04-20',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        DuplicateTransactionCandidate::create([
            'user_id' => $user->id,
            'primary_transaction_id' => min($txA->id, $txB->id),
            'candidate_transaction_id' => max($txA->id, $txB->id),
            'status' => 'pending',
            'distance_days' => 2,
        ]);
        DuplicateTransactionCandidate::create([
            'user_id' => $user->id,
            'primary_transaction_id' => min($txB->id, $txC->id),
            'candidate_transaction_id' => max($txB->id, $txC->id),
            'status' => 'pending',
            'distance_days' => 1,
        ]);
        DuplicateTransactionCandidate::create([
            'user_id' => $user->id,
            'primary_transaction_id' => min($txA->id, $txC->id),
            'candidate_transaction_id' => max($txA->id, $txC->id),
            'status' => 'pending',
            'distance_days' => 3,
        ]);

        $this->artisan('transactions:detect-duplicates', ['--days' => 3])
            ->assertSuccessful();

        $this->assertSame(1, DuplicateTransactionCandidate::where('user_id', $user->id)->where('status', 'pending')->count());
        $this->assertCount(3, DuplicateTransactionCandidate::first()->cluster_transaction_ids);
    }
}
