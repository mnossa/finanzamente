<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Espressioni SQL portabili tra SQLite (test) e MySQL (produzione).
 */
class DatabaseDialect
{
    public static function driver(?Connection $connection = null): string
    {
        return ($connection ?? DB::connection())->getDriverName();
    }

    public static function yearExpr(string $column = 'date', ?Connection $connection = null): string
    {
        return self::driver($connection) === 'sqlite'
            ? "CAST(strftime('%Y', {$column}) AS INTEGER)"
            : "YEAR({$column})";
    }

    public static function monthExpr(string $column = 'date', ?Connection $connection = null): string
    {
        return self::driver($connection) === 'sqlite'
            ? "CAST(strftime('%m', {$column}) AS INTEGER)"
            : "MONTH({$column})";
    }

    public static function yearMonthExpr(string $column = 'date', ?Connection $connection = null): string
    {
        return self::driver($connection) === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /**
     * Condizione regex sulla colonna descrizione (pattern come binding).
     */
    public static function columnRegexMatch(string $column, string $patternPlaceholder = '?', ?Connection $connection = null): string
    {
        return self::driver($connection) === 'sqlite'
            ? "regexp({$patternPlaceholder}, {$column}) = 1"
            : "{$column} REGEXP {$patternPlaceholder}";
    }

    public static function supportsLikeEscape(?Connection $connection = null): bool
    {
        return self::driver($connection) === 'mysql';
    }
}
