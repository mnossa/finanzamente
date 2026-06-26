<?php

namespace App\Support;

/**
 * Contesto di risoluzione per widget a formula (filtri runtime come conto).
 */
class FormulaWidgetRuntimeContext
{
    public function __construct(
        public readonly ?int $accountId = null,
        public readonly int $periodOffset = 0,
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

        return new self($accountId, $periodOffset);
    }

    public function hasAccountFilter(): bool
    {
        return $this->accountId !== null;
    }

    public function hasPeriodOffset(): bool
    {
        return $this->periodOffset !== 0;
    }
}
