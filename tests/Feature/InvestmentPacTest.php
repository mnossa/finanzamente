<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        ]);

        Carbon::setTestNow();
    }

    #[Test]
    public function base_user_is_redirected_from_pac_create(): void
    {
        $this->user->update(['plan' => 'base', 'plan_expires_at' => null]);

        $this->actingAs($this->user)
            ->get(route('investment-pacs.create'))
            ->assertRedirect(route('profile.subscription'));
    }
}
