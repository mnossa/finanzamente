<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentPac;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PacProjectionService
{
    public function __construct(
        private readonly InvestmentPacService $investmentPacService,
    ) {}

    /**
     * @return array{
     *     horizon_months: int,
     *     monthly_total: float,
     *     active_pac_count: int,
     *     series: list<array{month: string, label: string, contributions: float, cumulative: float}>
     * }
     */
    public function buildHouseholdProjection(User $user, int $horizonMonths = 12): array
    {
        return $this->buildHouseholdProjectionAt($user, $horizonMonths, Carbon::today());
    }

    public function getActivePacCount(User $user): int
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0;
        }

        return InvestmentPac::query()
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->count();
    }

    public function getMonthlyTotal(User $user): float
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0.0;
        }

        return round((float) InvestmentPac::query()
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->sum('amount'), 2);
    }

    public function getYtdContributions(User $user, ?Carbon $asOfDate = null): float
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0.0;
        }

        $asOfDate ??= Carbon::today();
        $yearStart = $asOfDate->copy()->startOfYear();

        return round((float) Investment::query()
            ->where('household_id', $householdId)
            ->whereNotNull('investment_pac_id')
            ->whereDate('buy_date', '>=', $yearStart)
            ->whereDate('buy_date', '<=', $asOfDate)
            ->selectRaw('COALESCE(SUM(buy_price * quantity + COALESCE(fees, 0)), 0) as total')
            ->value('total'), 2);
    }

    public function getProjectedContributions(User $user, int $horizonMonths = 12, ?Carbon $asOfDate = null): float
    {
        $projection = $this->buildHouseholdProjectionAt($user, $horizonMonths, $asOfDate);

        return round((float) collect($projection['series'])->sum('contributions'), 2);
    }

    public function getProjectedPatrimonio(User $user, int $horizonMonths = 12, float $annualGrowthRate = 0.0, ?Carbon $asOfDate = null): float
    {
        $asOfDate ??= Carbon::today();
        $currentInvested = $this->getYtdContributions($user, $asOfDate);
        $projection = $this->buildHouseholdProjectionAt($user, $horizonMonths, $asOfDate);
        $cumulative = 0.0;
        $monthlyGrowth = $annualGrowthRate > 0 ? pow(1 + ($annualGrowthRate / 100), 1 / 12) - 1 : 0.0;
        $running = $currentInvested;

        foreach ($projection['series'] as $point) {
            $contributions = (float) $point['contributions'];
            $running = ($running + $contributions) * (1 + $monthlyGrowth);
            $cumulative = $running;
        }

        return round($cumulative, 2);
    }

    /**
     * @return array{
     *     horizon_months: int,
     *     monthly_total: float,
     *     active_pac_count: int,
     *     series: list<array{month: string, label: string, contributions: float, cumulative: float}>
     * }
     */
    public function buildHouseholdProjectionAt(User $user, int $horizonMonths = 12, ?Carbon $asOfDate = null): array
    {
        $asOfDate ??= Carbon::today();
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return $this->emptyProjection($horizonMonths);
        }

        $pacs = InvestmentPac::query()
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->get();

        if ($pacs->isEmpty()) {
            return $this->emptyProjection($horizonMonths);
        }

        $monthlyTotal = round((float) $pacs->sum('amount'), 2);
        $contributionsByMonth = $this->buildMonthlyContributionsAt($pacs, $horizonMonths, $asOfDate);
        $series = [];
        $cumulative = 0.0;
        $cursor = $asOfDate->copy()->startOfMonth();

        for ($i = 0; $i <= $horizonMonths; $i++) {
            $monthKey = $cursor->format('Y-m');
            $contributions = (float) ($contributionsByMonth[$monthKey] ?? 0.0);
            $cumulative += $contributions;

            $series[] = [
                'month' => $monthKey,
                'label' => $cursor->locale('it')->translatedFormat('M y'),
                'contributions' => round($contributions, 2),
                'cumulative' => round($cumulative, 2),
            ];

            $cursor = $cursor->copy()->addMonth();
        }

        return [
            'horizon_months' => $horizonMonths,
            'monthly_total' => $monthlyTotal,
            'active_pac_count' => $pacs->count(),
            'series' => $series,
        ];
    }

    /**
     * @param  Collection<int, InvestmentPac>  $pacs
     * @return array<string, float>
     */
    private function buildMonthlyContributionsAt(Collection $pacs, int $horizonMonths, Carbon $asOfDate): array
    {
        $totals = [];
        $endDate = $asOfDate->copy()->startOfMonth()->addMonths($horizonMonths)->endOfMonth();

        foreach ($pacs as $pac) {
            $executionDate = $this->investmentPacService->calculateNextExecutionDate($pac, $asOfDate);

            while ($executionDate !== null && $executionDate->lte($endDate)) {
                if ($executionDate->gte($asOfDate->copy()->startOfDay())) {
                    $monthKey = $executionDate->copy()->startOfMonth()->format('Y-m');
                    $totals[$monthKey] = ($totals[$monthKey] ?? 0.0) + (float) $pac->amount;
                }

                $executionDate = $this->nextMonthlyExecutionAfter($pac, $executionDate);
            }
        }

        return $totals;
    }

    /**
     * @param  Collection<int, InvestmentPac>  $pacs
     * @return array<string, float>
     */
    private function buildMonthlyContributions(Collection $pacs, int $horizonMonths): array
    {
        return $this->buildMonthlyContributionsAt($pacs, $horizonMonths, Carbon::today());
    }

    private function nextMonthlyExecutionAfter(InvestmentPac $pac, Carbon $current): ?Carbon
    {
        $startDate = Carbon::parse($pac->start_date)->startOfDay();
        $preferredDay = (int) $startDate->day;
        $candidate = $current->copy()->startOfMonth()->addMonth();
        $daysInMonth = $candidate->daysInMonth;
        $candidate->day(min($preferredDay, $daysInMonth));

        if ($pac->end_date !== null && $candidate->gt(Carbon::parse($pac->end_date))) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array{
     *     horizon_months: int,
     *     monthly_total: float,
     *     active_pac_count: int,
     *     series: list<array{month: string, label: string, contributions: float, cumulative: float}>
     * }
     */
    private function emptyProjection(int $horizonMonths): array
    {
        return [
            'horizon_months' => $horizonMonths,
            'monthly_total' => 0.0,
            'active_pac_count' => 0,
            'series' => [],
        ];
    }
}
