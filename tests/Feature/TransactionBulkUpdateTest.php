<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function bulk_update_can_assign_debt_credit_to_selected_transactions(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $debtCredit = DebtCredit::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'counterparty' => 'Mario Rossi',
            'description' => 'Prestito',
            'amount' => 500,
            'initial_amount' => 500,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $tx1 = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -50,
        ]);

        $tx2 = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -30,
        ]);

        $response = $this->actingAs($user)->patch(route('transactions.bulk-update'), [
            'ids' => [$tx1->id, $tx2->id],
            'debt_credit_id' => $debtCredit->id,
        ]);

        $response->assertRedirect(route('transactions.index'));
        $response->assertSessionHas('success');

        $this->assertSame($debtCredit->id, $tx1->fresh()->debt_credit_id);
        $this->assertSame($debtCredit->id, $tx2->fresh()->debt_credit_id);
    }
}
