<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\UpcomingCashflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpcomingCashflowServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function virtual_recurring_and_pac_appear_in_upcoming_movements(): void
    {
        Carbon::setTestNow('2026-06-04');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'initial_balance' => 1000,
        ]);

        RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'description' => 'Affitto',
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        InvestmentPac::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'investment_asset_id' => $asset->id,
            'account_id' => $account->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        $movements = app(UpcomingCashflowService::class)->buildUpcomingMovements($user);

        $this->assertNotEmpty($movements);
        $this->assertTrue(collect($movements)->contains(fn ($row) => ($row['is_virtual'] ?? false) === true));
        $this->assertSame(1000.0, app(AccountBalanceService::class)->computeHouseholdTotal($user));
        $this->assertLessThan(1000.0, app(UpcomingCashflowService::class)->projectedHouseholdBalance($user));

        Carbon::setTestNow();
    }
}
