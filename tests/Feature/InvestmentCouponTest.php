<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentTransactionSyncService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentCouponTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private InvestmentAsset $asset;

    private Investment $investment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_completed' => true,
            'plan' => 'pro',
            'plan_expires_at' => now()->addYear(),
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
            'initial_balance' => 10000,
        ]);

        $this->asset = InvestmentAsset::query()->create([
            'type' => 'other',
            'symbol' => 'IT0000000001',
            'isin' => 'IT0000000001',
            'name' => 'BTP Valore Test',
            'currency_code' => 'EUR',
        ]);

        $this->investment = Investment::query()->create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $this->asset->id,
            'quantity' => 1,
            'buy_price' => 1000,
            'buy_date' => '2025-01-15',
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($this->investment);
    }

    #[Test]
    public function store_coupon_creates_income_transaction_linked_to_investment(): void
    {
        $this->actingAs($this->user)
            ->post(route('investments.coupons.store', $this->investment), [
                'amount' => 17.50,
                'date' => '2025-05-15',
                'description' => 'Cedola maggio',
                'account_id' => $this->account->id,
            ])
            ->assertRedirect(route('investments.show', $this->investment));

        $this->assertDatabaseHas('transactions', [
            'investment_id' => $this->investment->id,
            'investment_event' => 'coupon',
            'amount' => 17.50,
            'description' => 'Cedola maggio',
            'account_id' => $this->account->id,
        ]);

        $category = Category::query()
            ->where('household_id', $this->household->id)
            ->where('name', 'Cedole e dividendi')
            ->where('type', 'income')
            ->first();

        $this->assertNotNull($category);
    }

    #[Test]
    public function sync_sale_does_not_overwrite_coupon_transactions(): void
    {
        $coupon = Transaction::query()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => Category::factory()->create([
                'household_id' => $this->household->id,
                'type' => 'income',
                'name' => 'Cedole e dividendi',
            ])->id,
            'amount' => 20,
            'currency_code' => 'EUR',
            'date' => '2025-06-01',
            'description' => 'Cedola',
            'investment_id' => $this->investment->id,
            'investment_event' => 'coupon',
            'is_private' => false,
        ]);

        $this->investment->update([
            'sell_price' => 1050,
            'sell_date' => '2025-12-01',
        ]);

        app(InvestmentTransactionSyncService::class)->syncSale($this->investment->fresh());

        $this->assertDatabaseHas('transactions', [
            'id' => $coupon->id,
            'investment_event' => 'coupon',
            'amount' => 20,
            'description' => 'Cedola',
        ]);

        $this->assertDatabaseHas('transactions', [
            'investment_id' => $this->investment->id,
            'investment_event' => 'sale',
            'amount' => 1050,
        ]);
    }

    #[Test]
    public function show_includes_coupons_total_and_schedule(): void
    {
        Transaction::query()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => Category::factory()->create([
                'household_id' => $this->household->id,
                'type' => 'income',
                'name' => 'Cedole e dividendi',
            ])->id,
            'amount' => 12.5,
            'currency_code' => 'EUR',
            'date' => '2025-05-15',
            'description' => 'Cedola',
            'investment_id' => $this->investment->id,
            'investment_event' => 'coupon',
            'is_private' => false,
        ]);

        $this->asset->update([
            'coupon_frequency' => 'semi_annual',
            'next_coupon_date' => '2026-05-15',
            'coupon_rate_percent' => 3.5,
        ]);

        $this->actingAs($this->user)
            ->get(route('investments.show', $this->investment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Investments/Show')
                ->where('investment.coupons_total', 12.5)
                ->has('coupons', 1)
                ->where('couponSchedule.frequency', 'semi_annual')
                ->has('couponSchedule.next_dates', 6)
                ->where('couponSchedule.next_dates.0', '2026-05-15')
                ->where('couponSchedule.next_dates.1', '2026-11-15')
                ->where('couponSchedule.next_items.0.rate_percent', 3.5)
                ->has('accounts')
            );
    }

    #[Test]
    public function update_coupon_schedule_persists_on_asset(): void
    {
        $this->actingAs($this->user)
            ->put(route('investments.coupons.schedule', $this->investment), [
                'coupon_frequency' => 'annual',
                'next_coupon_date' => '2026-11-01',
                'coupon_rate_percent' => 4.25,
                'coupon_rate_steps' => [],
            ])
            ->assertRedirect(route('investments.show', $this->investment));

        $this->asset->refresh();
        $this->assertSame('annual', $this->asset->coupon_frequency);
        $this->assertSame('2026-11-01', $this->asset->next_coupon_date?->format('Y-m-d'));
        $this->assertEquals(4.25, (float) $this->asset->coupon_rate_percent);
        $this->assertNull($this->asset->coupon_rate_steps);
    }

    #[Test]
    public function update_coupon_schedule_with_step_up_rates(): void
    {
        $this->actingAs($this->user)
            ->put(route('investments.coupons.schedule', $this->investment), [
                'coupon_frequency' => 'semi_annual',
                'next_coupon_date' => '2026-05-15',
                'coupon_rate_percent' => null,
                'coupon_rate_steps' => [3.25, 3.5, 4.0],
            ])
            ->assertRedirect(route('investments.show', $this->investment));

        $this->asset->refresh();
        $this->assertEquals([
            ['from' => null, 'rate' => 3.25],
            ['from' => null, 'rate' => 3.5],
            ['from' => null, 'rate' => 4.0],
        ], $this->asset->coupon_rate_steps);
        $this->assertNull($this->asset->coupon_rate_percent);

        $this->actingAs($this->user)
            ->get(route('investments.show', $this->investment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('couponSchedule.is_step_up', true)
                ->where('couponSchedule.next_items.0.rate_percent', 3.25)
                ->where('couponSchedule.next_items.1.rate_percent', 3.5)
                ->where('couponSchedule.next_items.2.rate_percent', 4)
                ->where('couponSchedule.next_items.3.rate_percent', 4)
            );
    }

    #[Test]
    public function update_coupon_schedule_with_dated_step_up_rates(): void
    {
        $this->actingAs($this->user)
            ->put(route('investments.coupons.schedule', $this->investment), [
                'coupon_frequency' => 'semi_annual',
                'next_coupon_date' => '2026-05-15',
                'coupon_rate_percent' => null,
                'coupon_rate_steps' => [
                    ['from' => '2026-05-15', 'rate' => 3.25],
                    ['from' => '2027-05-15', 'rate' => 4.0],
                ],
                'income_policy' => 'distributing',
            ])
            ->assertRedirect(route('investments.show', $this->investment));

        $this->asset->refresh();
        $this->assertSame('distributing', $this->asset->income_policy);
        $this->assertEquals([
            ['from' => '2026-05-15', 'rate' => 3.25],
            ['from' => '2027-05-15', 'rate' => 4.0],
        ], $this->asset->coupon_rate_steps);

        $this->actingAs($this->user)
            ->get(route('investments.show', $this->investment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('couponSchedule.has_dated_steps', true)
                ->where('couponSchedule.next_items.0.rate_percent', 3.25)
                ->where('couponSchedule.next_items.1.rate_percent', 3.25)
                ->where('couponSchedule.next_items.2.rate_percent', 4)
                ->where('investment.asset.income_policy', 'distributing')
            );
    }

    #[Test]
    public function store_asset_persists_income_policy_for_etf(): void
    {
        $this->actingAs($this->user)
            ->post(route('investment-assets.store'), [
                'type' => 'etf',
                'name' => 'VWCE Accumulating',
                'currency_code' => 'EUR',
                'income_policy' => 'accumulating',
            ])
            ->assertRedirect(route('investment-assets.index'));

        $this->assertDatabaseHas('investment_assets', [
            'name' => 'VWCE Accumulating',
            'type' => 'etf',
            'income_policy' => 'accumulating',
        ]);
    }

    #[Test]
    public function destroy_coupon_schedule_clears_asset_fields(): void
    {
        $this->asset->update([
            'coupon_frequency' => 'annual',
            'next_coupon_date' => '2026-11-01',
            'coupon_rate_percent' => 4.0,
            'coupon_rate_steps' => [
                ['from' => null, 'rate' => 3.0],
                ['from' => null, 'rate' => 4.0],
            ],
        ]);

        $this->actingAs($this->user)
            ->delete(route('investments.coupons.schedule.destroy', $this->investment))
            ->assertRedirect(route('investments.show', $this->investment));

        $this->asset->refresh();
        $this->assertNull($this->asset->coupon_frequency);
        $this->assertNull($this->asset->next_coupon_date);
        $this->assertNull($this->asset->coupon_rate_percent);
        $this->assertNull($this->asset->coupon_rate_steps);
    }

    #[Test]
    public function destroy_coupon_removes_only_that_transaction(): void
    {
        $coupon = Transaction::query()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => Category::factory()->create([
                'household_id' => $this->household->id,
                'type' => 'income',
                'name' => 'Cedole e dividendi',
            ])->id,
            'amount' => 10,
            'currency_code' => 'EUR',
            'date' => '2025-05-15',
            'description' => 'Cedola',
            'investment_id' => $this->investment->id,
            'investment_event' => 'coupon',
            'is_private' => false,
        ]);

        $this->actingAs($this->user)
            ->delete(route('investments.coupons.destroy', [$this->investment, $coupon]))
            ->assertRedirect(route('investments.show', $this->investment));

        $this->assertSoftDeleted('transactions', ['id' => $coupon->id]);
        $this->assertDatabaseHas('transactions', [
            'investment_id' => $this->investment->id,
            'investment_event' => 'purchase',
            'deleted_at' => null,
        ]);
    }
}
