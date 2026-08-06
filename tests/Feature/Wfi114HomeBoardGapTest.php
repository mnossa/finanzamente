<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DashboardLayout;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaWidgetDashboardPinService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Wfi114HomeBoardGapTest extends TestCase
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
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function home_dashboard_uses_saved_layout_and_allows_edit(): void
    {
        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => [
                'widgets' => [
                    ['id' => 'lifestyle_widget', 'visible' => true, 'position' => 0, 'size' => 'xl'],
                ],
            ],
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canEditLayout', true)
                ->where('dashboardLayout.widgets.0.id', 'lifestyle_widget')
                ->has('dashboardLayout.widgets')
            );
    }

    #[Test]
    public function layout_store_on_home_succeeds(): void
    {
        $home = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'board' => $home->id,
                'config' => [
                    'widgets' => [
                        ['id' => 'financial_goals', 'visible' => true, 'position' => 0, 'size' => 'md'],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            ['financial_goals'],
            array_column($home->fresh()->config['widgets'], 'id'),
        );
    }

    #[Test]
    public function home_edit_query_starts_editing(): void
    {
        $home = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['edit' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canEditLayout', true)
                ->where('startEditing', true)
                ->where('activeBoard.id', $home->id)
            );
    }

    #[Test]
    public function custom_board_query_loads_layout_and_allows_edit(): void
    {
        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $custom = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Analisi',
            'is_home' => false,
            'sort_order' => 1,
            'config' => [
                'widgets' => [
                    ['id' => 'financial_goals', 'visible' => true, 'position' => 0, 'size' => 'md'],
                ],
            ],
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['board' => $custom->id, 'edit' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canEditLayout', true)
                ->where('startEditing', true)
                ->where('activeBoard.id', $custom->id)
                ->where('dashboardLayout.widgets.0.id', 'financial_goals')
            );
    }

    #[Test]
    public function pin_targets_home_when_no_custom_board(): void
    {
        $home = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create();

        $result = app(FormulaWidgetDashboardPinService::class)->pin($this->user, $widget);
        $this->assertSame(FormulaWidgetDashboardPinService::RESULT_PINNED, $result);
        $ids = array_column($home->fresh()->config['widgets'], 'id');
        $this->assertContains("formula_widget_{$widget->id}", $ids);
    }

    #[Test]
    public function pin_with_multiple_boards_requires_choice_then_pins_selected(): void
    {
        $home = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $custom = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Extra',
            'is_home' => false,
            'sort_order' => 1,
            'config' => ['widgets' => []],
        ]);

        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create();

        $service = app(FormulaWidgetDashboardPinService::class);
        $this->assertSame(
            FormulaWidgetDashboardPinService::RESULT_NEEDS_BOARD_CHOICE,
            $service->pin($this->user, $widget),
        );

        $this->actingAs($this->user)
            ->get(route('formula-widgets.pin.choose', $widget))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FormulaWidgets/PinToBoard')
                ->where('defaultBoardId', $home->id)
                ->has('boards', 2)
            );

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget), ['board_id' => $custom->id])
            ->assertRedirect(route('dashboard', ['board' => $custom->id]));

        $this->assertContains(
            "formula_widget_{$widget->id}",
            array_column($custom->fresh()->config['widgets'], 'id'),
        );
        $this->assertNotContains(
            "formula_widget_{$widget->id}",
            array_column($home->fresh()->config['widgets'], 'id'),
        );
    }

    #[Test]
    public function budgets_index_includes_monthly_income(): void
    {
        $currency = Currency::query()->firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => '€'],
        );

        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => $currency->code,
        ]);

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
        ]);

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $this->user->id,
            'category_id' => $incomeCategory->id,
            'amount' => 2000,
            'date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'transfer_id' => null,
        ]);

        Budget::query()->create([
            'household_id' => $this->household->id,
            'category_id' => $expenseCategory->id,
            'currency_code' => $currency->code,
            'amount' => 500,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get(route('budgets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('monthlyIncome', 2000)
            );
    }
}
