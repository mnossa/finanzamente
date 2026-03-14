<?php

namespace App\Services;

use App\Models\InvestmentAsset;
use Carbon\Carbon;

/**
 * Servizio per il parsing, la validazione e l'importazione di investimenti da CSV/XLSX.
 * Riutilizza la logica di parsing di TransactionImportService per CSV/XLSX.
 */
class InvestmentImportService
{
    /**
     * Colonne necessarie nel mapping investimenti:
     * - buy_date  (obbligatoria)
     * - quantity  (obbligatoria)
     * - buy_price (obbligatoria)
     * - ticker    (obbligatoria se isin non presente)
     * - isin      (obbligatoria se ticker non presente)
     * - fees      (opzionale)
     * - notes     (opzionale)
     */

    /**
     * Parsa un CSV in righe investimento normalizzate.
     *
     * @param string $content  Contenuto raw CSV
     * @param array  $layout   { delimiter, date_format, has_header, encoding, column_mapping }
     * @return array<int, array>
     */
    public function parseCsv(string $content, array $layout): array
    {
        $delimiter     = $layout['delimiter']      ?? ',';
        $dateFormat    = $layout['date_format']    ?? 'd/m/Y';
        $hasHeader     = $layout['has_header']     ?? true;
        $encoding      = $layout['encoding']       ?? 'UTF-8';
        $columnMapping = $layout['column_mapping'] ?? [];

        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines   = array_values(array_filter(explode("\n", $content), fn ($l) => trim($l) !== ''));

        if (empty($lines)) {
            return [];
        }

        $startIndex = $hasHeader ? 1 : 0;
        $rows       = [];

        for ($i = $startIndex; $i < count($lines); $i++) {
            $raw   = $lines[$i];
            $cols  = $this->parseCsvLine($raw, $delimiter);
            $rows[] = $this->mapColumns($cols, $columnMapping, $dateFormat, $raw, $i + 1);
        }

        return $rows;
    }

    /**
     * Parsa un file XLSX in righe investimento normalizzate.
     *
     * @param string $filePath Percorso assoluto al file .xlsx
     * @param array  $layout   { date_format, has_header, column_mapping }
     * @return array<int, array>
     */
    public function parseXlsx(string $filePath, array $layout): array
    {
        $dateFormat    = $layout['date_format']    ?? 'd/m/Y';
        $hasHeader     = $layout['has_header']     ?? true;
        $columnMapping = $layout['column_mapping'] ?? [];

        $reader     = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($filePath);

        $rows       = [];
        $lineNumber = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $lineNumber++;
                if ($hasHeader && $lineNumber === 1) {
                    continue;
                }
                $rows[] = $this->mapXlsxRow($row->getCells(), $columnMapping, $dateFormat, $lineNumber);
            }
            break;
        }

        $reader->close();
        return $rows;
    }

    /**
     * Restituisce la prima riga di intestazioni da un file XLSX.
     *
     * @return string[]
     */
    public function getXlsxHeaders(string $filePath): array
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($filePath);

        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $headers = array_map(
                    fn ($cell) => (string) ($cell->getValue() ?? ''),
                    $row->getCells(),
                );
                break;
            }
            break;
        }

        $reader->close();
        return $headers;
    }

    /**
     * Valida le righe parsate e separa quelle valide da quelle invalide.
     *
     * @param array<int, array> $rows
     * @return array{ valid: array, invalid: array }
     */
    public function validateRows(array $rows): array
    {
        $valid   = [];
        $invalid = [];

        foreach ($rows as $row) {
            if (
                empty($row['errors'])
                && $row['buy_date']   !== null
                && $row['quantity']   !== null
                && $row['buy_price']  !== null
                && ($row['ticker'] !== null || $row['isin'] !== null)
            ) {
                $valid[] = $row;
            } else {
                $invalid[] = $row;
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * Per ogni riga valida, tenta la risoluzione dell'asset tramite ticker o ISIN.
     * Restituisce le righe arricchite con asset_id (o null + asset_missing = true).
     *
     * @param array<int, array> $rows Righe già validate (senza errori di parsing)
     * @return array<int, array>
     */
    public function resolveAssets(array $rows): array
    {
        $allAssets = InvestmentAsset::select(['id', 'symbol', 'isin', 'name', 'type', 'currency_code'])->get();

        $bySymbol = $allAssets->keyBy(fn ($a) => strtoupper($a->symbol ?? ''));
        $byIsin   = $allAssets->keyBy(fn ($a) => strtoupper($a->isin   ?? ''));

        foreach ($rows as &$row) {
            $asset = null;

            if (!empty($row['ticker'])) {
                $asset = $bySymbol->get(strtoupper($row['ticker']));
            }

            if ($asset === null && !empty($row['isin'])) {
                $asset = $byIsin->get(strtoupper($row['isin']));
            }

            if ($asset !== null) {
                $row['asset_id']      = $asset->id;
                $row['asset_name']    = $asset->name;
                $row['asset_symbol']  = $asset->symbol;
                $row['asset_missing'] = false;
            } else {
                $row['asset_id']      = null;
                $row['asset_name']    = null;
                $row['asset_symbol']  = null;
                $row['asset_missing'] = true;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Parsa una singola riga CSV rispettando le virgolette.
     *
     * @return string[]
     */
    private function parseCsvLine(string $line, string $delimiter): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $line);
        rewind($handle);
        $row = fgetcsv($handle, 0, $delimiter, '"', '\\');
        fclose($handle);
        return $row !== false ? $row : [];
    }

    /**
     * Mappa le colonne di una riga CSV ai campi normalizzati dell'investimento.
     */
    private function mapColumns(array $cols, array $mapping, string $dateFormat, string $raw, int $lineNumber): array
    {
        $errors = [];

        $getCol = fn (?int $idx): ?string => ($idx !== null && isset($cols[$idx])) ? $cols[$idx] : null;

        $dateRaw     = $getCol($mapping['buy_date']  ?? $mapping['date']       ?? null);
        $quantityRaw = $getCol($mapping['quantity']  ?? null);
        $priceRaw    = $getCol($mapping['buy_price'] ?? $mapping['price']      ?? null);
        $tickerRaw   = $getCol($mapping['ticker']    ?? null);
        $isinRaw     = $getCol($mapping['isin']      ?? null);
        $feesRaw     = $getCol($mapping['fees']      ?? null);
        $notesRaw    = $getCol($mapping['notes']     ?? null);

        // Data acquisto
        $buyDate = null;
        if ($dateRaw !== null && $dateRaw !== '') {
            try {
                $buyDate = Carbon::createFromFormat($dateFormat, trim($dateRaw))->format('Y-m-d');
            } catch (\Exception) {
                $errors[] = "Riga {$lineNumber}: formato data non valido ({$dateRaw})";
            }
        } else {
            $errors[] = "Riga {$lineNumber}: data di acquisto mancante";
        }

        // Quantità
        $quantity = null;
        if ($quantityRaw !== null && $quantityRaw !== '') {
            $quantity = $this->parseDecimal(trim($quantityRaw));
            if ($quantity === null || $quantity <= 0) {
                $errors[] = "Riga {$lineNumber}: quantità non valida ({$quantityRaw})";
                $quantity = null;
            }
        } else {
            $errors[] = "Riga {$lineNumber}: quantità mancante";
        }

        // Prezzo di acquisto
        $buyPrice = null;
        if ($priceRaw !== null && $priceRaw !== '') {
            $buyPrice = $this->parseDecimal(trim($priceRaw));
            if ($buyPrice === null || $buyPrice < 0) {
                $errors[] = "Riga {$lineNumber}: prezzo di acquisto non valido ({$priceRaw})";
                $buyPrice = null;
            }
        } else {
            $errors[] = "Riga {$lineNumber}: prezzo di acquisto mancante";
        }

        // Ticker / ISIN (almeno uno obbligatorio)
        $ticker = ($tickerRaw !== null && trim($tickerRaw) !== '') ? strtoupper(trim($tickerRaw)) : null;
        $isin   = ($isinRaw   !== null && trim($isinRaw)   !== '') ? strtoupper(trim($isinRaw))   : null;

        if ($ticker === null && $isin === null) {
            $errors[] = "Riga {$lineNumber}: ticker o ISIN obbligatorio";
        }

        // Commissioni (opzionale)
        $fees = null;
        if ($feesRaw !== null && trim($feesRaw) !== '') {
            $fees = $this->parseDecimal(trim($feesRaw));
            if ($fees === null || $fees < 0) {
                $errors[] = "Riga {$lineNumber}: commissioni non valide ({$feesRaw})";
                $fees = null;
            }
        }

        return [
            'line_number' => $lineNumber,
            'buy_date'    => $buyDate,
            'quantity'    => $quantity,
            'buy_price'   => $buyPrice,
            'ticker'      => $ticker,
            'isin'        => $isin,
            'fees'        => $fees,
            'notes'       => ($notesRaw !== null && trim($notesRaw) !== '') ? trim($notesRaw) : null,
            'raw'         => $raw,
            'errors'      => $errors,
        ];
    }

    /**
     * Mappa una riga XLSX (array di Cell) ai campi normalizzati dell'investimento.
     */
    private function mapXlsxRow(array $cells, array $mapping, string $dateFormat, int $lineNumber): array
    {
        $errors = [];

        $getCell = fn (?int $idx) => ($idx !== null && isset($cells[$idx])) ? $cells[$idx] : null;

        $dateCell     = $getCell($mapping['buy_date']  ?? $mapping['date']  ?? null);
        $quantityCell = $getCell($mapping['quantity']  ?? null);
        $priceCell    = $getCell($mapping['buy_price'] ?? $mapping['price'] ?? null);
        $tickerCell   = $getCell($mapping['ticker']    ?? null);
        $isinCell     = $getCell($mapping['isin']      ?? null);
        $feesCell     = $getCell($mapping['fees']      ?? null);
        $notesCell    = $getCell($mapping['notes']     ?? null);

        $cellStr = function ($cell): string {
            if ($cell === null) {
                return '';
            }
            $v = $cell->getValue();
            if ($v === null) {
                return '';
            }
            if ($v instanceof \DateTimeInterface) {
                return Carbon::instance($v)->format('Y-m-d');
            }
            return (string) $v;
        };

        // Data acquisto
        $buyDate    = null;
        $dateValue  = $dateCell?->getValue();
        if ($dateValue instanceof \DateTimeInterface) {
            $buyDate = Carbon::instance($dateValue)->format('Y-m-d');
        } elseif ($dateValue !== null && trim($cellStr($dateCell)) !== '') {
            try {
                $buyDate = Carbon::createFromFormat($dateFormat, trim($cellStr($dateCell)))->format('Y-m-d');
            } catch (\Exception) {
                $errors[] = "Riga {$lineNumber}: formato data non valido ({$cellStr($dateCell)})";
            }
        } else {
            $errors[] = "Riga {$lineNumber}: data di acquisto mancante";
        }

        // Quantità
        $quantity    = null;
        $quantityVal = $quantityCell?->getValue();
        if (is_float($quantityVal) || is_int($quantityVal)) {
            $quantity = (float) $quantityVal;
        } elseif ($quantityVal !== null && !($quantityVal instanceof \DateTimeInterface)) {
            $str = trim($cellStr($quantityCell));
            if ($str !== '') {
                $quantity = $this->parseDecimal($str);
            }
        }
        if ($quantity === null || $quantity <= 0) {
            $errors[] = $quantity === null
                ? "Riga {$lineNumber}: quantità mancante"
                : "Riga {$lineNumber}: quantità non valida";
            $quantity = null;
        }

        // Prezzo
        $buyPrice  = null;
        $priceVal  = $priceCell?->getValue();
        if (is_float($priceVal) || is_int($priceVal)) {
            $buyPrice = (float) $priceVal;
        } elseif ($priceVal !== null && !($priceVal instanceof \DateTimeInterface)) {
            $str = trim($cellStr($priceCell));
            if ($str !== '') {
                $buyPrice = $this->parseDecimal($str);
            }
        }
        if ($buyPrice === null || $buyPrice < 0) {
            $errors[] = $buyPrice === null
                ? "Riga {$lineNumber}: prezzo di acquisto mancante"
                : "Riga {$lineNumber}: prezzo di acquisto non valido";
            $buyPrice = null;
        }

        // Ticker / ISIN
        $tickerStr = trim($cellStr($tickerCell));
        $isinStr   = trim($cellStr($isinCell));
        $ticker    = $tickerStr !== '' ? strtoupper($tickerStr) : null;
        $isin      = $isinStr   !== '' ? strtoupper($isinStr)   : null;

        if ($ticker === null && $isin === null) {
            $errors[] = "Riga {$lineNumber}: ticker o ISIN obbligatorio";
        }

        // Commissioni
        $fees    = null;
        $feesVal = $feesCell?->getValue();
        if (is_float($feesVal) || is_int($feesVal)) {
            $fees = (float) $feesVal;
        } elseif ($feesVal !== null && !($feesVal instanceof \DateTimeInterface)) {
            $str = trim($cellStr($feesCell));
            if ($str !== '') {
                $fees = $this->parseDecimal($str);
            }
        }

        $notesStr = trim($cellStr($notesCell));

        return [
            'line_number' => $lineNumber,
            'buy_date'    => $buyDate,
            'quantity'    => $quantity,
            'buy_price'   => $buyPrice,
            'ticker'      => $ticker,
            'isin'        => $isin,
            'fees'        => ($fees !== null && $fees >= 0) ? $fees : null,
            'notes'       => $notesStr !== '' ? $notesStr : null,
            'raw'         => "Riga {$lineNumber}",
            'errors'      => $errors,
        ];
    }

    /**
     * Parsa un numero decimale (formato italiano o inglese).
     * Supporta: "1.234,56", "1234,56", "1,234.56", "1234.56".
     */
    public function parseDecimal(string $value): ?float
    {
        $value = preg_replace('/[€$£\s]/', '', $value);
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, '.') > strrpos($value, ',')) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
        } elseif (str_contains($value, ',')) {
            $commaCount = substr_count($value, ',');
            if ($commaCount > 1) {
                $value = str_replace(',', '', $value);
            } else {
                $afterComma = substr($value, strrpos($value, ',') + 1);
                if (strlen($afterComma) === 3 && ctype_digit($afterComma)) {
                    $value = str_replace(',', '', $value);
                } else {
                    $value = str_replace(',', '.', $value);
                }
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
