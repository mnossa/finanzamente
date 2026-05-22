<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Finestre di tolleranza per date ricorrenti (rilevamento, generazione, riconciliazione).
 */
class RecurrenceDateTolerance
{
    public const FREQUENCY_GAPS = [
        'daily' => 1,
        'weekly' => 7,
        'monthly' => 30,
        'yearly' => 365,
    ];

    /**
     * Finestra ± giorni per considerare due date nella stessa occorrenza.
     */
    public const FREQUENCY_WINDOWS = [
        'daily' => 0,
        'weekly' => 1,
        'monthly' => 7,
        'yearly' => 7,
    ];

    public static function windowDaysForFrequency(string $frequency): int
    {
        return self::FREQUENCY_WINDOWS[$frequency] ?? 0;
    }

    public static function expectedGapDaysForFrequency(string $frequency): int
    {
        return self::FREQUENCY_GAPS[$frequency] ?? 30;
    }

    public static function maxAllowedGapDays(string $frequency): int
    {
        return self::expectedGapDaysForFrequency($frequency) + self::windowDaysForFrequency($frequency);
    }

    /**
     * Gap minimo tra due occorrenze distinte (sotto questa soglia = stesso periodo).
     */
    public static function minimumGapBetweenPeriods(string $frequency): int
    {
        $expected = self::expectedGapDaysForFrequency($frequency);
        $window = self::windowDaysForFrequency($frequency);

        return max(1, $expected - $window);
    }

    public static function hasOccurrenceNearDate(
        int $recurringTransactionId,
        Carbon $date,
        string $frequency,
    ): bool {
        $window = self::windowDaysForFrequency($frequency);

        return Transaction::query()
            ->where('recurring_transaction_id', $recurringTransactionId)
            ->get(['date'])
            ->contains(function (Transaction $tx) use ($date, $frequency, $window) {
                $txDate = Carbon::parse($tx->date);

                return self::isWithinWindowAndPeriod($txDate, $date, $frequency, $window);
            });
    }

    /**
     * Indice della data attesa più vicina entro la finestra, oppure null.
     *
     * @param  Carbon[]  $expectedDates
     */
    public static function findMatchingExpectedSlotIndex(
        Carbon $transactionDate,
        array $expectedDates,
        string $frequency,
    ): ?int {
        $window = self::windowDaysForFrequency($frequency);
        $bestSamePeriodIndex = null;
        $bestSamePeriodDistance = PHP_INT_MAX;
        $bestAnyIndex = null;
        $bestAnyDistance = PHP_INT_MAX;

        foreach ($expectedDates as $index => $expected) {
            $distance = (int) abs($transactionDate->diffInDays($expected));
            if ($distance > $window) {
                continue;
            }

            if (self::isSamePeriod($transactionDate, $expected, $frequency) && $distance < $bestSamePeriodDistance) {
                $bestSamePeriodDistance = $distance;
                $bestSamePeriodIndex = $index;
            }

            if ($distance < $bestAnyDistance) {
                $bestAnyDistance = $distance;
                $bestAnyIndex = $index;
            }
        }

        return $bestSamePeriodIndex ?? $bestAnyIndex;
    }

    public static function isWithinWindowAndPeriod(
        Carbon $transactionDate,
        Carbon $expectedDate,
        string $frequency,
        ?int $window = null,
    ): bool {
        $window ??= self::windowDaysForFrequency($frequency);

        return abs($transactionDate->diffInDays($expectedDate)) <= $window
            && self::isSamePeriod($transactionDate, $expectedDate, $frequency);
    }

    public static function isSamePeriod(Carbon $transactionDate, Carbon $expectedDate, string $frequency): bool
    {
        return match ($frequency) {
            'daily' => $transactionDate->isSameDay($expectedDate),
            'weekly' => $transactionDate->isoWeekYear() === $expectedDate->isoWeekYear()
                && $transactionDate->isoWeek() === $expectedDate->isoWeek(),
            'monthly' => $transactionDate->year === $expectedDate->year
                && $transactionDate->month === $expectedDate->month,
            'yearly' => $transactionDate->year === $expectedDate->year,
            default => false,
        };
    }

    /**
     * Calcola occorrenze teoriche tra due date (senza aggiustamento giorni lavorativi).
     *
     * @return Carbon[]
     */
    public static function projectOccurrencesBetween(
        Carbon $startDate,
        Carbon $endDate,
        string $frequency,
    ): array {
        $occurrences = [];
        $seenDates = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->toDateString();
            if (! isset($seenDates[$dateKey])) {
                $occurrences[] = $currentDate->copy();
                $seenDates[$dateKey] = true;
            }

            match ($frequency) {
                'daily' => $currentDate->addDay(),
                'weekly' => $currentDate->addWeek(),
                'monthly' => $currentDate->addMonth(),
                'yearly' => $currentDate->addYear(),
                default => $currentDate->addMonth(),
            };
        }

        return $occurrences;
    }

    /**
     * Etichetta leggibile per un periodo mancante (italiano).
     */
    public static function labelForOccurrenceDate(Carbon $date, string $frequency): string
    {
        return match ($frequency) {
            'yearly' => $date->locale('it')->translatedFormat('F Y'),
            'monthly' => $date->locale('it')->translatedFormat('F Y'),
            'weekly' => 'settimana dal '.$date->locale('it')->translatedFormat('j F Y'),
            default => $date->locale('it')->translatedFormat('j F Y'),
        };
    }
}
