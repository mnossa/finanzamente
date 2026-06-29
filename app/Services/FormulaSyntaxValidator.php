<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\User;
use App\Support\FormulaTokenParser;
use Illuminate\Validation\ValidationException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Validates formula syntax before persistence or evaluation.
 * Supports IF/WHEN, comparators and numeric helpers via Symfony ExpressionLanguage.
 */
class FormulaSyntaxValidator
{
    private ExpressionLanguage $expressionLanguage;

    /** @var list<string> */
    private const ALLOWED_FUNCTIONS = ['IF', 'WHEN', 'ABS', 'MIN', 'MAX', 'ROUND'];

    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
        private readonly SystemVariableResolver $systemVariableResolver,
    ) {
        $this->expressionLanguage = new ExpressionLanguage;
        $this->registerFunctions();
    }

    public function validate(User $user, string $formulaString, ?int $editingVariableId = null, ?string $variableCode = null): void
    {
        $formula = trim($formulaString);

        if ($formula === '') {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula non può essere vuota.',
            ]);
        }

        if (strlen($formula) > 2000) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula è troppo lunga.',
            ]);
        }

        if (preg_match('/\b(eval|exec|system|shell_exec|passthru|proc_open|popen)\b/i', $formula)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene istruzioni non consentite.',
            ]);
        }

        if (! preg_match('~^[0-9+\-*/().\s\[\]a-z_,><=!?A-Z]+$~i', $formula)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene caratteri non consentiti. Usa variabili [codice], numeri, operatori e funzioni IF/WHEN.',
            ]);
        }

        if (preg_match('/\[\s*\]/', $formula)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene un riferimento a variabile vuoto [].',
            ]);
        }

        if (substr_count($formula, '[') !== substr_count($formula, ']')) {
            throw ValidationException::withMessages([
                'formula_string' => 'Le parentesi quadre non sono bilanciate.',
            ]);
        }

        $tokens = $this->tokenParser->extract($formula);

        if ($tokens === []) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula deve contenere almeno una variabile tra parentesi quadre.',
            ]);
        }

        $maxTokens = (int) config('financial_variables.max_formula_tokens', 20);
        if (count($tokens) > $maxTokens) {
            throw ValidationException::withMessages([
                'formula_string' => "La formula può referenziare al massimo {$maxTokens} variabili.",
            ]);
        }

        $ownCode = $this->resolveOwnCode($user, $editingVariableId, $variableCode);
        if ($ownCode !== null && in_array($ownCode, $tokens, true)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula non può riferire la variabile su sé stessa.',
            ]);
        }

        foreach ($tokens as $token) {
            if (! $this->tokenParser->isValidCode($token)) {
                throw ValidationException::withMessages([
                    'formula_string' => "Il codice variabile [{$token}] non è valido.",
                ]);
            }

            if ($this->systemVariableResolver->isSystemCode($token)) {
                continue;
            }

            $exists = FinancialVariable::query()
                ->where('user_id', $user->id)
                ->where('code', $token)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'formula_string' => "La variabile [{$token}] non esiste o non è disponibile.",
                ]);
            }
        }

        $probeValues = array_fill_keys($tokens, 1.0);
        $expression = $this->tokenParser->substitute($formula, $probeValues);
        $this->evaluateNumericExpression($expression);
    }

    public function evaluateNumericExpression(string $expression): float
    {
        if (preg_match('/\[[a-z][a-z0-9_]*\]/', $expression)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene variabili non risolte.',
            ]);
        }

        $normalized = $this->normalizeExpression($expression);

        if (! preg_match('~^[0-9+\-*/().\s,><=!?A-Za-z_]+$~', $normalized)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene caratteri non consentiti.',
            ]);
        }

        if (strlen($normalized) > 4000) {
            throw ValidationException::withMessages([
                'formula_string' => 'L\'espressione risultante è troppo complessa.',
            ]);
        }

        try {
            $result = $this->expressionLanguage->evaluate($normalized, []);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula non è valida o non può essere calcolata.',
            ]);
        }

        if (! is_numeric($result) || ! is_finite((float) $result)) {
            throw ValidationException::withMessages([
                'formula_string' => 'Il risultato della formula non è un numero valido.',
            ]);
        }

        return round((float) $result, 2);
    }

    private function normalizeExpression(string $expression): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($expression)) ?? $expression;

        // WHEN(cond, val) -> IF(cond, val, 0)
        $normalized = preg_replace_callback(
            '/\bWHEN\s*\(/i',
            fn () => 'IF(',
            $normalized,
        ) ?? $normalized;

        return $normalized;
    }

    private function registerFunctions(): void
    {
        $this->expressionLanguage->register('IF', function ($arguments, $condition, $then, $else = 0) {
            return [$condition, $then, $else];
        }, function ($arguments, $condition, $then, $else = 0) {
            return $condition ? (float) $then : (float) $else;
        });

        $this->expressionLanguage->register('ABS', function ($arguments, $value) {
            return [$value];
        }, function ($arguments, $value) {
            return abs((float) $value);
        });

        $this->expressionLanguage->register('MIN', function ($arguments, $a, $b) {
            return [$a, $b];
        }, function ($arguments, $a, $b) {
            return min((float) $a, (float) $b);
        });

        $this->expressionLanguage->register('MAX', function ($arguments, $a, $b) {
            return [$a, $b];
        }, function ($arguments, $a, $b) {
            return max((float) $a, (float) $b);
        });

        $this->expressionLanguage->register('ROUND', function ($arguments, $value, $precision = 0) {
            return [$value, $precision];
        }, function ($arguments, $value, $precision = 0) {
            return round((float) $value, (int) $precision);
        });
    }

    private function resolveOwnCode(User $user, ?int $editingVariableId, ?string $variableCode): ?string
    {
        if ($editingVariableId !== null) {
            return FinancialVariable::query()
                ->where('user_id', $user->id)
                ->where('id', $editingVariableId)
                ->value('code');
        }

        if ($variableCode !== null && $variableCode !== '') {
            return $variableCode;
        }

        return null;
    }
}
