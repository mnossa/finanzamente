<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentTransactionSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentTransactionSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private InvestmentAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

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
            'currency_code' => 'EUR',
            'initial_balance' => 5000,
        ]);

        $this->asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'isin' => 'IE00B4L5Y983',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function sync_purchase_creates_expense_transaction_with_investment_category(): void
    {
        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 2,
            'buy_price' => 100,
            'buy_date' => '2026-03-10',
            'fees' => 1.5,
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $this->assertDatabaseHas('transactions', [
            'investment_id' => $investment->id,
            'account_id' => $this->account->id,
            'amount' => -201.5,
        ]);

        $category = Category::where('household_id', $this->household->id)
            ->where('name', 'Investimenti')
            ->where('type', 'expense')
            ->first();

        $this->assertNotNull($category);
        $this->assertSame('investments', $category->expense_distribution);
        $this->assertTrue($category->exclude_from_lifestyle_score);
    }

    #[Test]
    public function pac_with_account_syncs_transactions_on_backfill(): void
    {
        Carbon::setTestNow('2026-03-20');

        $this->actingAs($this->user)->post(route('investment-pacs.store'), [
            'account_id' => $this->account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'fees' => 2,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-10',
        ])->assertRedirect(route('investment-pacs.index'));

        $investmentCount = Investment::count();
        $this->assertGreaterThan(0, $investmentCount);
        $this->assertSame($investmentCount, Transaction::whereNotNull('investment_id')->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function investment_purchase_updates_expense_distribution_and_not_lifestyle_score(): void
    {
        Carbon::setTestNow('2026-06-10');

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'name' => 'Stipendio',
        ]);
        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
            'name' => 'Spese',
            'exclude_from_lifestyle_score' => false,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $incomeCategory->id,
            'amount' => 2000,
            'date' => now()->startOfMonth(),
            'currency_code' => 'EUR',
        ]);
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $expenseCategory->id,
            'amount' => -400,
            'date' => now()->startOfMonth(),
            'currency_code' => 'EUR',
        ]);

        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 300,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($this->user)->post(route('investment-pacs.run-now', $pac));

        $this->actingAs($this->user)
            ->getJson(route('dashboard.deferred-widgets'))
            ->assertOk()
            ->assertJsonPath('expenseDistributionData.investments.amount', 300);

        $this->actingAs($this->user)
            ->get('/punteggio-stile-vita')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metrics.total_expenses', 700)
                ->where('metrics.excluded_expenses', 300)
                ->where('metrics.effective_expenses', 400)
                ->where('metrics.lifestyle_score', 80)
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function sync_transactions_command_backfills_missing_links(): void
    {
        $investment = Investment::withoutEvents(fn () => Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 250,
            'buy_date' => '2026-02-01',
            'is_private' => false,
        ]));

        $this->assertDatabaseMissing('transactions', [
            'investment_id' => $investment->id,
        ]);

        $this->artisan('investment-pacs:sync-transactions')->assertSuccessful();

        $this->assertDatabaseHas('transactions', [
            'investment_id' => $investment->id,
            'amount' => -250,
        ]);
    }

    #[Test]
    public function transactions_index_marks_investment_rows(): void
    {
        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $this->actingAs($this->user)
            ->get(route('transactions.index', ['type' => 'investment']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.investment_id', $investment->id)
                ->where('transactions.data.0.is_investment', true)
                ->where('transactions.data.0.is_pac', false)
                ->where('transactions.data.0.pac_summary', null)
            );
    }

    #[Test]
    public function transactions_index_marks_pac_rows(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 75,
            'fees' => 1,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'investment_pac_id' => $pac->id,
            'quantity' => 1,
            'buy_price' => 75,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);

        $this->actingAs($this->user)
            ->get(route('transactions.index', ['type' => 'investment']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.is_pac', true)
                ->where('transactions.data.0.pac_summary.id', $pac->id)
                ->where('transactions.data.0.pac_summary.asset_name', $this->asset->name)
            );
    }

    #[Test]
    public function expense_distribution_includes_investment_movements_without_linked_transaction(): void
    {
        Carbon::setTestNow('2026-06-10');

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => null,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 500,
            'fees' => 5,
            'buy_date' => now()->startOfMonth()->addDays(5)->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->getJson(route('dashboard.deferred-widgets'))
            ->assertOk()
            ->assertJsonPath('expenseDistributionData.investments.amount', 505);

        Carbon::setTestNow();
    }

    #[Test]
    public function updating_pac_transaction_date_syncs_investment_and_pac_last_executed_at(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-08',
            'last_executed_at' => '2026-06-08',
            'status' => 'active',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'investment_pac_id' => $pac->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => '2026-07-01',
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);
        $transaction = Transaction::where('investment_id', $investment->id)->firstOrFail();

        $this->actingAs($this->user)->patch(route('transactions.update', $transaction), [
            'account_id' => $this->account->id,
            'category_id' => $transaction->category_id,
            'amount' => 100,
            'date' => '2026-07-08',
            'description' => $transaction->description,
            'is_private' => false,
            'is_tax_deductible' => false,
            'tag_ids' => [],
            'new_tag_names' => [],
        ])->assertRedirect(route('transactions.index'));

        $investment->refresh();
        $pac->refresh();
        $transaction->refresh();

        $this->assertSame('2026-07-08', $investment->buy_date->format('Y-m-d'));
        $this->assertSame('2026-07-08', $transaction->date->format('Y-m-d'));
        $this->assertSame('2026-07-08', $pac->last_executed_at->format('Y-m-d'));
    }

    #[Test]
    public function updating_manual_investment_transaction_date_syncs_investment_buy_date(): void
    {
        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 250,
            'buy_date' => '2026-02-01',
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);
        $transaction = Transaction::where('investment_id', $investment->id)->firstOrFail();

        $this->actingAs($this->user)->patch(route('transactions.update', $transaction), [
            'account_id' => $this->account->id,
            'category_id' => $transaction->category_id,
            'amount' => 250,
            'date' => '2026-02-15',
            'description' => $transaction->description,
            'is_private' => false,
            'is_tax_deductible' => false,
            'tag_ids' => [],
            'new_tag_names' => [],
        ])->assertRedirect(route('transactions.index'));

        $this->assertSame('2026-02-15', $investment->fresh()->buy_date->format('Y-m-d'));
    }

    #[Test]
    public function bulk_update_date_syncs_pac_linked_investment(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 80,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-03-08',
            'status' => 'active',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'investment_pac_id' => $pac->id,
            'quantity' => 1,
            'buy_price' => 80,
            'buy_date' => '2026-04-01',
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);
        $transaction = Transaction::where('investment_id', $investment->id)->firstOrFail();

        $plainTx = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'amount' => -20,
            'date' => '2026-04-01',
        ]);

        $this->actingAs($this->user)->patch(route('transactions.bulk-update'), [
            'ids' => [$transaction->id, $plainTx->id],
            'date' => '2026-04-08',
        ])->assertRedirect(route('transactions.index'));

        $this->assertSame('2026-04-08', $investment->fresh()->buy_date->format('Y-m-d'));
        $this->assertSame('2026-04-08', $plainTx->fresh()->date->format('Y-m-d'));
        $this->assertSame('2026-04-08', $pac->fresh()->last_executed_at->format('Y-m-d'));
    }

    #[Test]
    public function sync_buy_date_from_transaction_ignores_sale_ledger_rows(): void
    {
        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => '2026-01-10',
            'sell_price' => 120,
            'sell_date' => '2026-06-10',
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncInvestment($investment);

        $saleTransaction = Transaction::where('investment_id', $investment->id)
            ->where('amount', '>', 0)
            ->firstOrFail();
        $saleTransaction->update(['date' => '2026-06-15']);

        app(InvestmentTransactionSyncService::class)->syncBuyDateFromTransaction($saleTransaction->fresh());

        $this->assertSame('2026-06-10', $investment->fresh()->sell_date->format('Y-m-d'));
    }
}
