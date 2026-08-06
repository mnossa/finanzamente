<?php

namespace App\Services;

use App\Models\RecurringTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Giorni lavorativi in Italia (weekend + festività nazionali).
 */
class BusinessDayService
{
    /**
     * Posticipa la data al primo giorno lavorativo utile (inclusiva).
     */
    public function adjustToNextWorkingDay(Carbon $date): Carbon
    {
        $adjusted = $date->copy();

        while (! $this->isWorkingDay($adjusted)) {
            $adjusted->addDay();
        }

        return $adjusted;
    }

    /**
     * Anticipa la data al primo giorno lavorativo utile (inclusiva).
     */
    public function adjustToPreviousWorkingDay(Carbon $date): Carbon
    {
        $adjusted = $date->copy();

        while (! $this->isWorkingDay($adjusted)) {
            $adjusted->subDay();
        }

        return $adjusted;
    }

    public function adjustOccurrenceDate(Carbon $date, string $policy): Carbon
    {
        return match ($policy) {
            RecurringTransaction::NON_WORKING_DAY_POLICY_KEEP => $date->copy(),
            RecurringTransaction::NON_WORKING_DAY_POLICY_ANTICIPATE => $this->adjustToPreviousWorkingDay($date),
            default => $this->adjustToNextWorkingDay($date),
        };
    }

    public function isWorkingDay(Carbon $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! $this->isItalianHoliday($date);
    }

    public function isItalianHoliday(Carbon $date): bool
    {
        $md = $date->format('m-d');

        if (in_array($md, Config::get('holidays.italy_fixed', []), true)) {
            return true;
        }

        return $this->isEasterMonday($date);
    }

    private function isEasterMonday(Carbon $date): bool
    {
        $year = (int) $date->year;
        if (! function_exists('easter_date')) {
            return false;
        }

        $easterSunday = Carbon::createFromTimestamp(easter_date($year))->startOfDay();
        $easterMonday = $easterSunday->copy()->addDay();

        return $date->isSameDay($easterMonday);
    }
}
