<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class TransactionImportService
{
    /**
     * Parse a CSV string using the given layout configuration.
     *
     * @param  string  $content  Raw CSV content
     * @param  array  $layout  { delimiter, date_format, has_header, encoding, column_mapping }
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

        $lines = array_filter(explode("\n", $content), fn ($line) => trim($line) !== '');
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
            if ($this->isCompletelyEmptyCsvRow($cols)) {
                continue;
            }
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
        $warnings = [];

        $dateRaw = $this->getColumn($cols, $mapping['date'] ?? null);
        $amountRaw = $this->getColumn($cols, $mapping['amount'] ?? null);
        $descriptionRaw = $this->getColumn($cols, $mapping['description'] ?? null);
        $notesRaw = $this->getColumn($cols, $mapping['notes'] ?? null);
        $categoryRaw = $this->getColumn($cols, $mapping['category'] ?? null);
        $accountRaw = $this->getColumn($cols, $mapping['account'] ?? null);
        $currencyRaw = $this->getColumn($cols, $mapping['currency'] ?? null);

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

        return [
            'line_number' => $lineNumber,
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'notes' => $notesRaw !== null ? trim($notesRaw) : null,
            'category_name' => ($categoryRaw !== null && trim($categoryRaw) !== '') ? trim($categoryRaw) : null,
            'account_name' => ($accountRaw !== null && trim($accountRaw) !== '') ? trim($accountRaw) : null,
            'currency_code' => ($currencyRaw !== null && trim($currencyRaw) !== '') ? strtoupper(trim($currencyRaw)) : null,
            'raw' => $raw,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get a column value by index (or null if out of bounds).
     */
    private function getColumn(array $cols, ?int $index): ?string
    {
        if ($index === null || ! isset($cols[$index])) {
            return null;
        }

        return $cols[$index];
    }

    /**
     * True if every CSV column is empty/blank.
     */
    private function isCompletelyEmptyCsvRow(array $cols): bool
    {
        foreach ($cols as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
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

        if (! is_numeric($value)) {
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
            if (empty($row['errors']) && $row['date'] !== null && $row['amount'] !== null) {
                $valid[] = $row;
            } else {
                $invalid[] = $row;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Legge i nomi dei fogli presenti in un file XLSX.
     *
     * @param  string  $filePath  Percorso assoluto al file .xlsx
     * @return array<int, array{index: int, name: string}>
     */
    public function getXlsxSheets(string $filePath): array
    {
        $reader = new XlsxReader;
        $reader->open($filePath);

        $sheets = [];
        $index = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[] = ['index' => $index, 'name' => $sheet->getName()];
            $index++;
        }

        $reader->close();

        return $sheets;
    }

    /**
     * Legge la prima riga di un file XLSX come intestazioni di colonna.
     *
     * @param  string  $filePath  Percorso assoluto al file .xlsx
     * @param  int  $sheetIndex  Indice (0-based) del foglio da leggere (default 0)
     */
    public function getXlsxHeaders(string $filePath, int $sheetIndex = 0): array
    {
        $reader = new XlsxReader;
        $reader->open($filePath);

        $headers = [];
        $sheetCount = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheetCount !== $sheetIndex) {
                $sheetCount++;

                continue;
            }
            foreach ($sheet->getRowIterator() as $row) {
                $headers = array_map(
                    fn ($value) => $this->cellValueToString($value),
                    $row->toArray(),
                );
                break;
            }
            break;
        }

        $reader->close();

        return $headers;
    }

    /**
     * Legge un file XLSX e restituisce le righe nel formato normalizzato.
     *
     * @param  string  $filePath  Percorso assoluto al file .xlsx
     * @param  array  $layout  { date_format, has_header, column_mapping }
     * @param  int  $sheetIndex  Indice (0-based) del foglio da leggere (default 0)
     * @return array Array di righe: [{date, amount, description, notes, raw, errors}]
     */
    public function parseXlsx(string $filePath, array $layout, int $sheetIndex = 0): array
    {
        $dateFormat = $layout['date_format'] ?? 'd/m/Y';
        $hasHeader = $layout['has_header'] ?? true;
        $columnMapping = $layout['column_mapping'] ?? [];

        $reader = new XlsxReader;
        $reader->open($filePath);

        $rows = [];
        $lineNumber = 0;
        $sheetCount = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheetCount !== $sheetIndex) {
                $sheetCount++;

                continue;
            }
            foreach ($sheet->getRowIterator() as $row) {
                $lineNumber++;
                if ($hasHeader && $lineNumber === 1) {
                    continue; // riga di intestazione ignorata
                }
                if ($this->isCompletelyEmptyXlsxRow($row->toArray())) {
                    continue;
                }
                $rows[] = $this->mapXlsxRow($row->cells, $columnMapping, $dateFormat, $lineNumber);
            }
            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * Mappa una riga XLSX (array di Cell) ai campi normalizzati della transazione.
     * Gestisce celle DateTime (date native Excel), numeriche e testuali.
     */
    /**
     * Converte in modo sicuro il valore di una cella XLSX in stringa.
     * DateTimeImmutable/DateTime non implementano __toString: li formattiamo esplicitamente.
     */
    private function cellValueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        return (string) $value;
    }

    private function mapXlsxRow(array $cells, array $mapping, string $dateFormat, int $lineNumber): array
    {
        $errors = [];

        $getCell = fn (?int $idx) => ($idx !== null && isset($cells[$idx])) ? $cells[$idx] : null;

        $dateCell = $getCell($mapping['date'] ?? 0);
        $amountCell = $getCell($mapping['amount'] ?? 1);
        $descCell = $getCell($mapping['description'] ?? 2);
        $notesCell = $getCell(isset($mapping['notes']) ? (int) $mapping['notes'] : null);
        $categoryCell = $getCell(isset($mapping['category']) ? (int) $mapping['category'] : null);
        $accountCell = $getCell(isset($mapping['account']) ? (int) $mapping['account'] : null);
        $currencyCell = $getCell(isset($mapping['currency']) ? (int) $mapping['currency'] : null);

        // ── Data ────────────────────────────────────────────────────────────
        $date = null;
        $dateValue = $dateCell?->getValue();
        if ($dateValue instanceof DateTimeInterface) {
            // Excel memorizza date come oggetti DateTimeImmutable nativi
            $date = Carbon::instance($dateValue)->format('Y-m-d');
        } elseif ($dateValue !== null && trim($this->cellValueToString($dateValue)) !== '') {
            try {
                $date = Carbon::createFromFormat($dateFormat, trim($this->cellValueToString($dateValue)))->format('Y-m-d');
            } catch (\Exception) {
                $errors[] = "Riga {$lineNumber}: formato data non valido ({$this->cellValueToString($dateValue)})";
            }
        } else {
            $errors[] = "Riga {$lineNumber}: data mancante";
        }

        // ── Importo ─────────────────────────────────────────────────────────
        $amount = null;
        $amountValue = $amountCell?->getValue();
        if (is_float($amountValue) || is_int($amountValue)) {
            $amount = (float) $amountValue;
        } elseif ($amountValue !== null && ! ($amountValue instanceof DateTimeInterface)) {
            $amountStr = trim($this->cellValueToString($amountValue));
            if ($amountStr !== '') {
                $amount = $this->parseAmount($amountStr);
                if ($amount === null) {
                    $errors[] = "Riga {$lineNumber}: importo non valido ({$amountStr})";
                }
            } else {
                $errors[] = "Riga {$lineNumber}: importo mancante";
            }
        } else {
            $errors[] = "Riga {$lineNumber}: importo mancante";
        }

        // ── Descrizione / Note ───────────────────────────────────────────────
        $description = $descCell !== null ? trim($this->cellValueToString($descCell->getValue())) : '';
        $notesRaw = $notesCell !== null ? trim($this->cellValueToString($notesCell->getValue())) : null;
        $categoryRaw = $categoryCell !== null ? trim($this->cellValueToString($categoryCell->getValue())) : null;
        $accountRaw = $accountCell !== null ? trim($this->cellValueToString($accountCell->getValue())) : null;
        $currencyRaw = $currencyCell !== null ? trim($this->cellValueToString($currencyCell->getValue())) : null;

        return [
            'line_number' => $lineNumber,
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'notes' => ($notesRaw !== null && $notesRaw !== '') ? $notesRaw : null,
            'category_name' => ($categoryRaw !== null && $categoryRaw !== '') ? $categoryRaw : null,
            'account_name' => ($accountRaw !== null && $accountRaw !== '') ? $accountRaw : null,
            'currency_code' => ($currencyRaw !== null && $currencyRaw !== '') ? strtoupper($currencyRaw) : null,
            'raw' => "Riga {$lineNumber}",
            'errors' => $errors,
        ];
    }

    /**
     * True if every XLSX cell value is empty/blank.
     */
    private function isCompletelyEmptyXlsxRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($this->cellValueToString($value)) !== '') {
                return false;
            }
        }

        return true;
    }
}
