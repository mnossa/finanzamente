<?php

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Support\FormulaTokenParser;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot: allinea nome ufficiale/cloni «Saldo conti» → «Liquidità attuale» ([household_balance]).
 */
return new class extends Migration
{
    public function up(): void
    {
        FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->where('name', 'Saldo conti')
            ->update(['name' => 'Liquidità attuale']);

        FinancialVariable::query()
            ->where(function ($query): void {
                $query->where('template_slug', 'official.saldo_liquidita_var')
                    ->orWhere(function ($inner): void {
                        $inner->where('code', 'saldo_liquidita')
                            ->where('is_official_template', true);
                    });
            })
            ->where('name', 'Saldo conti')
            ->update(['name' => 'Liquidità attuale']);

        $parser = app(FormulaTokenParser::class);
        $target = $parser->normalizeFormula('[household_balance]');

        FinancialVariable::query()
            ->where('name', 'Saldo conti')
            ->where('type', FinancialVariable::TYPE_FORMULA)
            ->get()
            ->each(function (FinancialVariable $variable) use ($parser, $target): void {
                if ($parser->normalizeFormula((string) $variable->formula_string) !== $target) {
                    return;
                }

                $variable->name = 'Liquidità attuale';
                $variable->save();

                FormulaWidget::query()
                    ->where('financial_variable_id', $variable->id)
                    ->where('name', 'Saldo conti')
                    ->update(['name' => 'Liquidità attuale']);
            });
    }

    public function down(): void
    {
        // Irreversibile (rename prodotto).
    }
};
