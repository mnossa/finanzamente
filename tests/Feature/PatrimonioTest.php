<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PatrimonioTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_completed' => true,
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_patrimonio(): void
    {
        $this->get(route('patrimonio.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function patrimonio_page_shows_liquid_and_invested_breakdown(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'bank',
            'initial_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $this->user->id,
            'amount' => -200,
            'currency_code' => 'EUR',
            'date' => now(),
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 5,
            'buy_price' => 100,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('patrimonio.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Patrimonio/Index')
                ->where('liquidValue', 300)
                ->where('investedValue', 500)
                ->where('investedLinkedValue', 500)
                ->where('investedUnlinkedValue', 0)
                ->where('totalValue', 800)
                ->has('accounts', 1)
                ->has('positionGroups', 1)
                ->where('positionMovementCount', 1)
                ->has('allocation')
            );
    }

    #[Test]
    public function patrimonio_groups_pac_movements_for_display(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'broker',
            'initial_balance' => 5000,
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'CSSPX.MI',
            'name' => 'iShares Core S&P 500',
            'currency_code' => 'EUR',
        ]);

        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'investment_asset_id' => $asset->id,
            'amount' => 60,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
        ]);

        foreach ([now()->subMonths(2), now()->subMonth()] as $buyDate) {
            Investment::create([
                'user_id' => $this->user->id,
                'household_id' => $this->household->id,
                'account_id' => $account->id,
                'asset_id' => $asset->id,
                'investment_pac_id' => $pac->id,
                'quantity' => 1,
                'buy_price' => 60,
                'buy_date' => $buyDate->toDateString(),
                'is_private' => false,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('patrimonio.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Patrimonio/Index')
                ->where('positionMovementCount', 2)
                ->has('positionGroups', 1)
                ->where('positionGroups.0.kind', 'pac')
                ->where('positionGroups.0.movement_count', 2)
                ->where('positionGroups.0.value', 120)
            );
    }

    #[Test]
    public function patrimonio_allocation_lists_instruments_per_class(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'bank',
            'initial_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'CSSPX.MI',
            'name' => 'iShares Core S&P 500 UCITS ETF',
            'currency_code' => 'EUR',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 500,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('patrimonio.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('allocation', 2)
                ->where('allocation', fn ($allocation) => collect($allocation)
                    ->contains(fn ($entry) => $entry['asset_class'] === 'equities'
                        && count($entry['instruments'] ?? []) === 1
                        && ($entry['instruments'][0]['symbol'] ?? null) === 'CSSPX.MI')
                )
            );
    }

    #[Test]
    public function dashboard_exposes_balance_breakdown(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'bank',
            'initial_balance' => 2000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'VWCE',
            'name' => 'Vanguard FTSE All-World',
            'currency_code' => 'EUR',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 2,
            'buy_price' => 150,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('balanceBreakdown.total', 1700)
                ->where('balanceBreakdown.invested', 300)
                ->where('balanceBreakdown.patrimonioTotal', 2000)
            );
    }

    #[Test]
    public function dashboard_balance_total_is_not_inflated_by_unsynced_investments(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'bank',
            'initial_balance' => 10000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => null,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 10000,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('balanceBreakdown.total', 10000)
                ->where('balanceBreakdown.invested', 10000)
            );
    }
}
