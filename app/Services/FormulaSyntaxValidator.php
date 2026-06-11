<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\User;
use App\Support\FormulaTokenParser;
use Illuminate\Validation\ValidationException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Validates formula syntax before persistence or evaluation.
 * No eval(): only numeric expressions via Symfony ExpressionLanguage after token substitution.
 */
class FormulaSyntaxValidator
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
        private readonly SystemVariableResolver $systemVariableResolver,
    ) {
        $this->expressionLanguage = new ExpressionLanguage;
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

        if (! preg_match('~^[0-9+\-*/().\s\[\]a-z_]+$~i', $formula)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene caratteri non consentiti. Usa variabili [codice], numeri e operatori + − × ÷ ( ).',
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

        if (! preg_match('~^[0-9+\-*/().\s]+$~', $expression)) {
            throw ValidationException::withMessages([
                'formula_string' => 'La formula contiene caratteri non consentiti.',
            ]);
        }

        if (strlen($expression) > 4000) {
            throw ValidationException::withMessages([
                'formula_string' => 'L\'espressione risultante è troppo complessa.',
            ]);
        }

        try {
            $result = $this->expressionLanguage->evaluate($expression, []);
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
