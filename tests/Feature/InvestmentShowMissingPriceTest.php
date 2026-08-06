<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentShowMissingPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    #[Test]
    public function show_returns_ok_when_market_price_unavailable_for_btp(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::query()->create([
            'name' => 'BTP VALORE test',
            'symbol' => 'IT0005672024',
            'type' => 'other',
            'currency_code' => 'EUR',
        ]);

        $investment = Investment::query()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => now()->subMonth()->toDateString(),
            'is_private' => false,
        ]);

        // Market price APIs removed: open positions without a manual quote stay null.
        $this->actingAs($user)
            ->get(route('investments.show', $investment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Investments/Show')
                ->where('investment.current_price', null)
                ->where('investment.current_value', null)
                ->where('investment.unrealized_profit', null)
            );
    }

    #[Test]
    public function show_survives_without_market_price_provider(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::query()->create([
            'name' => 'BTP fragile quote',
            'symbol' => 'IT0005672024',
            'type' => 'other',
            'currency_code' => 'EUR',
        ]);

        $investment = Investment::query()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => now()->subMonth()->toDateString(),
            'is_private' => false,
        ]);

        $this->actingAs($user)
            ->get(route('investments.show', $investment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Investments/Show')
                ->where('investment.current_price', null)
            );
    }
}
