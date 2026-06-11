<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\User;
use App\Support\FormulaTokenParser;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class FormulaResolverService
{
    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
        private readonly SystemVariableResolver $systemVariableResolver,
        private readonly FinancialVariableDependencyValidator $dependencyValidator,
        private readonly FormulaSyntaxValidator $syntaxValidator,
    ) {}

    public function evaluate(User $user, string $formulaString, Carbon $startDate, Carbon $endDate): float
    {
        $tokens = $this->tokenParser->extract($formulaString);

        if ($tokens === []) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula deve contenere almeno una variabile o un valore.',
            ]);
        }

        $resolved = [];
        foreach ($tokens as $code) {
            $resolved[$code] = $this->resolveCode($user, $code, $startDate, $endDate);
        }

        $expression = $this->tokenParser->substitute($formulaString, $resolved);

        return $this->syntaxValidator->evaluateNumericExpression($expression);
    }

    public function resolveCode(
        User $user,
        string $code,
        Carbon $startDate,
        Carbon $endDate,
        int $depth = 0,
        array $resolvingStack = [],
    ): float {
        if (in_array($code, $resolvingStack, true)) {
            throw ValidationException::withMessages([
                'formula_string' => 'Rilevata dipendenza circolare durante il calcolo.',
            ]);
        }

        $maxDepth = (int) config('financial_variables.max_formula_depth', 3);
        if ($depth > $maxDepth) {
            throw ValidationException::withMessages([
                'formula_string' => "Profondità massima formula superata ({$maxDepth} livelli).",
            ]);
        }

        if ($this->systemVariableResolver->isSystemCode($code)) {
            return $this->systemVariableResolver->resolve($user, $code, $startDate, $endDate);
        }

        $variable = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('code', $code)
            ->first();

        if ($variable === null) {
            throw ValidationException::withMessages([
                'variable_code' => "La variabile [{$code}] non esiste.",
            ]);
        }

        if ($variable->isStatic()) {
            return (float) $variable->static_value;
        }

        $stack = [...$resolvingStack, $code];
        $tokens = $this->tokenParser->extract((string) $variable->formula_string);
        $resolved = [];

        foreach ($tokens as $token) {
            $resolved[$token] = $this->resolveCode($user, $token, $startDate, $endDate, $depth + 1, $stack);
        }

        $expression = $this->tokenParser->substitute((string) $variable->formula_string, $resolved);

        return $this->syntaxValidator->evaluateNumericExpression($expression);
    }

    /**
     * @return array<int, array{label: string, value: float}>
     */
    public function evaluateMonthlySeries(
        User $user,
        string $formulaOrCode,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?FormulaPeriodResolver $periodResolver = null,
    ): array {
        $periodResolver ??= app(FormulaPeriodResolver::class);
        $buckets = $periodResolver->monthBuckets($rangeStart, $rangeEnd);
        $series = [];

        foreach ($buckets as $bucket) {
            $value = $this->isDirectCode($formulaOrCode)
                ? $this->systemVariableResolver->resolveForSeries($user, $formulaOrCode, $bucket['end'])
                : $this->evaluate($user, $formulaOrCode, $bucket['start'], $bucket['end']);

            $series[] = [
                'label' => $bucket['label'],
                'value' => round($value, 2),
            ];
        }

        return $series;
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, float>
     */
    public function evaluateCodesForPeriod(User $user, array $codes, Carbon $startDate, Carbon $endDate): array
    {
        $values = [];

        foreach ($codes as $code) {
            $values[$code] = round($this->resolveCode($user, $code, $startDate, $endDate), 2);
        }

        return $values;
    }

    /**
     * @return array{
     *   system: array<int, array{code: string, label: string, requires_period: bool}>,
     *   user: array<int, array{id: int, code: string, name: string, type: string}>
     * }
     */
    public function listAvailableVariables(User $user): array
    {
        $userVariables = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialVariable $variable) => [
                'id' => $variable->id,
                'code' => $variable->code,
                'name' => $variable->name,
                'type' => $variable->type,
            ])
            ->values()
            ->all();

        return [
            'system' => $this->systemVariableResolver->listMetadata(),
            'user' => $userVariables,
        ];
    }

    public function validateDependencies(
        User $user,
        string $formulaString,
        ?int $editingVariableId = null,
        ?string $variableCode = null,
    ): void {
        $this->syntaxValidator->validate($user, $formulaString, $editingVariableId, $variableCode);
        $this->dependencyValidator->validate($user, $formulaString, $editingVariableId, $variableCode);
    }

    private function isDirectCode(string $value): bool
    {
        return $this->tokenParser->isValidCode($value)
            && $this->systemVariableResolver->isSystemCode($value)
            && ! str_contains($value, '[');
    }
}
