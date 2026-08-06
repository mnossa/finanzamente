<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaWidgetDataVersionService;
use App\Services\TransferService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferTransactionDateSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function updating_transfer_leg_date_syncs_linked_transaction_and_bumps_widget_data_version(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_completed' => true,
        ]);
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $source = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'currency_code' => 'EUR',
            'initial_balance' => 1000,
            'active' => true,
        ]);
        $destination = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'currency_code' => 'EUR',
            'initial_balance' => 0,
            'active' => true,
        ]);

        $expenseCategory = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'name' => 'Uscita trasferimento',
        ]);
        $incomeCategory = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
            'name' => 'Entrata trasferimento',
        ]);

        $transfer = app(TransferService::class)->createTransfer([
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'source_amount' => 120,
            'source_currency' => 'EUR',
            'dest_currency' => 'EUR',
            'source_category_id' => $expenseCategory->id,
            'dest_category_id' => $incomeCategory->id,
            'date' => '2026-05-10',
            'initiated_by' => $user->id,
            'description' => 'Sposta liquidità',
        ]);

        $legs = Transaction::query()->where('transfer_id', $transfer->id)->orderBy('id')->get();
        $this->assertCount(2, $legs);
        $this->assertTrue($legs->every(fn (Transaction $tx) => $tx->date->format('Y-m-d') === '2026-05-10'));

        $sourceLeg = $legs->firstWhere('account_id', $source->id);
        $destLeg = $legs->firstWhere('account_id', $destination->id);
        $this->assertNotNull($sourceLeg);
        $this->assertNotNull($destLeg);

        $versionBefore = app(FormulaWidgetDataVersionService::class)->resolveForUser($user);

        $response = $this->actingAs($user)->patch("/transazioni/{$sourceLeg->id}", [
            'account_id' => $source->id,
            'category_id' => $expenseCategory->id,
            'amount' => 120,
            'date' => '2026-06-01',
            'description' => 'Sposta liquidità',
            'is_private' => false,
        ]);

        $response->assertRedirect();

        $sourceLeg->refresh();
        $destLeg->refresh();

        $this->assertSame('2026-06-01', $sourceLeg->date->format('Y-m-d'));
        $this->assertSame('2026-06-01', $destLeg->date->format('Y-m-d'));

        $versionAfter = app(FormulaWidgetDataVersionService::class)->resolveForUser($user->fresh());
        $this->assertNotSame($versionBefore, $versionAfter);
    }
}
