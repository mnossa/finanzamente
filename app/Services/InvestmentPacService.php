<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentPac;
use Illuminate\Support\Carbon;

class InvestmentPacService
{
    public function __construct(
        private readonly InvestmentMetricsService $investmentMetricsService,
        private readonly InvestmentTransactionSyncService $investmentTransactionSyncService,
    ) {}

    public function realignPacMovements(InvestmentPac $pac, ?Carbon $today = null): int
    {
        $pac->loadMissing('asset:id,symbol');

        $movements = Investment::where('investment_pac_id', $pac->id)
            ->orderBy('buy_date')
            ->orderBy('id')
            ->get();

        if ($movements->isEmpty()) {
            return 0;
        }

        $expectedDates = $this->buildExpectedExecutionDates($pac, $today);
        $updatedCount = 0;
        $expectedCount = count($expectedDates);
        $amount = (float) $pac->amount;

        foreach ($movements as $index => $movement) {
            $buyDate = $index < $expectedCount
                ? $expectedDates[$index]->toDateString()
                : $movement->buy_date?->format('Y-m-d');

            $lot = $this->investmentMetricsService->resolvePurchaseLot(
                $amount,
                $pac->asset?->symbol,
                $buyDate ?? Carbon::today()->toDateString(),
            );

            $payload = [
                'account_id' => $pac->account_id,
                'asset_id' => $pac->investment_asset_id,
                'buy_price' => $lot['buy_price'],
                'nav_at_buy' => $lot['nav_at_buy'],
                'quantity' => $lot['quantity'],
                'fees' => $pac->fees !== null ? (float) $pac->fees : null,
                'notes' => trim('PAC automatico'.($pac->notes ? ' - '.$pac->notes : '')),
            ];

            if ($index < $expectedCount) {
                $payload['buy_date'] = $expectedDates[$index]->toDateString();
                $movement->update($payload);
                $this->investmentTransactionSyncService->syncInvestment($movement->fresh());
                $updatedCount++;

                continue;
            }

            if ($movement->isOpen()) {
                $this->investmentTransactionSyncService->deleteForInvestment($movement);
                $movement->delete();
                $updatedCount++;

                continue;
            }

            $movement->update($payload);
            $this->investmentTransactionSyncService->syncInvestment($movement->fresh());
            $updatedCount++;
        }

        $this->refreshLastExecutedAt($pac);

        return $updatedCount;
    }

    public function backfillPacUntilLastUsefulDate(InvestmentPac $pac, ?Carbon $today = null): int
    {
        if ($pac->status !== 'active' || $pac->start_date === null) {
            return 0;
        }

        $startDate = Carbon::parse($pac->start_date)->startOfDay();
        $limitDate = ($today ?? Carbon::today())->copy()->startOfDay();

        if ($pac->end_date !== null) {
            $endDate = Carbon::parse($pac->end_date)->startOfDay();
            if ($endDate->lt($limitDate)) {
                $limitDate = $endDate;
            }
        }

        if ($startDate->gt($limitDate)) {
            return 0;
        }

        $preferredDay = (int) $startDate->day;
        $executionDate = $startDate->copy();
        $count = 0;

        while ($executionDate->lte($limitDate)) {
            if ($this->runSinglePac($pac, $executionDate) !== null) {
                $count++;
            }

            $executionDate = $this->nextMonthlyExecutionDate($executionDate, $preferredDay);
        }

        return $count;
    }

    public function runDuePacs(?Carbon $today = null): int
    {
        $date = ($today ?? Carbon::today())->copy()->startOfDay();
        $count = 0;

        $pacs = InvestmentPac::where('status', 'active')
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->get();

        foreach ($pacs as $pac) {
            while (($dueDate = $this->calculateNextExecutionDate($pac, $date)) && $dueDate->lte($date)) {
                if ($this->runSinglePac($pac, $dueDate) === null) {
                    break;
                }

                $count++;
                $pac->refresh();
            }
        }

        return $count;
    }

    public function runSinglePac(InvestmentPac $pac, ?Carbon $today = null, bool $force = false): ?Investment
    {
        $date = ($today ?? Carbon::today())->copy();

        if (! $force && $this->hasExecutionInMonth($pac, $date)) {
            return null;
        }

        if (! $force && ! $this->isExecutionAllowedByDateRange($pac, $date)) {
            return null;
        }

        $this->applyInflationIfDue($pac, $date);
        $pac->loadMissing('asset:id,symbol');

        $lot = $this->investmentMetricsService->resolvePurchaseLot(
            (float) $pac->amount,
            $pac->asset?->symbol,
            $date->toDateString(),
        );

        $investment = Investment::create([
            'user_id' => $pac->user_id,
            'household_id' => $pac->household_id,
            'account_id' => $pac->account_id,
            'asset_id' => $pac->investment_asset_id,
            'investment_pac_id' => $pac->id,
            'quantity' => $lot['quantity'],
            'buy_price' => $lot['buy_price'],
            'nav_at_buy' => $lot['nav_at_buy'],
            'buy_date' => $date->toDateString(),
            'fees' => $pac->fees !== null ? (float) $pac->fees : null,
            'notes' => trim('PAC automatico'.($pac->notes ? ' - '.$pac->notes : '')),
            'is_private' => false,
        ]);

        $this->investmentTransactionSyncService->syncPurchase($investment);

        $this->refreshLastExecutedAt($pac);

        return $investment;
    }

    public function refreshLastExecutedAt(InvestmentPac $pac): void
    {
        $latestBuyDate = Investment::where('investment_pac_id', $pac->id)
            ->max('buy_date');

        $pac->update([
            'last_executed_at' => $latestBuyDate,
        ]);
    }

    public function calculateNextExecutionDate(InvestmentPac $pac, ?Carbon $today = null): ?Carbon
    {
        if ($pac->status !== 'active' || $pac->start_date === null) {
            return null;
        }

        $todayDate = ($today ?? Carbon::today())->copy()->startOfDay();
        $startDate = Carbon::parse($pac->start_date)->startOfDay();

        if ($startDate->gt($todayDate)) {
            return $startDate;
        }

        if ($pac->end_date !== null && Carbon::parse($pac->end_date)->isBefore($todayDate)) {
            return null;
        }

        $preferredDay = (int) $startDate->day;
        $candidate = $startDate->copy();

        while ($candidate->lte($todayDate)) {
            if (! $this->hasExecutionInMonth($pac, $candidate)) {
                if ($this->isExecutionAllowedByDateRange($pac, $candidate)) {
                    return $candidate;
                }
            }

            $candidate = $this->nextMonthlyExecutionDate($candidate, $preferredDay);
        }

        if ($pac->end_date !== null && $candidate->gt(Carbon::parse($pac->end_date))) {
            return null;
        }

        return $candidate;
    }

    private function isExecutionAllowedByDateRange(InvestmentPac $pac, Carbon $date): bool
    {
        if ($pac->status !== 'active') {
            return false;
        }

        if ($pac->start_date === null || Carbon::parse($pac->start_date)->isAfter($date)) {
            return false;
        }

        if ($pac->end_date !== null && Carbon::parse($pac->end_date)->isBefore($date)) {
            return false;
        }

        return true;
    }

    private function hasExecutionInMonth(InvestmentPac $pac, Carbon $date): bool
    {
        return Investment::where('investment_pac_id', $pac->id)
            ->whereYear('buy_date', $date->year)
            ->whereMonth('buy_date', $date->month)
            ->exists();
    }

    private function nextMonthlyExecutionDate(Carbon $currentDate, int $preferredDay): Carbon
    {
        $nextMonth = $currentDate->copy()->addMonth()->startOfMonth();
        $targetDay = min($preferredDay, $nextMonth->daysInMonth);

        return $nextMonth->copy()->day($targetDay);
    }

    /**
     * @return array<int, Carbon>
     */
    private function buildExpectedExecutionDates(InvestmentPac $pac, ?Carbon $today = null): array
    {
        if ($pac->start_date === null) {
            return [];
        }

        $startDate = Carbon::parse($pac->start_date)->startOfDay();
        $limitDate = ($today ?? Carbon::today())->copy()->startOfDay();

        if ($pac->end_date !== null) {
            $endDate = Carbon::parse($pac->end_date)->startOfDay();
            if ($endDate->lt($limitDate)) {
                $limitDate = $endDate;
            }
        }

        if ($startDate->gt($limitDate)) {
            return [];
        }

        $preferredDay = (int) $startDate->day;
        $executionDate = $startDate->copy();
        $dates = [];

        while ($executionDate->lte($limitDate)) {
            $dates[] = $executionDate->copy();
            $executionDate = $this->nextMonthlyExecutionDate($executionDate, $preferredDay);
        }

        return $dates;
    }

    private function applyInflationIfDue(InvestmentPac $pac, Carbon $today): void
    {
        if (! $pac->adjust_for_inflation || $pac->inflation_rate_annual === null) {
            return;
        }

        $anchor = $pac->last_inflation_adjusted_at ?? $pac->start_date;
        if ($anchor === null || Carbon::parse($anchor)->diffInYears($today) < 1) {
            return;
        }

        $pac->amount = round((float) $pac->amount * (1 + ((float) $pac->inflation_rate_annual / 100)), 2);
        $pac->last_inflation_adjusted_at = $today->toDateString();
        $pac->save();
    }
}
