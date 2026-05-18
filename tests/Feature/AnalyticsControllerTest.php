<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_worth_analytics_page_loads(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)
            ->get(route('analytics.net-worth'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Analytics/NetWorth'));
    }

    public function test_cash_flow_analytics_page_loads(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)
            ->get(route('analytics.cash-flow'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Analytics/CashFlow'));
    }

    public function test_expenses_by_category_respects_month_filter(): void
    {
        $user = $this->createUserWithHousehold();
        $account = Account::where('household_id', $user->active_household_id)->first();
        $category = Category::factory()->create([
            'household_id' => $user->active_household_id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -50,
            'date' => now()->startOfMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('analytics.expenses-by-category', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Analytics/ExpensesByCategory')
                ->has('expenseCategories', 1));
    }

    private function createUserWithHousehold(): User
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Test',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        return $user;
    }
}
