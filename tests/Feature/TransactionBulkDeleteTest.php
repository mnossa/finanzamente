<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionBulkDeleteTest extends TestCase
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
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'current_balance' => 1000.00,
        ]);
    }

    private function createTransaction(float $amount = -50.00): Transaction
    {
        return Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => $amount,
            'date' => now(),
            'description' => 'Test transaction',
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_bulk_delete(): void
    {
        $transaction = $this->createTransaction();

        $response = $this->delete(route('transactions.bulk-destroy'), [
            'ids' => [$transaction->id],
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function user_can_bulk_delete_transactions(): void
    {
        $t1 = $this->createTransaction(-100.00);
        $t2 = $this->createTransaction(-200.00);

        $response = $this->actingAs($this->user)->delete(route('transactions.bulk-destroy'), [
            'ids' => [$t1->id, $t2->id],
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertSoftDeleted('transactions', ['id' => $t1->id]);
        $this->assertSoftDeleted('transactions', ['id' => $t2->id]);
    }

    #[Test]
    public function bulk_delete_updates_account_balance(): void
    {
        $t1 = $this->createTransaction(-100.00);
        $t2 = $this->createTransaction(-200.00);

        $this->actingAs($this->user)->delete(route('transactions.bulk-destroy'), [
            'ids' => [$t1->id, $t2->id],
        ]);

        // After deleting all transactions, balance is recalculated to initial_balance
        $this->account->refresh();
        $this->assertEqualsWithDelta(
            (float) $this->account->initial_balance,
            (float) $this->account->current_balance,
            0.01
        );
    }

    #[Test]
    public function user_cannot_bulk_delete_transactions_from_other_household(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherHousehold->users()->attach($otherUser->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $otherUser->update(['active_household_id' => $otherHousehold->id]);

        $otherAccount = Account::factory()->create([
            'household_id' => $otherHousehold->id,
            'owner_user_id' => $otherUser->id,
        ]);

        $otherTransaction = Transaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'currency_code' => 'EUR',
            'amount' => -50.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->user)->delete(route('transactions.bulk-destroy'), [
            'ids' => [$otherTransaction->id],
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('transactions', ['id' => $otherTransaction->id]);
    }

    #[Test]
    public function bulk_delete_requires_ids(): void
    {
        $response = $this->actingAs($this->user)->deleteJson(route('transactions.bulk-destroy'), []);

        $response->assertUnprocessable();
    }
}
