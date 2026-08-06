<?php

namespace App\Services;

/**
 * Avvisi non bloccanti sulla mappatura colonne per l'import transazioni.
 */
class TransactionImportColumnMappingAdvisor
{
    private const FIELD_LABELS = [
        'date' => 'Data',
        'amount' => 'Importo',
        'description' => 'Descrizione',
        'notes' => 'Note',
        'category' => 'Categoria',
        'account' => 'Conto',
        'currency' => 'Valuta',
    ];

    /**
     * @param  array<string, mixed>  $columnMapping
     * @param  array<int, string>  $headers
     * @return list<string>
     */
    public static function warnings(array $columnMapping, array $headers): array
    {
        $warnings = [];
        $headers = array_values($headers);

        $fields = ['date', 'amount', 'description', 'notes', 'category', 'account', 'currency'];
        $byIndex = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $columnMapping)) {
                continue;
            }
            $raw = $columnMapping[$field];
            if ($raw === null || $raw === '') {
                continue;
            }
            if (! is_numeric($raw)) {
                continue;
            }
            $idx = (int) $raw;
            if (! isset($byIndex[$idx])) {
                $byIndex[$idx] = [];
            }
            $byIndex[$idx][] = $field;
        }

        foreach ($byIndex as $idx => $mappedFields) {
            if (count($mappedFields) < 2) {
                continue;
            }
            $labels = array_map(
                fn (string $f): string => self::FIELD_LABELS[$f] ?? $f,
                $mappedFields,
            );
            $headerLabel = isset($headers[$idx]) && trim((string) $headers[$idx]) !== ''
                ? sprintf(' (intestazione: «%s»)', trim((string) $headers[$idx]))
                : '';
            $warnings[] = sprintf(
                'Più campi usano la stessa colonna %d%s: %s. Assegna una colonna diversa a ciascun campo.',
                $idx + 1,
                $headerLabel,
                implode(', ', $labels),
            );
        }

        $descRaw = $columnMapping['description'] ?? null;
        if ($descRaw === null || $descRaw === '' || ! is_numeric($descRaw)) {
            return $warnings;
        }
        $descIdx = (int) $descRaw;
        if (! isset($headers[$descIdx])) {
            return $warnings;
        }
        $headerText = mb_strtolower(trim((string) $headers[$descIdx]));
        if ($headerText === '') {
            return $warnings;
        }
        if (preg_match('/\b(categoria|categorie|category|tipo\s+movimento|classificazione|macro|gruppo)\b/u', $headerText)) {
            $warnings[] = sprintf(
                'La colonna scelta per «Descrizione» ha intestazione «%s» e di solito contiene categorie, non il testo del movimento. '.
                'Mappa quella colonna come «Categoria (opzionale)» e scegli un\'altra colonna per «Descrizione».',
                trim((string) $headers[$descIdx]),
            );
        }

        return $warnings;
    }
}
