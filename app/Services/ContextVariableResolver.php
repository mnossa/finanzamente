<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Resolves calendar and period-scoped numeric context (not tied to household ledger data).
 * Reference date is the period end (or bucket end in monthly series).
 */
class ContextVariableResolver
{
    public function resolve(Carbon $startDate, Carbon $endDate, string $field): float
    {
        $reference = $endDate->copy()->startOfDay();
        $periodStart = $startDate->copy()->startOfDay();

        return match ($field) {
            'year' => (float) $reference->year,
            'month' => (float) $reference->month,
            'day' => (float) $reference->day,
            'day_of_year' => (float) $reference->dayOfYear,
            'quarter' => (float) $reference->quarter,
            'iso_week' => (float) $reference->isoWeek,
            'days_in_month' => (float) $reference->daysInMonth,
            'days_in_year' => (float) $reference->daysInYear,
            'days_elapsed_in_month' => (float) $reference->day,
            'days_remaining_in_month' => (float) max(0, $reference->daysInMonth - $reference->day),
            'days_elapsed_in_year' => (float) $reference->dayOfYear,
            'days_remaining_in_year' => (float) max(0, $reference->daysInYear - $reference->dayOfYear),
            'days_in_period' => (float) max(1, $periodStart->diffInDays($reference) + 1),
            default => throw ValidationException::withMessages([
                'variable_code' => "Campo contesto [{$field}] non configurato.",
            ]),
        };
    }
}
