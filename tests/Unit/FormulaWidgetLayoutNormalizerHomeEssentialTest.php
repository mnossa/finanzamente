<?php

namespace Tests\Unit;

use App\Models\DashboardLayout;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FormulaWidgetLayoutNormalizer;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetLayoutNormalizerHomeEssentialTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function build_home_essential_config_orders_kpi_then_builtins(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $user = User::factory()->create();

        foreach (['official.saldo_liquidita', 'official.entrate_30gg', 'official.uscite_30gg', 'official.cashflow_mensile'] as $slug) {
            $template = FormulaWidget::query()
                ->where('template_slug', $slug)
                ->where('is_official_template', true)
                ->firstOrFail();

            $variable = FinancialVariable::factory()->for($user)->formula('[household_balance]')->create();
            FormulaWidget::factory()
                ->for($user)
                ->for($variable, 'financialVariable')
                ->create([
                    'source_id' => $template->id,
                    'default_size' => 'sm',
                    'period_preset' => null,
                    'chart_config' => ['format' => 'currency'],
                ]);
        }

        $config = app(FormulaWidgetLayoutNormalizer::class)->buildHomeEssentialConfig($user);
        $ids = array_column($config['widgets'], 'id');

        $this->assertCount(7, $ids);
        $this->assertTrue(str_starts_with($ids[0], 'formula_widget_'));
        $this->assertTrue(str_starts_with($ids[1], 'formula_widget_'));
        $this->assertTrue(str_starts_with($ids[2], 'formula_widget_'));
        $this->assertSame(
            ['active_budgets', 'expense_treemap', 'recent_transactions', 'accounts'],
            array_slice($ids, 3),
        );
        $this->assertSame('xl', $config['widgets'][0]['size']);
        $this->assertSame('md', $config['widgets'][1]['size']);
        $this->assertSame('md', $config['widgets'][2]['size']);
        $this->assertSame('md', $config['widgets'][3]['size']);

        $cashflowId = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereHas('source', fn ($q) => $q->where('template_slug', 'official.cashflow_mensile'))
            ->value('id');

        $this->assertNotNull($cashflowId);
        $this->assertNotContains("formula_widget_{$cashflowId}", $ids);
    }

    #[Test]
    public function is_bare_essential_config_detects_builtin_only_template(): void
    {
        $this->assertTrue(DashboardLayout::isBareEssentialConfig(DashboardLayout::essentialConfig()));
        $this->assertFalse(DashboardLayout::isBareEssentialConfig([
            'widgets' => [
                ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'md'],
            ],
        ]));
    }
}
