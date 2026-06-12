<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class FormulaPeriodResolver
{
    public function __construct(
        private readonly NetWorthSeriesService $netWorthSeriesService,
    ) {}

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function resolve(string $preset, User $user): array
    {
        $presets = config('financial_variables.period_presets', []);

        if (! array_key_exists($preset, $presets)) {
            throw ValidationException::withMessages([
                'period_preset' => 'Il periodo selezionato non è valido.',
            ]);
        }

        $end = Carbon::now()->endOfDay();
        $label = $presets[$preset]['label'] ?? $preset;

        $start = match ($preset) {
            'current_month' => Carbon::now()->startOfMonth()->startOfDay(),
            'rolling_30' => Carbon::now()->subDays(29)->startOfDay(),
            'full_history' => $this->resolveFullHistoryStart($user),
            'calendar_ytd' => Carbon::now()->startOfYear()->startOfDay(),
            default => throw ValidationException::withMessages([
                'period_preset' => 'Il periodo selezionato non è supportato.',
            ]),
        };

        return $this->normalize($start, $end, $label);
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function resolveExplicit(Carbon $startDate, Carbon $endDate, ?string $label = null): array
    {
        return $this->normalize($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay(), $label ?? 'Periodo personalizzato');
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function previousPeriod(Carbon $startDate, Carbon $endDate): array
    {
        $days = $startDate->diffInDays($endDate) + 1;
        $prevEnd = $startDate->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return $this->normalize($prevStart, $prevEnd, 'Periodo precedente');
    }

    /**
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    public function monthBuckets(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $maxMonths = (int) config('financial_variables.max_series_months', 24);
        $start = $rangeStart->copy()->startOfMonth();
        $end = $rangeEnd->copy()->endOfMonth();

        $monthSpan = ($start->year * 12 + $start->month);
        $endMonth = ($end->year * 12 + $end->month);
        $totalMonths = $endMonth - $monthSpan + 1;

        // Mostra sempre gli ultimi N mesi (fino a oggi), non i primi N dello storico.
        if ($totalMonths > $maxMonths) {
            $start = $end->copy()->subMonths($maxMonths - 1)->startOfMonth();
        }

        $buckets = [];
        $cursor = $start->copy();

        while ($cursor->lte($end) && count($buckets) < $maxMonths) {
            $bucketStart = $cursor->copy()->startOfMonth()->max($rangeStart);
            $bucketEnd = $cursor->copy()->endOfMonth()->min($rangeEnd);

            $buckets[] = [
                'start' => $bucketStart->copy()->startOfDay(),
                'end' => $bucketEnd->copy()->endOfDay(),
                'label' => $cursor->translatedFormat('M Y'),
            ];

            $cursor->addMonth()->startOfMonth();
        }

        return $buckets;
    }

    private function resolveFullHistoryStart(User $user): Carbon
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return Carbon::now()->subYear()->startOfMonth();
        }

        return $this->netWorthSeriesService->resolveHistoryStartDate($householdId, $user->id);
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    private function normalize(Carbon $start, Carbon $end, string $label): array
    {
        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $today = Carbon::now()->endOfDay();
        if ($end->gt($today)) {
            $end = $today;
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }
}
