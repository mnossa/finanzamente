<?php

namespace App\Support;

/**
 * Valida pattern regex per filtri descrizione transazioni (sintassi MySQL REGEXP / PCRE).
 */
class TransactionRegexSearchValidator
{
    private const MAX_LENGTH = 120;

    /** Pattern pericolosi o non supportati in contesto utente. */
    private const DENYLIST_FRAGMENTS = [
        '(?{',
        '(??',
        '(?P',
        '(?&',
        '(?R',
        '(?0',
        '(?1',
        '(?2',
        '(?3',
        '(?4',
        '(?5',
        '(?6',
        '(?7',
        '(?8',
        '(?9',
    ];

    /**
     * @return string|null Pattern validato oppure null se assente/invalido
     */
    public static function validate(?string $pattern): ?string
    {
        if ($pattern === null) {
            return null;
        }

        $trimmed = trim($pattern);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_LENGTH) {
            return null;
        }

        foreach (self::DENYLIST_FRAGMENTS as $fragment) {
            if (stripos($trimmed, $fragment) !== false) {
                return null;
            }
        }

        if (preg_match('/\([^)]*\+[^)]*\)\+/', $trimmed)) {
            return null;
        }

        $previous = set_error_handler(static fn () => true);
        try {
            if (@preg_match('@^(?:'.$trimmed.')$@u', '') === false) {
                return null;
            }
        } finally {
            if ($previous !== null) {
                set_error_handler($previous);
            } else {
                restore_error_handler();
            }
        }

        return $trimmed;
    }
}
