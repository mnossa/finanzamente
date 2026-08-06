<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Support\FormulaTokenParser;

/**
 * Riuso / riparazione variabili formula in libreria utente (ensure + clone).
 */
class FinancialVariableLibraryService
{
    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
    ) {}

    /**
     * @return list<FinancialVariable>
     */
    public function formulaMatchesForUser(User $user, string $formula): array
    {
        $normalized = $this->tokenParser->normalizeFormula($formula);
        if ($normalized === '') {
            return [];
        }

        return FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('type', FinancialVariable::TYPE_FORMULA)
            ->where('is_official_template', false)
            ->with('source')
            ->get()
            ->filter(fn (FinancialVariable $variable) => $this->tokenParser->normalizeFormula((string) $variable->formula_string) === $normalized)
            ->values()
            ->all();
    }

    /**
     * Preferisci ensure/custom (niente source ufficiale) rispetto ai clone di template.
     */
    public function preferLibraryVariable(array $candidates): ?FinancialVariable
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (FinancialVariable $a, FinancialVariable $b): int {
            $score = fn (FinancialVariable $variable): int => $this->isOfficialOrigin($variable) ? 1 : 0;

            return $score($a) <=> $score($b) ?: $a->id <=> $b->id;
        });

        return $candidates[0];
    }

    public function findReusableByFormula(User $user, string $formula): ?FinancialVariable
    {
        return $this->preferLibraryVariable($this->formulaMatchesForUser($user, $formula));
    }

    /**
     * Variabile con nome scenario ma formula diversa → allinea alla formula richiesta.
     */
    public function repairByScenarioName(User $user, string $name, string $expectedFormula): ?FinancialVariable
    {
        $expectedNormalized = $this->tokenParser->normalizeFormula($expectedFormula);
        if ($expectedNormalized === '') {
            return null;
        }

        $variable = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('type', FinancialVariable::TYPE_FORMULA)
            ->where('is_official_template', false)
            ->where('name', $name)
            ->with('source')
            ->orderBy('id')
            ->first();

        if ($variable === null) {
            return null;
        }

        if ($this->tokenParser->normalizeFormula((string) $variable->formula_string) === $expectedNormalized) {
            return $variable;
        }

        // Se un'altra riga ha già la formula corretta, riusala e rimuovi la riga rotta.
        $alreadyCorrect = $this->findReusableByFormula($user, $expectedFormula);
        if ($alreadyCorrect !== null && $alreadyCorrect->id !== $variable->id) {
            FormulaWidget::query()
                ->where('financial_variable_id', $variable->id)
                ->update(['financial_variable_id' => $alreadyCorrect->id]);

            FinancialVariable::query()
                ->where('source_id', $variable->id)
                ->update(['source_id' => $alreadyCorrect->source_id]);

            $variable->delete();

            return $alreadyCorrect->fresh(['source']);
        }

        $variable->formula_string = $expectedFormula;
        $variable->save();

        return $variable->fresh(['source']);
    }

    public function isOfficialOrigin(FinancialVariable $variable): bool
    {
        if ($variable->is_official_template) {
            return true;
        }

        if ($variable->source_id === null) {
            return false;
        }

        if ($variable->relationLoaded('source')) {
            return (bool) $variable->source?->is_official_template;
        }

        return FinancialVariable::query()
            ->where('id', $variable->source_id)
            ->where('is_official_template', true)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    public function scenarioNameFormulas(): array
    {
        /** @var array<string, string> $names */
        $names = config('metric_scenarios.names', []);

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public function formulaAliases(): array
    {
        /** @var array<string, string> $aliases */
        $aliases = config('metric_scenarios.formula_aliases', []);

        return $aliases;
    }

    public function applyFormulaAlias(string $formula): string
    {
        $normalized = $this->tokenParser->normalizeFormula($formula);
        foreach ($this->formulaAliases() as $from => $to) {
            if ($this->tokenParser->normalizeFormula($from) === $normalized) {
                return $to;
            }
        }

        return $formula;
    }
}
