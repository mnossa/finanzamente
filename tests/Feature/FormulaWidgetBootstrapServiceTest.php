<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\User;
use App\Services\FormulaWidgetBootstrapService;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_provisions_default_widgets_and_dashboard_layout_for_new_user(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $user->update(['active_household_id' => $household->id]);

        app(FormulaWidgetBootstrapService::class)->provisionForUser($user);

        $this->assertGreaterThan(0, FormulaWidget::query()->where('user_id', $user->id)->count());

        $layout = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->first();

        $this->assertNotNull($layout);
        $this->assertTrue($layout->is_home);
        $widgetIds = array_column($layout->config['widgets'], 'id');
        $this->assertSame(
            array_column(DashboardLayout::essentialConfigForUser($user)['widgets'], 'id'),
            $widgetIds,
        );

        $builtinIds = array_column(DashboardLayout::essentialConfig()['widgets'], 'id');
        $this->assertSame(
            ['active_budgets', 'expense_treemap', 'recent_transactions', 'accounts'],
            $builtinIds,
        );

        $formulaIds = collect($widgetIds)->filter(fn (string $id) => str_starts_with($id, 'formula_widget_'));
        $this->assertCount(3, $formulaIds);
        $this->assertSame(
            [...$formulaIds->all(), ...$builtinIds],
            $widgetIds,
        );
    }
}
