<?php

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FinancialVariableLibraryService;
use App\Support\FormulaTokenParser;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot: ripara formule scenario sbagliate, applica alias legacy, unisce duplicati stessa formula.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parser = app(FormulaTokenParser::class);
        $library = app(FinancialVariableLibraryService::class);

        User::query()->orderBy('id')->each(function (User $user) use ($parser, $library): void {
            // Prima i nomi scenario (es. PAC con formula sbagliata), poi alias token, poi merge.
            $this->repairScenarioNames($user, $parser, $library);
            $this->applyFormulaAliases($user, $parser, $library);
            $this->mergeDuplicateFormulas($user, $parser, $library);
        });
    }

    public function down(): void
    {
        // Irreversibile (merge + repair).
    }

    private function applyFormulaAliases(User $user, FormulaTokenParser $parser, FinancialVariableLibraryService $library): void
    {
        foreach ($library->formulaAliases() as $from => $to) {
            $fromNormalized = $parser->normalizeFormula($from);
            FinancialVariable::query()
                ->where('user_id', $user->id)
                ->where('type', FinancialVariable::TYPE_FORMULA)
                ->where('is_official_template', false)
                ->get()
                ->each(function (FinancialVariable $variable) use ($parser, $fromNormalized, $to): void {
                    if ($parser->normalizeFormula((string) $variable->formula_string) === $fromNormalized) {
                        $variable->formula_string = $to;
                        $variable->save();
                    }
                });
        }
    }

    private function repairScenarioNames(User $user, FormulaTokenParser $parser, FinancialVariableLibraryService $library): void
    {
        foreach ($library->scenarioNameFormulas() as $name => $expectedFormula) {
            $expectedNormalized = $parser->normalizeFormula($expectedFormula);

            FinancialVariable::query()
                ->where('user_id', $user->id)
                ->where('type', FinancialVariable::TYPE_FORMULA)
                ->where('is_official_template', false)
                ->where('name', $name)
                ->get()
                ->each(function (FinancialVariable $variable) use ($parser, $expectedNormalized, $expectedFormula, $library, $user): void {
                    if ($parser->normalizeFormula((string) $variable->formula_string) === $expectedNormalized) {
                        return;
                    }

                    $already = $library->findReusableByFormula($user, $expectedFormula);
                    if ($already !== null && $already->id !== $variable->id) {
                        FormulaWidget::query()
                            ->where('financial_variable_id', $variable->id)
                            ->update(['financial_variable_id' => $already->id]);

                        FinancialVariable::query()
                            ->where('source_id', $variable->id)
                            ->update(['source_id' => $already->source_id]);

                        $variable->delete();

                        return;
                    }

                    $variable->formula_string = $expectedFormula;
                    $variable->save();
                });
        }
    }

    private function mergeDuplicateFormulas(User $user, FormulaTokenParser $parser, FinancialVariableLibraryService $library): void
    {
        $groups = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('type', FinancialVariable::TYPE_FORMULA)
            ->where('is_official_template', false)
            ->with('source')
            ->get()
            ->groupBy(fn (FinancialVariable $variable) => $parser->normalizeFormula((string) $variable->formula_string));

        foreach ($groups as $normalized => $variables) {
            if ($normalized === '' || $variables->count() < 2) {
                continue;
            }

            /** @var list<FinancialVariable> $candidates */
            $candidates = $variables->all();
            $keeper = $library->preferLibraryVariable($candidates);
            if ($keeper === null) {
                continue;
            }

            foreach ($candidates as $loser) {
                if ($loser->id === $keeper->id) {
                    continue;
                }

                FormulaWidget::query()
                    ->where('financial_variable_id', $loser->id)
                    ->update(['financial_variable_id' => $keeper->id]);

                FinancialVariable::query()
                    ->where('source_id', $loser->id)
                    ->update(['source_id' => $keeper->source_id]);

                $loser->delete();
            }
        }
    }
};
