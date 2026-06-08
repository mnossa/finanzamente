<?php

namespace App\Services;

use App\Models\InvestmentPac;
use Illuminate\Support\Collection;

class PacRecurrenceConflictService
{
    private const AMOUNT_TOLERANCE = 0.05;

    /**
     * Trova PAC attivi o in pausa che potrebbero duplicare una ricorrenza manuale.
     *
     * @return Collection<int, InvestmentPac>
     */
    public function findConflicts(
        int $householdId,
        int $accountId,
        float $amount,
        string $frequency,
    ): Collection {
        if ($frequency !== 'monthly') {
            return collect();
        }

        $pacs = InvestmentPac::query()
            ->where('household_id', $householdId)
            ->where('account_id', $accountId)
            ->whereIn('status', ['active', 'paused'])
            ->get();

        if ($pacs->isEmpty()) {
            return collect();
        }

        $targetAmount = abs($amount);

        return $pacs->filter(function (InvestmentPac $pac) use ($targetAmount): bool {
            $pacAmount = (float) $pac->amount + (float) ($pac->fees ?? 0);

            if ($pacAmount <= 0) {
                return false;
            }

            $variance = abs($targetAmount - $pacAmount) / $pacAmount;

            return $variance <= self::AMOUNT_TOLERANCE;
        })->values();
    }

    public function hasConflict(
        int $householdId,
        int $accountId,
        float $amount,
        string $frequency,
    ): bool {
        return $this->findConflicts($householdId, $accountId, $amount, $frequency)->isNotEmpty();
    }
}
