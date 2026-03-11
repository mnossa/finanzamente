<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role'        => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_cannot_access_asset_allocation(): void
    {
        $this->get(route('asset-allocation.index'))
            ->assertRedirect(route('login'));
    }

    // ── Index (empty) ─────────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_access_asset_allocation_page(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('AssetAllocation/Index')
                ->has('positions')
                ->has('allocation')
                ->has('totalValue')
                ->has('riskIndex')
                ->has('riskLabel')
        );
    }

    #[Test]
    public function empty_portfolio_returns_zero_total_and_low_risk(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('totalValue', 0)
                ->where('positions', [])
                ->where('allocation', [])
        );
    }

    // ── Investment positions ──────────────────────────────────────────────────

    #[Test]
    public function open_investments_are_included_in_positions(): void
    {
        $currency = \App\Models\Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        $asset = InvestmentAsset::create([
            'name'          => 'ACME Corp',
            'symbol'        => 'ACME',
            'type'          => 'stock',
            'currency_code' => 'EUR',
        ]);

        $account = Account::create([
            'household_id'    => $this->household->id,
            'name'            => 'Broker Test',
            'type'            => 'broker',
            'initial_balance' => 0,
            'currency_code'   => 'EUR',
            'active'          => true,
        ]);

        Investment::create([
            'user_id'      => $this->user->id,
            'household_id' => $this->household->id,
            'account_id'   => $account->id,
            'asset_id'     => $asset->id,
            'quantity'     => 10,
            'buy_price'    => 50,
            'buy_date'     => now()->subMonths(3)->toDateString(),
        ]);

        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('totalValue', 500)
                ->has('positions', 1)
                ->has('allocation', 1)
        );
    }

    #[Test]
    public function sold_investments_are_excluded(): void
    {
        $currency = \App\Models\Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        $asset = InvestmentAsset::create([
            'name' => 'SOLD Corp', 'symbol' => 'SOLD', 'type' => 'stock', 'currency_code' => 'EUR',
        ]);
        $account = Account::create([
            'household_id' => $this->household->id, 'name' => 'Broker',
            'type' => 'broker', 'initial_balance' => 0, 'currency_code' => 'EUR', 'active' => true,
        ]);

        Investment::create([
            'user_id' => $this->user->id, 'household_id' => $this->household->id,
            'account_id' => $account->id, 'asset_id' => $asset->id,
            'quantity' => 10, 'buy_price' => 50, 'buy_date' => now()->subYear()->toDateString(),
            'sell_price' => 60, 'sell_date' => now()->subMonths(2)->toDateString(),
        ]);

        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('totalValue', 0)
                ->where('positions', [])
        );
    }

    // ── Liquidity from accounts ───────────────────────────────────────────────

    #[Test]
    public function bank_account_balance_is_included_as_liquidity(): void
    {
        $currency = \App\Models\Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        Account::create([
            'household_id'    => $this->household->id,
            'name'            => 'Conto Corrente',
            'type'            => 'bank',
            'initial_balance' => 1000,
            'currency_code'   => 'EUR',
            'active'          => true,
        ]);

        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('totalValue', 1000)
                ->has('positions', 1)
        );
    }

    #[Test]
    public function accounts_with_zero_balance_are_excluded(): void
    {
        $currency = \App\Models\Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        Account::create([
            'household_id'    => $this->household->id,
            'name'            => 'Conto Vuoto',
            'type'            => 'bank',
            'initial_balance' => 0,
            'currency_code'   => 'EUR',
            'active'          => true,
        ]);

        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('totalValue', 0)
                ->where('positions', [])
        );
    }

    // ── Risk index ────────────────────────────────────────────────────────────

    #[Test]
    public function risk_index_is_1_for_pure_liquidity(): void
    {
        $currency = \App\Models\Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        Account::create([
            'household_id' => $this->household->id, 'name' => 'Banca',
            'type' => 'bank', 'initial_balance' => 5000, 'currency_code' => 'EUR', 'active' => true,
        ]);

        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('riskIndex', 1)
                ->where('riskLabel', 'Molto Basso')
        );
    }

    #[Test]
    public function risk_index_is_7_for_pure_crypto(): void
    {
        $currency = \App\Models\Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        $asset = InvestmentAsset::create([
            'name' => 'Bitcoin', 'symbol' => 'BTC', 'type' => 'crypto', 'currency_code' => 'EUR',
        ]);
        $account = Account::create([
            'household_id' => $this->household->id, 'name' => 'Wallet',
            'type' => 'broker', 'initial_balance' => 0, 'currency_code' => 'EUR', 'active' => true,
        ]);

        Investment::create([
            'user_id' => $this->user->id, 'household_id' => $this->household->id,
            'account_id' => $account->id, 'asset_id' => $asset->id,
            'quantity' => 1, 'buy_price' => 10000, 'buy_date' => now()->subYear()->toDateString(),
        ]);

        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('asset-allocation.index'));

        $response->assertInertia(fn ($page) =>
            $page->where('riskIndex', 7)
                ->where('riskLabel', 'Molto Alto')
        );
    }

    // ── Widget endpoint ───────────────────────────────────────────────────────

    #[Test]
    public function widget_endpoint_returns_json_summary(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('asset-allocation.widget'));

        $response->assertOk();
        $response->assertJsonStructure([
            'total_value',
            'risk_index',
            'risk_label',
            'allocation',
        ]);
    }

    // ── Dashboard includes assetAllocationData ────────────────────────────────

    #[Test]
    public function dashboard_page_includes_asset_allocation_data(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('assetAllocationData')
                ->has('assetAllocationData.total_value')
                ->has('assetAllocationData.risk_index')
                ->has('assetAllocationData.risk_label')
                ->has('assetAllocationData.allocation')
        );
    }

    // ── Default layout includes asset_allocation widget ───────────────────────

    #[Test]
    public function default_layout_includes_asset_allocation_widget(): void
    {
        $defaultConfig = \App\Models\DashboardLayout::defaultConfig();
        $widgetIds = array_column($defaultConfig['widgets'], 'id');

        $this->assertContains('asset_allocation', $widgetIds);
    }
}
