<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Suffissi periodo in italiano per descrizioni transazioni generate da ricorrenze.
 */
class RecurringOccurrenceLabel
{
    private const MONTHS_IT = [
        1 => 'Gennaio',
        2 => 'Febbraio',
        3 => 'Marzo',
        4 => 'Aprile',
        5 => 'Maggio',
        6 => 'Giugno',
        7 => 'Luglio',
        8 => 'Agosto',
        9 => 'Settembre',
        10 => 'Ottobre',
        11 => 'Novembre',
        12 => 'Dicembre',
    ];

    public static function suffix(Carbon $date, string $frequency): string
    {
        return match ($frequency) {
            'monthly' => ' - '.self::MONTHS_IT[(int) $date->format('n')].' '.$date->format('Y'),
            'yearly' => ' - '.$date->format('Y'),
            'weekly' => ' - Settimana '.$date->isoWeek().'/'.$date->format('Y'),
            'daily' => ' - '.$date->format('d/m/Y'),
            default => ' - '.$date->format('m/Y'),
        };
    }

    public static function buildDescriptionWithOccurrence(?string $template, Carbon $date, string $frequency): string
    {
        $base = trim((string) $template);
        $suffix = self::suffix($date, $frequency);

        if ($base !== '' && self::alreadyHasOccurrenceSuffix($base, $date, $frequency)) {
            return $base;
        }

        return $base === '' ? ltrim($suffix, ' -') : $base.$suffix;
    }

    private static function alreadyHasOccurrenceSuffix(string $description, Carbon $date, string $frequency): bool
    {
        $expected = self::suffix($date, $frequency);

        return str_ends_with($description, $expected)
            || str_ends_with($description, ltrim($expected, ' -'));
    }
}
