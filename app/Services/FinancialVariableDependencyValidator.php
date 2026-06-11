<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\User;
use App\Support\FormulaTokenParser;
use Illuminate\Validation\ValidationException;

class FinancialVariableDependencyValidator
{
    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
        private readonly SystemVariableResolver $systemVariableResolver,
    ) {}

    public function validate(
        User $user,
        string $formulaString,
        ?int $editingVariableId = null,
        ?string $variableCode = null,
    ): void {
        $tokens = $this->tokenParser->extract($formulaString);
        $userFormulas = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('type', FinancialVariable::TYPE_FORMULA)
            ->when($editingVariableId, fn ($q) => $q->where('id', '!=', $editingVariableId))
            ->get()
            ->keyBy('code');

        $graph = [];

        foreach ($userFormulas as $code => $variable) {
            $deps = array_filter(
                $this->tokenParser->extract((string) $variable->formula_string),
                fn (string $token) => $userFormulas->has($token)
            );
            $graph[$code] = array_values($deps);
        }

        $editingCode = null;
        if ($editingVariableId !== null) {
            $editingCode = FinancialVariable::query()
                ->where('user_id', $user->id)
                ->where('id', $editingVariableId)
                ->value('code');
        }

        $pendingCode = $editingCode ?? ($variableCode !== null && $variableCode !== '' ? $variableCode : '__new_formula__');
        $graph[$pendingCode] = array_values(array_filter(
            $tokens,
            fn (string $token) => $userFormulas->has($token) || $token === $variableCode,
        ));

        $this->assertNoCycles($graph, $pendingCode);
        $this->assertMaxDepth($graph, $pendingCode, (int) config('financial_variables.max_formula_depth', 3));
    }

    /**
     * @param  array<string, array<int, string>>  $graph
     */
    private function assertNoCycles(array $graph, string $root): void
    {
        $visited = [];
        $stack = [];

        $visit = function (string $node) use (&$visit, &$visited, &$stack, $graph): void {
            if (in_array($node, $stack, true)) {
                throw ValidationException::withMessages([
                    'formula_string' => 'La formula crea una dipendenza circolare tra variabili.',
                ]);
            }

            if (isset($visited[$node])) {
                return;
            }

            $visited[$node] = true;
            $stack[] = $node;

            foreach ($graph[$node] ?? [] as $dependency) {
                if (isset($graph[$dependency])) {
                    $visit($dependency);
                }
            }

            array_pop($stack);
        };

        $visit($root);
    }

    /**
     * @param  array<string, array<int, string>>  $graph
     */
    private function assertMaxDepth(array $graph, string $root, int $maxDepth): void
    {
        $depths = [];

        $computeDepth = function (string $node) use (&$computeDepth, &$depths, $graph): int {
            if (isset($depths[$node])) {
                return $depths[$node];
            }

            $deps = $graph[$node] ?? [];
            if ($deps === []) {
                return $depths[$node] = 0;
            }

            $max = 0;
            foreach ($deps as $dependency) {
                if (! isset($graph[$dependency])) {
                    continue;
                }
                $max = max($max, $computeDepth($dependency) + 1);
            }

            return $depths[$node] = $max;
        };

        $depth = $computeDepth($root);

        if ($depth > $maxDepth) {
            throw ValidationException::withMessages([
                'formula_string' => "La formula supera la profondità massima consentita ({$maxDepth} livelli).",
            ]);
        }
    }
}
