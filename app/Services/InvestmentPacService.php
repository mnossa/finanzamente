<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentPac;
use Illuminate\Support\Carbon;

class InvestmentPacService
{
    public function realignPacMovements(InvestmentPac $pac, ?Carbon $today = null): int
    {
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

        foreach ($movements as $index => $movement) {
            $payload = [
                'account_id' => $pac->account_id,
                'asset_id' => $pac->investment_asset_id,
                'buy_price' => (float) $pac->amount,
                'quantity' => 1,
                'fees' => $pac->fees !== null ? (float) $pac->fees : null,
                'notes' => trim('PAC automatico'.($pac->notes ? ' - '.$pac->notes : '')),
            ];

            if ($index < $expectedCount) {
                $payload['buy_date'] = $expectedDates[$index]->toDateString();
                $movement->update($payload);
                $updatedCount++;

                continue;
            }

            // Se il PAC ora prevede meno esecuzioni, elimina gli extra ancora aperti.
            // I movimenti già venduti restano per preservare lo storico realizzato.
            if ($movement->isOpen()) {
                $movement->delete();
                $updatedCount++;

                continue;
            }

            $movement->update($payload);
            $updatedCount++;
        }

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
        $date = ($today ?? Carbon::today())->copy();
        $count = 0;

        $pacs = InvestmentPac::where('status', 'active')
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->get();

        foreach ($pacs as $pac) {
            if ($this->runSinglePac($pac, $date) !== null) {
                $count++;
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

        $investment = Investment::create([
            'user_id' => $pac->user_id,
            'household_id' => $pac->household_id,
            'account_id' => $pac->account_id,
            'asset_id' => $pac->investment_asset_id,
            'investment_pac_id' => $pac->id,
            'quantity' => 1,
            'buy_price' => (float) $pac->amount,
            'buy_date' => $date->toDateString(),
            'fees' => $pac->fees !== null ? (float) $pac->fees : null,
            'notes' => trim('PAC automatico'.($pac->notes ? ' - '.$pac->notes : '')),
            'is_private' => false,
        ]);

        $pac->update(['last_executed_at' => $date->toDateString()]);

        return $investment;
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
