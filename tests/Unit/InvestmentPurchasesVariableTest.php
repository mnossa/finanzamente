<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Services\InvestmentLedgerService;
use App\Services\InvestmentTransactionSyncService;
use App\Services\SystemVariableResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentPurchasesVariableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function investment_purchases_includes_synced_and_unsynced_buys_in_period(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create(['plan' => 'pro']);
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->for($household)->for($user, 'owner')->create([
            'initial_balance' => 10000,
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'World ETF',
            'currency_code' => 'EUR',
        ]);

        $synced = Investment::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'buy_price' => 100,
            'fees' => 5,
            'buy_date' => '2026-06-10',
            'is_private' => false,
        ]);
        app(InvestmentTransactionSyncService::class)->syncPurchase($synced);

        Investment::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'account_id' => null,
            'asset_id' => $asset->id,
            'quantity' => 2,
            'buy_price' => 50,
            'fees' => 0,
            'buy_date' => '2026-06-12',
            'is_private' => false,
        ]);

        // Fuori periodo: non deve entrare.
        Investment::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'account_id' => null,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 999,
            'buy_date' => '2026-05-01',
            'is_private' => false,
        ]);

        $ledger = app(InvestmentLedgerService::class);
        $all = $ledger->purchasesInPeriod(
            $user,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30'),
        );
        $unsynced = $ledger->unsyncedPurchasesInPeriod(
            $user,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30'),
        );

        $this->assertSame(1105.0, $all['amount']); // 1000+5 + 100
        $this->assertSame(100.0, $unsynced['amount']);

        $resolved = app(SystemVariableResolver::class)->resolve(
            $user,
            'investment_purchases',
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30'),
        );
        $this->assertSame(1105.0, $resolved);

        Carbon::setTestNow();
    }
}
