<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaWidgetPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private FormulaWidgetPayloadBuilder $builder;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = app(FormulaWidgetPayloadBuilder::class);
        $this->user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 500,
            'date' => now(),
            'description' => 'Entrata',
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function it_builds_kpi_payload(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create([
            'code' => 'saldo',
            'name' => 'Saldo',
        ]);

        $widget = FormulaWidget::factory()->for($this->user)->for($variable, 'financialVariable')->create([
            'name' => 'Saldo',
            'display_type' => FormulaWidget::DISPLAY_KPI,
            'period_preset' => null,
            'chart_config' => ['format' => 'currency'],
        ]);

        $payload = $this->builder->build($widget, $this->user);

        $this->assertSame('kpi', $payload['type']);
        $this->assertSame('Saldo', $payload['name']);
        $this->assertIsFloat($payload['value']);
    }

    #[Test]
    public function it_builds_balance_summary_kpi_payload(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create([
            'code' => 'saldo_liquidita',
            'name' => 'Liquidità attuale',
        ]);

        $widget = FormulaWidget::factory()->for($this->user)->for($variable, 'financialVariable')->create([
            'name' => 'Liquidità attuale',
            'display_type' => FormulaWidget::DISPLAY_KPI,
            'period_preset' => null,
            'chart_config' => ['format' => 'currency', 'variant' => 'balance_summary'],
        ]);

        $payload = $this->builder->build($widget, $this->user);

        $this->assertSame('kpi', $payload['type']);
        $this->assertSame('balance_summary', $payload['variant']);
        $this->assertArrayHasKey('invested', $payload);
        $this->assertArrayHasKey('patrimonioTotal', $payload);
        $this->assertSame(1, $payload['accountsCount']);
    }

    #[Test]
    public function it_builds_bar_payload_with_multiple_series(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[total_investments]')->create([
            'code' => 'bar_base',
            'name' => 'Bar Base',
        ]);

        $widget = FormulaWidget::factory()->for($this->user)->for($variable, 'financialVariable')->create([
            'display_type' => FormulaWidget::DISPLAY_BAR,
            'period_preset' => null,
            'chart_config' => [
                'series' => [
                    ['code' => 'total_investments', 'label' => 'Investimenti', 'color' => '#6366f1'],
                    ['code' => 'household_balance', 'label' => 'Liquidità', 'color' => '#10b981'],
                ],
            ],
        ]);

        $payload = $this->builder->build($widget, $this->user);

        $this->assertSame('bar', $payload['type']);
        $this->assertCount(2, $payload['categories']);
    }

    #[Test]
    public function it_builds_kpi_payload_with_lower_is_better_delta_polarity(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_expenses]')->create([
            'code' => 'uscite_30gg',
            'name' => 'Uscite 30 giorni',
        ]);

        $widget = FormulaWidget::factory()->for($this->user)->for($variable, 'financialVariable')->create([
            'name' => 'Uscite (30 gg)',
            'display_type' => FormulaWidget::DISPLAY_KPI,
            'period_preset' => 'rolling_30',
            'chart_config' => [
                'show_delta' => true,
                'format' => 'currency',
                'delta_polarity' => 'lower_is_better',
            ],
        ]);

        $payload = $this->builder->build($widget, $this->user);

        $this->assertSame('lower_is_better', $payload['deltaPolarity']);
        $this->assertSame('30 giorni precedenti', $payload['deltaComparisonLabel']);
    }

    #[Test]
    public function it_builds_guest_preview_payload(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_net]')->create();
        $widget = FormulaWidget::factory()->for($this->user)->for($variable, 'financialVariable')->create([
            'display_type' => FormulaWidget::DISPLAY_KPI,
        ]);

        $payload = $this->builder->buildForGuest($widget);

        $this->assertSame('kpi', $payload['type']);
        $this->assertArrayHasKey('value', $payload);
    }

    #[Test]
    public function it_builds_traffic_light_progress_with_editable_threshold(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('MAX([period_expenses], 0)')->create([
            'code' => 'alert_spese_elevate',
            'name' => 'Alert spese elevate',
        ]);

        $widget = FormulaWidget::factory()->for($this->user)->for($variable, 'financialVariable')->create([
            'name' => 'Alert spese elevate',
            'display_type' => FormulaWidget::DISPLAY_PROGRESS,
            'period_preset' => 'current_month',
            'chart_config' => [
                'variant' => 'traffic_light',
                'value_code' => 'household_balance',
                'threshold_amount' => 1000,
                'bands' => ['warn' => 70, 'danger' => 100],
                'parameters' => [
                    [
                        'key' => 'threshold',
                        'type' => 'number',
                        'label' => 'Soglia (€)',
                        'default' => '1000',
                    ],
                ],
            ],
        ]);

        $atLimit = $this->builder->build($widget, $this->user);
        $this->assertSame('progress', $atLimit['type']);
        $this->assertSame('traffic_light', $atLimit['variant']);
        $this->assertSame(1000.0, $atLimit['threshold']);
        $this->assertSame(1500.0, $atLimit['value']);
        $this->assertSame('danger', $atLimit['status']);

        $warn = $this->builder->build($widget, $this->user, ['threshold' => '2000']);
        $this->assertSame(2000.0, $warn['threshold']);
        $this->assertSame('warn', $warn['status']);

        $ok = $this->builder->build($widget, $this->user, ['threshold' => '3000']);
        $this->assertSame(3000.0, $ok['threshold']);
        $this->assertSame('ok', $ok['status']);
    }
}
