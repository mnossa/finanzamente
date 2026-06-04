<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentPacTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private InvestmentAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create([
            'plan' => 'pro',
            'plan_expires_at' => now()->addYear(),
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'isin' => 'IE00B4L5Y983',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function pro_user_can_create_monthly_pac_with_inflation(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)->post(route('investment-pacs.store'), [
            'account_id' => $account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 200,
            'adjust_for_inflation' => true,
            'inflation_rate_annual' => 2.5,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'notes' => 'PAC test',
        ]);

        $response->assertRedirect(route('investment-pacs.index'));
        $this->assertDatabaseHas('investment_pacs', [
            'household_id' => $this->household->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 200,
            'adjust_for_inflation' => true,
            'inflation_rate_annual' => 2.5,
            'frequency' => 'monthly',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function pro_user_can_create_pac_without_account_id(): void
    {
        $response = $this->actingAs($this->user)->post(route('investment-pacs.store'), [
            'account_id' => '',
            'investment_asset_id' => $this->asset->id,
            'amount' => 120,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
        ]);

        $response->assertRedirect(route('investment-pacs.index'));
        $this->assertDatabaseHas('investment_pacs', [
            'investment_asset_id' => $this->asset->id,
            'amount' => 120,
            'account_id' => null,
        ]);
    }

    #[Test]
    public function pac_creation_backfills_monthly_investments_from_start_date_to_last_useful_date(): void
    {
        Carbon::setTestNow('2026-06-20');

        $response = $this->actingAs($this->user)->post(route('investment-pacs.store'), [
            'account_id' => '',
            'investment_asset_id' => $this->asset->id,
            'amount' => 90,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-03-15',
        ]);

        $response->assertRedirect(route('investment-pacs.index'));

        $pac = InvestmentPac::query()->latest('id')->firstOrFail();
        $this->assertDatabaseCount('investments', 4);
        $this->assertDatabaseHas('investments', [
            'investment_pac_id' => $pac->id,
            'buy_date' => '2026-03-15 00:00:00',
            'buy_price' => 90,
        ]);
        $this->assertDatabaseHas('investments', [
            'investment_pac_id' => $pac->id,
            'buy_date' => '2026-06-15 00:00:00',
            'buy_price' => 90,
        ]);
        $this->assertDatabaseHas('investment_pacs', [
            'id' => $pac->id,
            'last_executed_at' => '2026-06-15 00:00:00',
        ]);

        Carbon::setTestNow();
    }

    #[Test]
    public function pac_command_applies_annual_inflation_before_monthly_run(): void
    {
        Carbon::setTestNow('2027-02-15');

        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => true,
            'inflation_rate_annual' => 10,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'last_inflation_adjusted_at' => null,
            'status' => 'active',
        ]);

        $this->artisan('investment-pacs:run')->assertSuccessful();

        $pac->refresh();
        $this->assertEquals(110.0, (float) $pac->amount);
        $this->assertEquals('2027-02-15', $pac->last_inflation_adjusted_at->format('Y-m-d'));
        $this->assertDatabaseCount('investments', 1);
        $this->assertDatabaseHas('investments', [
            'asset_id' => $this->asset->id,
            'buy_price' => 110,
            'investment_pac_id' => $pac->id,
        ]);

        Carbon::setTestNow();
    }

    #[Test]
    public function user_can_run_pac_now_manually_and_create_purchase_movement(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 250,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(route('investment-pacs.run-now', $pac));

        $response->assertRedirect();
        $this->assertDatabaseHas('investments', [
            'investment_pac_id' => $pac->id,
            'asset_id' => $this->asset->id,
            'buy_price' => 250,
        ]);
    }

    #[Test]
    public function user_can_update_pause_and_delete_pac(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
        ]);

        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->actingAs($this->user)->put(route('investment-pacs.update', $pac), [
            'account_id' => $account->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 175,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'status' => 'paused',
            'notes' => 'Aggiornato',
        ])->assertRedirect(route('investment-pacs.show', $pac));

        $this->assertDatabaseHas('investment_pacs', [
            'id' => $pac->id,
            'amount' => 175,
            'status' => 'paused',
            'notes' => 'Aggiornato',
        ]);

        $this->actingAs($this->user)
            ->post(route('investment-pacs.toggle-status', $pac))
            ->assertRedirect();

        $this->assertDatabaseHas('investment_pacs', [
            'id' => $pac->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->delete(route('investment-pacs.destroy', $pac))
            ->assertRedirect(route('investment-pacs.index'));

        $this->assertDatabaseMissing('investment_pacs', [
            'id' => $pac->id,
        ]);
    }

    #[Test]
    public function pac_update_realigns_existing_generated_movements(): void
    {
        Carbon::setTestNow('2026-03-20');

        $accountA = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
        ]);

        $accountB = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->post(route('investment-pacs.store'), [
            'account_id' => $accountA->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'fees' => 1,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-10',
            'notes' => 'Prima versione',
        ])->assertRedirect(route('investment-pacs.index'));

        $pac = InvestmentPac::query()->latest('id')->firstOrFail();
        $this->assertGreaterThan(0, Investment::where('investment_pac_id', $pac->id)->count());

        $this->actingAs($this->user)->put(route('investment-pacs.update', $pac), [
            'account_id' => $accountB->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 175,
            'fees' => 3.5,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-25',
            'status' => 'active',
            'notes' => 'PAC riallineato',
        ])->assertRedirect(route('investment-pacs.show', $pac));

        $this->assertDatabaseMissing('investments', [
            'investment_pac_id' => $pac->id,
            'buy_price' => 100,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('investments', [
            'investment_pac_id' => $pac->id,
            'account_id' => $accountB->id,
            'buy_price' => 175,
            'fees' => 3.5,
            'notes' => 'PAC automatico - PAC riallineato',
        ]);
        $this->assertDatabaseHas('investments', [
            'investment_pac_id' => $pac->id,
            'buy_date' => '2026-01-25 00:00:00',
        ]);
        $this->assertDatabaseHas('investments', [
            'investment_pac_id' => $pac->id,
            'buy_date' => '2026-02-25 00:00:00',
        ]);
        $this->assertDatabaseMissing('investments', [
            'investment_pac_id' => $pac->id,
            'buy_date' => '2026-03-10 00:00:00',
            'deleted_at' => null,
        ]);
        $this->assertSame(2, Investment::where('investment_pac_id', $pac->id)->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function pac_show_exposes_generated_movements_and_realized_result(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'asset_id' => $this->asset->id,
            'investment_pac_id' => $pac->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => '2026-01-10',
            'sell_price' => 120,
            'sell_date' => '2026-02-10',
            'fees' => 2,
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('investment-pacs.show', $pac))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('InvestmentPacs/Show')
                ->where('stats.executions_count', 1)
                ->where('stats.closed_count', 1)
                ->where('stats.realized_total', 18)
                ->has('investments', 1));
    }

    #[Test]
    public function base_user_is_redirected_from_pac_create(): void
    {
        $this->user->update(['plan' => 'base', 'plan_expires_at' => null]);

        $this->actingAs($this->user)
            ->get(route('investment-pacs.create'))
            ->assertRedirect(route('profile.subscription'));
    }

    #[Test]
    public function dashboard_asset_allocation_widget_reflects_backfilled_pac_investments(): void
    {
        Carbon::setTestNow('2026-06-20');

        $this->actingAs($this->user)->post(route('investment-pacs.store'), [
            'account_id' => '',
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-03-10',
        ])->assertRedirect(route('investment-pacs.index'));

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('assetAllocationData.total_value')
                ->where('assetAllocationData.total_value', 400));

        Carbon::setTestNow();
    }

    #[Test]
    public function investments_index_exposes_pac_metadata_for_grouped_rendering(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-03-10',
            'status' => 'active',
        ]);

        Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'asset_id' => $this->asset->id,
            'investment_pac_id' => $pac->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => '2026-03-10',
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->get(route('investments.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Investments/Index')
                ->where('investments.0.investment_pac.id', $pac->id)
                ->where('investments.0.investment_pac.status', 'active'));
    }
}
