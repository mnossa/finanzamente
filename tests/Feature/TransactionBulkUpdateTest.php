<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\Tag;
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

    #[Test]
    public function bulk_update_can_replace_tags_for_selected_transactions(): void
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

        $existingTag = Tag::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'name' => 'Casa',
            'color' => '#6366f1',
        ]);

        $response = $this->actingAs($user)->patch(route('transactions.bulk-update'), [
            'ids' => [$tx1->id, $tx2->id],
            'tag_ids' => [$existingTag->id],
            'new_tag_names' => ['Auto'],
        ]);

        $response->assertRedirect(route('transactions.index'));
        $autoTag = Tag::where('household_id', $household->id)->where('name', 'AUTO')->first();
        $this->assertNotNull($autoTag);

        $this->assertEqualsCanonicalizing(
            [$existingTag->id, $autoTag->id],
            $tx1->fresh()->tags()->pluck('tags.id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$existingTag->id, $autoTag->id],
            $tx2->fresh()->tags()->pluck('tags.id')->all()
        );
    }

    #[Test]
    public function bulk_update_can_change_date_for_selected_transactions(): void
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

        $tx1 = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -50,
            'date' => '2026-03-01',
        ]);

        $tx2 = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -30,
            'date' => '2026-03-01',
        ]);

        $response = $this->actingAs($user)->patch(route('transactions.bulk-update'), [
            'ids' => [$tx1->id, $tx2->id],
            'date' => '2026-05-15',
        ]);

        $response->assertRedirect(route('transactions.index'));
        $this->assertSame('2026-05-15', $tx1->fresh()->date->format('Y-m-d'));
        $this->assertSame('2026-05-15', $tx2->fresh()->date->format('Y-m-d'));
    }

    #[Test]
    public function bulk_update_recalculates_tax_year_when_date_changes_for_deductible_transactions(): void
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

        $tx = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -50,
            'date' => '2025-11-20',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2025,
        ]);

        $this->actingAs($user)->patch(route('transactions.bulk-update'), [
            'ids' => [$tx->id],
            'date' => '2026-02-10',
        ])->assertRedirect(route('transactions.index'));

        $tx->refresh();
        $this->assertSame('2026-02-10', $tx->date->format('Y-m-d'));
        $this->assertSame(2026, $tx->tax_year);
    }
}
