<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TransactionImportService
{
    /**
     * Predefined layouts for known banks.
     * column_mapping keys: date, amount, description, notes (optional)
     * Values are zero-based column indices (integers).
     */
    public function getPredefinedLayouts(): array
    {
        return [
            'intesa' => [
                'name' => 'Intesa Sanpaolo',
                'bank_name' => 'intesa',
                'delimiter' => ';',
                'date_format' => 'd/m/Y',
                'has_header' => true,
                'encoding' => 'UTF-8',
                'column_mapping' => [
                    'date' => 0,
                    'description' => 1,
                    'amount' => 2,
                    'notes' => null,
                ],
            ],
            'unicredit' => [
                'name' => 'UniCredit',
                'bank_name' => 'unicredit',
                'delimiter' => ';',
                'date_format' => 'd/m/Y',
                'has_header' => true,
                'encoding' => 'UTF-8',
                'column_mapping' => [
                    'date' => 0,
                    'description' => 2,
                    'amount' => 4,
                    'notes' => 3,
                ],
            ],
            'fineco' => [
                'name' => 'FinecoBank',
                'bank_name' => 'fineco',
                'delimiter' => ',',
                'date_format' => 'd/m/Y',
                'has_header' => true,
                'encoding' => 'UTF-8',
                'column_mapping' => [
                    'date' => 0,
                    'description' => 2,
                    'amount' => 4,
                    'notes' => 3,
                ],
            ],
            'banco_bpm' => [
                'name' => 'Banco BPM',
                'bank_name' => 'banco_bpm',
                'delimiter' => ';',
                'date_format' => 'd/m/Y',
                'has_header' => true,
                'encoding' => 'UTF-8',
                'column_mapping' => [
                    'date' => 0,
                    'description' => 1,
                    'amount' => 3,
                    'notes' => 2,
                ],
            ],
            'poste_pay' => [
                'name' => 'PostePay / Poste Italiane',
                'bank_name' => 'poste_pay',
                'delimiter' => ';',
                'date_format' => 'd/m/Y',
                'has_header' => true,
                'encoding' => 'UTF-8',
                'column_mapping' => [
                    'date' => 0,
                    'description' => 2,
                    'amount' => 3,
                    'notes' => null,
                ],
            ],
        ];
    }

    /**
     * Parse a CSV string using the given layout configuration.
     *
     * @param string $content Raw CSV content
     * @param array $layout { delimiter, date_format, has_header, encoding, column_mapping }
     * @return array Array of parsed rows: [{date, amount, description, notes, raw, errors}]
     */
    public function parseCsv(string $content, array $layout): array
    {
        $delimiter = $layout['delimiter'] ?? ',';
        $dateFormat = $layout['date_format'] ?? 'd/m/Y';
        $hasHeader = $layout['has_header'] ?? true;
        $encoding = $layout['encoding'] ?? 'UTF-8';
        $columnMapping = $layout['column_mapping'] ?? [];

        // Convert encoding if needed
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $lines = array_filter(explode("\n", $content), fn($line) => trim($line) !== '');
        $lines = array_values($lines);

        if (empty($lines)) {
            return [];
        }

        // Skip header row
        $startIndex = $hasHeader ? 1 : 0;

        $rows = [];
        for ($i = $startIndex; $i < count($lines); $i++) {
            $raw = $lines[$i];
            $cols = $this->parseCsvLine($raw, $delimiter);
            $rows[] = $this->mapColumns($cols, $columnMapping, $dateFormat, $raw, $i + 1);
        }

        return $rows;
    }

    /**
     * Parse a single CSV line respecting quoted fields.
     */
    private function parseCsvLine(string $line, string $delimiter): array
    {
        $result = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $line);
        rewind($handle);
        $row = fgetcsv($handle, 0, $delimiter, '"', '\\');
        fclose($handle);
        return $row !== false ? $row : [];
    }

    /**
     * Map parsed columns to normalized transaction fields.
     */
    private function mapColumns(array $cols, array $mapping, string $dateFormat, string $raw, int $lineNumber): array
    {
        $errors = [];

        // Extract fields using column indices
        $dateRaw = $this->getColumn($cols, $mapping['date'] ?? null);
        $amountRaw = $this->getColumn($cols, $mapping['amount'] ?? null);
        $descriptionRaw = $this->getColumn($cols, $mapping['description'] ?? null);
        $notesRaw = isset($mapping['notes']) && $mapping['notes'] !== null
            ? $this->getColumn($cols, $mapping['notes'])
            : null;

        // Parse date
        $date = null;
        if ($dateRaw !== null && $dateRaw !== '') {
            try {
                $date = Carbon::createFromFormat($dateFormat, trim($dateRaw))->format('Y-m-d');
            } catch (\Exception $e) {
                $errors[] = "Riga {$lineNumber}: formato data non valido ({$dateRaw})";
            }
        } else {
            $errors[] = "Riga {$lineNumber}: data mancante";
        }

        // Parse amount (handle Italian number format)
        $amount = null;
        if ($amountRaw !== null && $amountRaw !== '') {
            $amount = $this->parseAmount(trim($amountRaw));
            if ($amount === null) {
                $errors[] = "Riga {$lineNumber}: importo non valido ({$amountRaw})";
            }
        } else {
            $errors[] = "Riga {$lineNumber}: importo mancante";
        }

        // Description
        $description = $descriptionRaw !== null ? trim($descriptionRaw) : '';
        if ($description === '') {
            $errors[] = "Riga {$lineNumber}: descrizione mancante";
        }

        return [
            'line_number' => $lineNumber,
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'notes' => $notesRaw !== null ? trim($notesRaw) : null,
            'raw' => $raw,
            'errors' => $errors,
        ];
    }

    /**
     * Get a column value by index (or null if out of bounds).
     */
    private function getColumn(array $cols, ?int $index): ?string
    {
        if ($index === null || !isset($cols[$index])) {
            return null;
        }
        return $cols[$index];
    }

    /**
     * Parse Italian-formatted amount string to float.
     * Handles: "1.234,56", "1234,56", "-1.234,56", "1,234.56" (English format)
     */
    public function parseAmount(string $value): ?float
    {
        // Remove currency symbols and spaces
        $value = preg_replace('/[€$£\s]/', '', $value);
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return null;
        }

        // Detect format: if contains comma and dot, figure out which is decimal separator
        if (str_contains($value, ',') && str_contains($value, '.')) {
            // If comma comes after dot: English format (1,234.56)
            if (strrpos($value, '.') > strrpos($value, ',')) {
                $value = str_replace(',', '', $value);
            } else {
                // Italian format: 1.234,56
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
        } elseif (str_contains($value, ',')) {
            // Only comma: could be decimal (1234,56) or thousands (1,234 or 1,234,567)
            $commaCount = substr_count($value, ',');
            if ($commaCount > 1) {
                // Multiple commas: all are thousands separators (e.g., 1,234,567)
                $value = str_replace(',', '', $value);
            } else {
                $afterComma = substr($value, strrpos($value, ',') + 1);
                // In EUR banking context, decimal amounts have at most 2 decimal places.
                // 3 digits after comma is treated as a thousands separator (e.g., 1,234 → 1234).
                if (strlen($afterComma) === 3 && ctype_digit($afterComma)) {
                    // Thousands separator: 1,234 -> 1234
                    $value = str_replace(',', '', $value);
                } else {
                    // Decimal separator: 1234,56 -> 1234.56
                    $value = str_replace(',', '.', $value);
                }
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Validate parsed rows and return only valid ones along with error summary.
     */
    public function validateRows(array $rows): array
    {
        $valid = [];
        $invalid = [];

        foreach ($rows as $row) {
            if (empty($row['errors']) && $row['date'] !== null && $row['amount'] !== null && $row['description'] !== '') {
                $valid[] = $row;
            } else {
                $invalid[] = $row;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }
}
