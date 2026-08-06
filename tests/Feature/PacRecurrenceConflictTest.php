<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PacRecurrenceConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
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
            'active' => true,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);
    }

    #[Test]
    public function store_rejects_monthly_recurring_that_conflicts_with_active_pac(): void
    {
        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'CONF',
            'name' => 'Conflict Asset',
            'currency_code' => 'EUR',
        ]);

        InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'investment_asset_id' => $asset->id,
            'amount' => 100,
            'fees' => 2,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => Carbon::today()->subMonth(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(route('recurring-transactions.store'), [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 102,
            'frequency' => 'monthly',
            'start_date' => Carbon::today()->toDateString(),
            'description' => 'Versamento mensile',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    #[Test]
    public function store_allows_monthly_recurring_without_pac_conflict(): void
    {
        $response = $this->actingAs($this->user)->post(route('recurring-transactions.store'), [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 45,
            'frequency' => 'monthly',
            'start_date' => Carbon::today()->toDateString(),
            'description' => 'Abbonamento palestra',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_transactions', [
            'account_id' => $this->account->id,
            'amount' => -45,
            'frequency' => 'monthly',
        ]);
    }

    #[Test]
    public function update_rejects_when_new_values_conflict_with_pac(): void
    {
        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'UPD',
            'name' => 'Update Conflict Asset',
            'currency_code' => 'EUR',
        ]);

        InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'investment_asset_id' => $asset->id,
            'amount' => 200,
            'fees' => 0,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => Carbon::today()->subMonth(),
            'status' => 'active',
        ]);

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 30,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => Carbon::today()->subMonths(2),
            'last_generated_date' => Carbon::today()->subMonth(),
        ]);

        $response = $this->actingAs($this->user)->put(route('recurring-transactions.update', $recurring), [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 200,
            'frequency' => 'monthly',
            'start_date' => Carbon::today()->subMonths(2)->toDateString(),
            'description' => 'PAC-like amount',
        ]);

        $response->assertSessionHasErrors('amount');
    }
}
