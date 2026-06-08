<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardAnalyticsService;
use App\Services\InvestmentTransactionSyncService;
use App\Services\PortfolioSnapshotService;
use App\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifica coerenza tra ledger transazioni, investimenti e widget dashboard.
 */
class FinancialConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private InvestmentAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'user_type' => 'persona',
            'email_verified_at' => now(),
            'profile_completed' => true,
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'bank',
            'initial_balance' => 5000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $this->asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function dashboard_total_is_ledger_only_invested_is_breakdown_not_addendum(): void
    {
        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => null,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 2000,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 5000)
                ->where('balanceBreakdown.total', 5000)
                ->where('balanceBreakdown.invested', 2000)
            );
    }

    #[Test]
    public function synced_investment_reduces_cash_and_appears_in_breakdown(): void
    {
        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 10,
            'buy_price' => 100,
            'fees' => 5,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 3995)
                ->where('balanceBreakdown.total', 3995)
                ->where('balanceBreakdown.invested', 1005)
            );
    }

    #[Test]
    public function expense_distribution_does_not_double_count_synced_investment(): void
    {
        Carbon::setTestNow('2026-06-15');

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 400,
            'fees' => 10,
            'buy_date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('expenseDistributionData.investments.amount', 410)
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function lifestyle_excludes_synced_investment_but_expense_distribution_includes_it(): void
    {
        Carbon::setTestNow('2026-06-15');

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'name' => 'Stipendio',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $incomeCategory->id,
            'amount' => 3000,
            'date' => now()->startOfMonth(),
            'currency_code' => 'EUR',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 600,
            'buy_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'is_private' => false,
        ]);
        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('expenseDistributionData.investments.amount', 600)
            );

        $this->actingAs($this->user)
            ->get('/punteggio-stile-vita')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.total_expenses', 600)
                ->where('metrics.excluded_expenses', 600)
                ->where('metrics.effective_expenses', 0)
                ->where('metrics.lifestyle_score', 100)
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function unsynced_investment_affects_expense_distribution_and_lifestyle_as_excluded(): void
    {
        Carbon::setTestNow('2026-06-15');

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'name' => 'Stipendio',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $incomeCategory->id,
            'amount' => 2000,
            'date' => now()->startOfMonth(),
            'currency_code' => 'EUR',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => null,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 800,
            'buy_date' => now()->startOfMonth()->addDays(4)->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('expenseDistributionData.investments.amount', 800)
            );

        $this->actingAs($this->user)
            ->get('/punteggio-stile-vita')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.total_expenses', 800)
                ->where('metrics.excluded_expenses', 800)
                ->where('metrics.effective_expenses', 0)
                ->where('metrics.lifestyle_score', 100)
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function patrimonio_total_excludes_unsynced_investments_from_total(): void
    {
        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => null,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 1500,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $snapshot = app(PortfolioSnapshotService::class)->build($this->user);

        $this->assertSame(5000.0, $snapshot['liquidValue']);
        $this->assertSame(1500.0, $snapshot['investedValue']);
        $this->assertSame(0.0, $snapshot['investedLinkedValue']);
        $this->assertSame(1500.0, $snapshot['investedUnlinkedValue']);
        $this->assertSame(5000.0, $snapshot['totalValue']);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('balanceBreakdown.total', 5000)
            );
    }

    #[Test]
    public function broker_cash_included_in_dashboard_and_patrimonio_liquid(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'broker',
            'name' => 'Broker E2E',
            'initial_balance' => 3000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $snapshot = app(PortfolioSnapshotService::class)->build($this->user);

        $this->assertSame(8000.0, $snapshot['liquidValue']);
        $this->assertSame(0.0, $snapshot['investedValue']);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 8000)
            );
    }

    #[Test]
    public function net_worth_series_portfolio_mode_includes_linked_investments(): void
    {
        Carbon::setTestNow('2026-06-15');

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 1000,
            'buy_date' => now()->startOfMonth()->toDateString(),
            'is_private' => false,
        ]);
        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $series = app(DashboardAnalyticsService::class)->getNetWorthSeries(
            $this->household->id,
            $this->user->id,
            Carbon::now()->subMonths(2)->startOfMonth(),
        );

        $lastPoint = end($series);
        $this->assertSame(5000.0, $lastPoint['Patrimonio']);

        $snapshot = app(PortfolioSnapshotService::class)->build($this->user);
        $this->assertSame(4000.0, $snapshot['liquidValue']);
        $this->assertSame(1000.0, $snapshot['investedValue']);
        $this->assertSame(5000.0, $snapshot['totalValue']);

        Carbon::setTestNow();
    }

    #[Test]
    public function period_stats_exclude_internal_transfers(): void
    {
        Carbon::setTestNow('2026-06-15');

        $destAccount = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'cash',
            'initial_balance' => 0,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
            'name' => 'Trasferimento uscita',
        ]);
        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'name' => 'Trasferimento entrata',
        ]);

        app(TransferService::class)->createTransfer([
            'source_account_id' => $this->account->id,
            'destination_account_id' => $destAccount->id,
            'source_amount' => 500,
            'source_currency' => 'EUR',
            'dest_currency' => 'EUR',
            'source_category_id' => $expenseCategory->id,
            'dest_category_id' => $incomeCategory->id,
            'date' => now()->subDays(3)->toDateString(),
            'initiated_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('periodStats.income', 0)
                ->where('periodStats.expenses', 0)
                ->where('periodStats.net', 0)
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function patrimonio_liquid_aligns_with_dashboard_when_account_is_negative(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'type' => 'cash',
            'name' => 'Contanti negativi',
            'initial_balance' => -1000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 500,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);
        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $snapshot = app(PortfolioSnapshotService::class)->build($this->user);

        $this->assertSame(3500.0, $snapshot['liquidValue']);
        $this->assertSame(4000.0, $snapshot['totalValue']);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 3500)
                ->where('balanceBreakdown.total', 3500)
                ->where('balanceBreakdown.patrimonioTotal', 4000)
            );

        $this->actingAs($this->user)
            ->get(route('patrimonio.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('liquidValue', 3500)
                ->where('totalValue', 4000)
                ->has('accounts', 2)
            );

        $series = app(DashboardAnalyticsService::class)->getNetWorthSeries(
            $this->household->id,
            $this->user->id,
            Carbon::now()->subMonths(2)->startOfMonth(),
        );

        $lastPoint = end($series);
        $this->assertSame(4000.0, $lastPoint['Patrimonio']);
    }

    #[Test]
    public function dashboard_total_recomputes_from_raw_transaction_sum(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -250,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 4750)
            );
    }

    #[Test]
    public function accounts_and_dashboard_use_same_computed_balance(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -250,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $this->account->refresh();

        $this->actingAs($this->user)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 4750)
            );

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 4750)
            );
    }
}
