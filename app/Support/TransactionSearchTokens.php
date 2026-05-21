<?php

namespace App\Support;

/**
 * Tokenizza una stringa di ricerca per filtri descrizione transazioni (italiano).
 */
class TransactionSearchTokens
{
    /** @var list<string> */
    private const STOPWORDS = [
        'a', 'ad', 'al', 'alla', 'alle', 'allo', 'ai', 'agli', 'all', 'dell', 'dello', 'della', 'delle', 'dei', 'degli',
        'con', 'per', 'su', 'da', 'di', 'in', 'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'e', 'o', 'che',
        'non', 'si', 'mi', 'ti', 'ci', 'vi', 'ho', 'ha', 'sono', 'è', 'era', 'the', 'of', 'and', 'to',
    ];

    /**
     * @return list<string> Token significativi (min 2 caratteri, no stopword)
     */
    public static function fromQuery(?string $query, int $maxLength = 120): array
    {
        if ($query === null || trim($query) === '') {
            return [];
        }

        $normalized = mb_strtolower(mb_substr(trim($query), 0, $maxLength));
        $parts = preg_split('/[\s\p{P}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 2) {
                continue;
            }
            if (in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }
}
