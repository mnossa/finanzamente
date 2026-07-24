<?php

namespace App\Support;

use App\Models\FinancialVariable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FormulaTokenParser
{
    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const TOKEN_REGEX = '/\[(?<code>[a-z][a-z0-9_]*)\]/';

    /**
     * @return array<int, string>
     */
    public function extract(string $formula): array
    {
        preg_match_all(self::TOKEN_REGEX, $formula, $matches);

        return array_values(array_unique($matches['code'] ?? []));
    }

    public function sanitizeCode(string $name): string
    {
        $code = Str::slug($name, '_');

        if ($code === '' || ! preg_match(self::CODE_PATTERN, $code)) {
            throw ValidationException::withMessages([
                'code' => 'Il codice variabile non è valido. Usa lettere, numeri e underscore.',
            ]);
        }

        return $code;
    }

    public function normalizeFormula(string $formula): string
    {
        $collapsed = preg_replace('/\s+/', '', trim($formula));

        return $collapsed ?? '';
    }

    public function uniqueCodeForUser(int $userId, string $preferredCode): string
    {
        $base = $preferredCode !== '' && $this->isValidCode($preferredCode)
            ? $preferredCode
            : 'metrica';

        $code = $base;
        $suffix = 2;

        while (
            FinancialVariable::query()
                ->where('user_id', $userId)
                ->where('code', $code)
                ->exists()
        ) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    public function isValidCode(string $code): bool
    {
        return (bool) preg_match(self::CODE_PATTERN, $code);
    }

    public function substitute(string $formula, array $resolvedValues): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_REGEX,
            function (array $matches) use ($resolvedValues) {
                $code = $matches['code'];

                if (! array_key_exists($code, $resolvedValues)) {
                    throw ValidationException::withMessages([
                        'formula_string' => "La variabile [{$code}] non è disponibile.",
                    ]);
                }

                return (string) $resolvedValues[$code];
            },
            $formula
        );
    }
}
