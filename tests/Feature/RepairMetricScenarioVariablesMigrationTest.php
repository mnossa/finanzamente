<?php

namespace Tests\Feature;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepairMetricScenarioVariablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migration_repairs_wrong_pac_formula_and_merges_duplicates(): void
    {
        $user = User::factory()->create();

        $brokenPac = FinancialVariable::factory()->for($user)->formula('[totale_investimenti]')->create([
            'name' => 'Versamenti PAC mensili',
            'code' => 'pac_broken',
        ]);

        $goodPac = FinancialVariable::factory()->for($user)->formula('[pac_monthly_total]')->create([
            'name' => 'Versamenti PAC mensili',
            'code' => 'pac_ok',
        ]);

        $speso = FinancialVariable::factory()->for($user)->formula('[period_expenses]')->create([
            'name' => 'Speso nel periodo',
            'code' => 'speso',
        ]);

        $uscite = FinancialVariable::factory()->for($user)->formula('[period_expenses]')->create([
            'name' => 'Uscite 30 giorni',
            'code' => 'uscite',
        ]);

        $widgetOnUscite = FormulaWidget::factory()->for($user)->create([
            'financial_variable_id' => $uscite->id,
            'name' => 'Uscite widget',
        ]);

        $migration = require database_path('migrations/2026_07_24_110000_repair_metric_scenario_variables_and_merge_duplicates.php');
        $migration->up();

        $remaining = FinancialVariable::query()->where('user_id', $user->id)->orderBy('id')->get();

        $this->assertNull(FinancialVariable::query()->find($brokenPac->id));

        $pacVars = $remaining->filter(
            fn (FinancialVariable $variable) => $variable->formula_string === '[pac_monthly_total]',
        );
        $this->assertCount(1, $pacVars);
        $this->assertSame($goodPac->id, $pacVars->first()->id);

        $periodExpenseVars = $remaining->filter(
            fn (FinancialVariable $variable) => str_replace(' ', '', (string) $variable->formula_string) === '[period_expenses]',
        );
        $this->assertCount(1, $periodExpenseVars);
        $this->assertSame($speso->id, $periodExpenseVars->first()->id);

        $widgetOnUscite->refresh();
        $this->assertSame($speso->id, $widgetOnUscite->financial_variable_id);
    }
}
