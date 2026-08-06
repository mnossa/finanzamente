<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applica filtri sulla descrizione transazione (token LIKE o regex).
 */
class TransactionDescriptionFilter
{
    public static function apply(Builder $query, ?string $description, bool $useRegex = false): void
    {
        if ($description === null || trim($description) === '') {
            return;
        }

        if ($useRegex) {
            self::applyRegex($query, $description);

            return;
        }

        self::applyTokens($query, $description);
    }

    private static function applyTokens(Builder $query, string $description): void
    {
        $tokens = TransactionSearchTokens::fromQuery($description);
        $connection = $query->getConnection();

        foreach ($tokens as $token) {
            if (DatabaseDialect::supportsLikeEscape($connection)) {
                $pattern = '%'.self::escapeLikeToken($token).'%';
                $query->whereRaw('description LIKE ? ESCAPE ?', [$pattern, '\\']);
            } else {
                $query->where('description', 'like', '%'.$token.'%');
            }
        }
    }

    private static function applyRegex(Builder $query, string $description): void
    {
        $pattern = TransactionRegexSearchValidator::validate($description);
        if ($pattern === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $connection = $query->getConnection();
        $query->whereRaw(
            DatabaseDialect::columnRegexMatch('description', '?', $connection),
            [$pattern]
        );
    }

    private static function escapeLikeToken(string $token): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $token
        );
    }
}
