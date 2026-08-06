<?php

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Support\FormulaTokenParser;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot: Alert spese elevate da flag 0/1 (€) a progress semaforo + soglia editabile.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parser = app(FormulaTokenParser::class);
        $legacy = $parser->normalizeFormula('IF([period_expenses] > 1000, 1, 0)');
        $canonical = $parser->normalizeFormula('MAX([period_expenses], 0)');

        $trafficLightConfig = [
            'variant' => 'traffic_light',
            'value_code' => 'period_expenses',
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
        ];

        FinancialVariable::query()
            ->where('type', FinancialVariable::TYPE_FORMULA)
            ->where(function ($query): void {
                $query->where('name', 'Alert spese elevate')
                    ->orWhere('code', 'alert_spese_elevate');
            })
            ->get()
            ->each(function (FinancialVariable $variable) use ($parser, $legacy, $canonical, $trafficLightConfig): void {
                $normalized = $parser->normalizeFormula((string) $variable->formula_string);
                if ($normalized !== $legacy && $normalized !== $canonical) {
                    return;
                }

                if ($normalized === $legacy) {
                    $variable->formula_string = 'MAX([period_expenses], 0)';
                    $variable->save();
                }

                FormulaWidget::query()
                    ->where('financial_variable_id', $variable->id)
                    ->get()
                    ->each(function (FormulaWidget $widget) use ($trafficLightConfig): void {
                        $widget->display_type = FormulaWidget::DISPLAY_PROGRESS;
                        $widget->chart_config = $trafficLightConfig;
                        $widget->save();
                    });
            });
    }

    public function down(): void
    {
        // Irreversibile (cambio prodotto UI).
    }
};
