<?php

namespace App\Support;

/**
 * Contesto di risoluzione per widget a formula (filtri runtime).
 */
class FormulaWidgetRuntimeContext
{
    /**
     * @param  array<string, string|int|null>  $parameters
     */
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly int $periodOffset = 0,
        public readonly array $parameters = [],
    ) {}

    /**
     * @param  array<string, string|int|null>  $resolvedParameters
     */
    public static function fromResolvedParameters(array $resolvedParameters): self
    {
        $accountRaw = $resolvedParameters['account_id'] ?? 'all';
        $accountId = ($accountRaw === 'all' || $accountRaw === null || $accountRaw === '')
            ? null
            : (int) $accountRaw;

        $offsetRaw = $resolvedParameters['period_offset'] ?? 0;
        $periodOffset = is_numeric($offsetRaw) ? (int) $offsetRaw : 0;

        return new self($accountId, $periodOffset, $resolvedParameters);
    }

    public function hasAccountFilter(): bool
    {
        return $this->accountId !== null;
    }

    public function hasPeriodOffset(): bool
    {
        return $this->periodOffset !== 0;
    }

    public function getParameter(string $key): ?string
    {
        $value = $this->parameters[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function getIntParameter(string $key): ?int
    {
        $value = $this->getParameter($key);

        if ($value === null || $value === 'all' || $value === 'none' || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
