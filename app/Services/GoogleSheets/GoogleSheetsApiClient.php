<?php

namespace App\Services\GoogleSheets;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Google Sheets + Drive client using a service-account JWT (no google/apiclient).
 */
class GoogleSheetsApiClient
{
    private ?string $accessToken = null;

    private int $tokenExpiresAt = 0;

    /**
     * @param  array{type?: string, project_id?: string, private_key_id?: string, private_key: string, client_email: string, client_id?: string}  $credentials
     */
    public function __construct(
        private readonly array $credentials,
    ) {
        if (! isset($this->credentials['client_email'], $this->credentials['private_key'])) {
            throw new RuntimeException('Credenziali service account Google incomplete (client_email / private_key).');
        }
    }

    public static function fromCredentialsFile(string $path): self
    {
        $resolved = self::resolveCredentialsPath($path);
        if (! is_file($resolved)) {
            throw new RuntimeException("File credenziali Google non trovato: {$path} (risolto: {$resolved})");
        }

        $json = file_get_contents($resolved);
        if ($json === false) {
            throw new RuntimeException("Impossibile leggere credenziali Google: {$resolved}");
        }

        /** @var array<string, mixed> $credentials */
        $credentials = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new self($credentials);
    }

    public function clientEmail(): string
    {
        return (string) $this->credentials['client_email'];
    }

    /**
     * @param  list<string>  $sheetTitles
     * @return array{spreadsheetId: string, spreadsheetUrl: string}
     */
    public function createSpreadsheet(string $title, array $sheetTitles): array
    {
        $sheets = [];
        foreach (array_values($sheetTitles) as $index => $sheetTitle) {
            $sheets[] = [
                'properties' => [
                    'title' => $sheetTitle,
                    'index' => $index,
                    'gridProperties' => [
                        'rowCount' => 1000,
                        'columnCount' => 40,
                    ],
                ],
            ];
        }

        $response = Http::withToken($this->token())
            ->timeout(60)
            ->post('https://sheets.googleapis.com/v4/spreadsheets', [
                'properties' => [
                    'title' => $title,
                    'locale' => 'it_IT',
                    'timeZone' => 'Europe/Rome',
                ],
                'sheets' => $sheets,
            ]);

        if (! $response->successful()) {
            $hint = $response->status() === 403
                ? ' Abilita Google Sheets API sul progetto del service account, oppure crea un foglio a mano,'
                    .' condividilo come Editor con '.$this->clientEmail()
                    .' e rilancia con --spreadsheet-id=ID.'
                : '';

            throw new RuntimeException(
                'Creazione spreadsheet fallita ('.$response->status().'): '.$response->body().$hint
            );
        }

        /** @var array{spreadsheetId: string, spreadsheetUrl?: string} $data */
        $data = $response->json();

        return [
            'spreadsheetId' => $data['spreadsheetId'],
            'spreadsheetUrl' => $data['spreadsheetUrl'] ?? 'https://docs.google.com/spreadsheets/d/'.$data['spreadsheetId'],
        ];
    }

    /**
     * Wipe spreadsheet completely and recreate only the requested tabs (clean formatting).
     *
     * @param  list<string>  $sheetTitles
     * @return array{spreadsheetId: string, spreadsheetUrl: string}
     */
    public function prepareExistingSpreadsheet(string $spreadsheetId, array $sheetTitles): array
    {
        if ($sheetTitles === []) {
            throw new RuntimeException('Nessun foglio da creare.');
        }

        $response = Http::withToken($this->token())
            ->timeout(60)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}", [
                'fields' => 'spreadsheetId,spreadsheetUrl,sheets.properties,sheets.charts',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Lettura spreadsheet fallita ('.$response->status().'): '.$response->body()
                .' Condividi il foglio come Editor con '.$this->clientEmail().'.'
            );
        }

        /** @var array{spreadsheetId?: string, spreadsheetUrl?: string, sheets?: list<array{properties?: array{title?: string, sheetId?: int}, charts?: list<array{chartId?: int}}>}> $data */
        $data = $response->json();
        $requests = [];

        foreach ($data['sheets'] ?? [] as $sheet) {
            foreach ($sheet['charts'] ?? [] as $chart) {
                if (isset($chart['chartId'])) {
                    $requests[] = [
                        'deleteEmbeddedObject' => ['objectId' => $chart['chartId']],
                    ];
                }
            }
        }

        $tempTitle = '__finanzamente_reset__';
        $requests[] = [
            'addSheet' => [
                'properties' => [
                    'title' => $tempTitle,
                    'gridProperties' => ['rowCount' => 10, 'columnCount' => 5],
                ],
            ],
        ];

        foreach ($data['sheets'] ?? [] as $sheet) {
            $sheetId = (int) ($sheet['properties']['sheetId'] ?? -1);
            if ($sheetId < 0) {
                continue;
            }
            $requests[] = [
                'deleteSheet' => ['sheetId' => $sheetId],
            ];
        }

        foreach (array_values($sheetTitles) as $index => $title) {
            $requests[] = [
                'addSheet' => [
                    'properties' => [
                        'title' => $title,
                        'index' => $index,
                        'gridProperties' => [
                            'rowCount' => 1000,
                            'columnCount' => 40,
                        ],
                    ],
                ],
            ];
        }

        $requests[] = [
            'updateSpreadsheetProperties' => [
                'properties' => [
                    'locale' => 'it_IT',
                    'timeZone' => 'Europe/Rome',
                ],
                'fields' => 'locale,timeZone',
            ],
        ];

        // Delete temp sheet after recreating tabs (needs sheetId from add response — do in 2 steps)
        $batch = Http::withToken($this->token())
            ->timeout(120)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => $requests,
            ]);

        if (! $batch->successful()) {
            throw new RuntimeException(
                'Reset spreadsheet fallito ('.$batch->status().'): '.$batch->body()
            );
        }

        $ids = $this->sheetIdsByTitle($spreadsheetId);
        if (isset($ids[$tempTitle])) {
            $deleteTemp = Http::withToken($this->token())
                ->timeout(60)
                ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                    'requests' => [[
                        'deleteSheet' => ['sheetId' => $ids[$tempTitle]],
                    ]],
                ]);

            if (! $deleteTemp->successful()) {
                throw new RuntimeException(
                    'Rimozione foglio temporaneo fallita ('.$deleteTemp->status().'): '.$deleteTemp->body()
                );
            }
        }

        return [
            'spreadsheetId' => $spreadsheetId,
            'spreadsheetUrl' => $data['spreadsheetUrl'] ?? 'https://docs.google.com/spreadsheets/d/'.$spreadsheetId,
        ];
    }

    /**
     * Data validation (dropdown) + number/date formats + conditional colors.
     *
     * @param  array<string, array{headers?: list<string>, rows: list<list<mixed>>, skip_header?: bool}>  $tables
     */
    public function applyWorkbookFormatting(string $spreadsheetId, array $tables): void
    {
        $ids = $this->sheetIdsByTitle($spreadsheetId);
        $requests = [];

        // Locale IT: display date default DD/MM + argomenti formule con ;
        $requests[] = [
            'updateSpreadsheetProperties' => [
                'properties' => [
                    'locale' => 'it_IT',
                    'timeZone' => 'Europe/Rome',
                ],
                'fields' => 'locale,timeZone',
            ],
        ];

        $euroFormat = ['type' => 'CURRENCY', 'pattern' => '€#,##0.00'];
        // Pattern esplicito DD/MM/YYYY (non dipende da locale viewer)
        $dateFormat = ['type' => 'DATE', 'pattern' => 'dd/mm/yyyy'];
        $numberFormat = ['type' => 'NUMBER', 'pattern' => '#,##0.00'];

        // Finance header: navy + testo chiaro (leggibile)
        $headerFormat = [
            'textFormat' => [
                'bold' => true,
                'foregroundColor' => ['red' => 1.0, 'green' => 1.0, 'blue' => 1.0],
            ],
            'backgroundColor' => ['red' => 0.12, 'green' => 0.23, 'blue' => 0.37], // #1e3a5f
            'horizontalAlignment' => 'CENTER',
        ];

        $contiLast = max(count($tables['Conti']['rows'] ?? []) + 1, 2);
        // Col A = _id (chiave sistema per dropdown/Ref AppSheet)
        $contiIdRange = 'A2:A'.max($contiLast, 200);
        $categorieLast = max(count($tables['Categorie']['rows'] ?? []) + 1, 2);
        $categorieIdRange = 'A2:A'.max($categorieLast, 200);

        foreach ($tables as $title => $table) {
            if (! isset($ids[$title]) || ($table['skip_header'] ?? false) || ($table['headers'] ?? []) === []) {
                continue;
            }
            $sheetId = $ids[$title];
            $colCount = count($table['headers']);

            $requests[] = [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $colCount,
                    ],
                    'cell' => ['userEnteredFormat' => $headerFormat],
                    'fields' => 'userEnteredFormat(textFormat,backgroundColor,horizontalAlignment)',
                ],
            ];
            $requests[] = [
                'updateSheetProperties' => [
                    'properties' => [
                        'sheetId' => $sheetId,
                        'gridProperties' => ['frozenRowCount' => 1],
                    ],
                    'fields' => 'gridProperties.frozenRowCount',
                ],
            ];
        }

        if (isset($ids['Conti'])) {
            $n = max($contiLast + 20, 30);
            // E Saldo Iniziale, F Saldo (dopo _id)
            foreach ([4, 5] as $col) {
                $requests[] = $this->repeatNumberFormat($ids['Conti'], 1, $n, $col, $col + 1, $euroFormat);
            }
        }

        if (isset($ids['Transazioni'])) {
            $n = max(count($tables['Transazioni']['rows'] ?? []) + 1, 2);
            $end = max($n + 200, 500);
            $requests[] = $this->repeatNumberFormat($ids['Transazioni'], 1, $end, 0, 1, $dateFormat);
            $requests[] = $this->repeatNumberFormat($ids['Transazioni'], 1, $end, 2, 3, $euroFormat);
            $requests = array_merge($requests, $this->amountConditionalRules($ids['Transazioni'], 1, $end, 2, 3));

            // Dropdown sulle chiavi _*_id (non sulle etichette)
            if (isset($ids['Conti'])) {
                $requests[] = $this->oneOfRangeValidation($ids['Transazioni'], 1, $end, 5, 6, 'Conti', $contiIdRange);
            }
            if (isset($ids['Categorie'])) {
                $requests[] = $this->oneOfRangeValidation($ids['Transazioni'], 1, $end, 7, 8, 'Categorie', $categorieIdRange);
            }
            if (isset($ids['Debiti'])) {
                $debitoLast = max(count($tables['Debiti']['rows'] ?? []) + 1, 2);
                $requests[] = $this->oneOfRangeValidation(
                    $ids['Transazioni'],
                    1,
                    $end,
                    9,
                    10,
                    'Debiti',
                    'A2:A'.max($debitoLast, 200)
                );
            }
            if (isset($ids['Portfolio'])) {
                $portLast = max(count($tables['Portfolio']['rows'] ?? []) + 1, 2);
                $requests[] = $this->oneOfRangeValidation(
                    $ids['Transazioni'],
                    1,
                    $end,
                    11,
                    12,
                    'Portfolio',
                    'A2:A'.max($portLast, 200)
                );
            }
            $requests[] = $this->oneOfListValidation(
                $ids['Transazioni'],
                1,
                $end,
                4,
                5,
                [
                    'Entrata', 'Uscita', 'Trasferimento', 'Rimborso',
                    'Investimento', 'Investimento acquisto', 'Investimento vendita', 'Cedola/dividendo',
                ]
            );
        }

        // Checkbox boolean columns (Conti attivo/privato, Categorie flags, …)
        foreach ($tables as $title => $table) {
            if (! isset($ids[$title], $table['checkbox_columns'])) {
                continue;
            }
            // Solo righe dati (+ buffer tabella): evita checkbox su decine di righe vuote (rompe AppSheet)
            $end = count($table['rows'] ?? []) + 1 + (int) ($table['table_buffer_rows'] ?? 0);
            $end = max($end, 2);
            foreach ($table['checkbox_columns'] as $colIndex) {
                $requests[] = $this->checkboxValidation(
                    $ids[$title],
                    1,
                    $end,
                    (int) $colIndex,
                    (int) $colIndex + 1
                );
            }
        }

        if (isset($ids['Investimenti'])) {
            $n = max(count($tables['Investimenti']['rows'] ?? []) + 1, 2);
            $end = max($n + 20, 50);
            $invSheet = $ids['Investimenti'];
            $requests[] = $this->repeatNumberFormat($invSheet, 1, $end, 0, 2, $dateFormat);
            // F Qty, G Prezzo, H Costo, I PrezzoVendita, J Fee, M MovimentiCassa
            $requests[] = $this->repeatNumberFormat($invSheet, 1, $end, 5, 6, $numberFormat);
            foreach ([6, 7, 8, 9, 12] as $col) {
                $requests[] = $this->repeatNumberFormat($invSheet, 1, $end, $col, $col + 1, $euroFormat);
            }
            if (isset($ids['Conti'])) {
                $requests[] = $this->oneOfRangeValidation(
                    $invSheet,
                    1,
                    max($end, 100),
                    10,
                    11,
                    'Conti',
                    $contiIdRange
                );
            }
            if (isset($ids['Portfolio'])) {
                $portLast = max(count($tables['Portfolio']['rows'] ?? []) + 1, 2);
                $requests[] = $this->oneOfRangeValidation(
                    $invSheet,
                    1,
                    max($end, 100),
                    3,
                    4,
                    'Portfolio',
                    'A2:A'.max($portLast, 200)
                );
            }
            // Stato (col C): aperto verde / chiuso grigio
            $requests = array_merge(
                $requests,
                $this->statusTextColorRules($invSheet, 1, $end, 2, [
                    'aperto' => 'green',
                    'chiuso' => 'gray',
                ])
            );
        }

        if (isset($ids['Portfolio'])) {
            $end = max(count($tables['Portfolio']['rows'] ?? []) + 1, 30);
            // H Qty, I Costo, J Prezzo Medio
            $requests[] = $this->repeatNumberFormat($ids['Portfolio'], 1, $end, 7, 10, $numberFormat);
            $requests[] = $this->repeatNumberFormat($ids['Portfolio'], 1, $end, 8, 9, $euroFormat);
            if (isset($ids['Conti'])) {
                $requests[] = $this->oneOfRangeValidation(
                    $ids['Portfolio'],
                    1,
                    max($end, 100),
                    5,
                    6,
                    'Conti',
                    $contiIdRange
                );
            }
            // Stato (col K): aperto verde / chiuso grigio
            $requests = array_merge(
                $requests,
                $this->statusTextColorRules($ids['Portfolio'], 1, $end, 10, [
                    'aperto' => 'green',
                    'chiuso' => 'gray',
                ])
            );
        }

        if (isset($ids['Debiti'])) {
            $n = max(count($tables['Debiti']['rows'] ?? []) + 1, 2);
            $end = max($n + 20, 50);
            // D Importo, E Pagato, F Residuo
            foreach ([3, 4, 5] as $col) {
                $requests[] = $this->repeatNumberFormat($ids['Debiti'], 1, $end, $col, $col + 1, $euroFormat);
            }
            // I Data Inizio, J Scadenza
            $requests[] = $this->repeatNumberFormat($ids['Debiti'], 1, $end, 8, 10, $dateFormat);
            $requests = array_merge($requests, $this->amountConditionalRules($ids['Debiti'], 1, $end, 5, 6));
            // Stato (col H)
            $requests = array_merge(
                $requests,
                $this->statusTextColorRules($ids['Debiti'], 1, $end, 7, [
                    'aperto' => 'green',
                    'scaduto' => 'amber',
                    'chiuso' => 'gray',
                ])
            );
        }

        if (isset($ids['Budget'])) {
            $n = max(count($tables['Budget']['rows'] ?? []) + 1, 2);
            $end = max($n + 50, 100);
            $requests[] = $this->repeatNumberFormat($ids['Budget'], 1, $end, 2, 3, $euroFormat);
            $requests[] = $this->repeatNumberFormat($ids['Budget'], 1, $end, 4, 6, $dateFormat);
            if (isset($ids['Categorie'])) {
                $requests[] = $this->oneOfRangeValidation($ids['Budget'], 1, $end, 0, 1, 'Categorie', $categorieIdRange);
            }
        }

        if (isset($ids['Obiettivi'])) {
            $n = max(count($tables['Obiettivi']['rows'] ?? []) + 1, 2);
            // B Nome skipped; C Obiettivo, D Attuale, E Progresso, G Data Target
            foreach ([2, 3] as $col) {
                $requests[] = $this->repeatNumberFormat($ids['Obiettivi'], 1, $n, $col, $col + 1, $euroFormat);
            }
            $requests[] = $this->repeatNumberFormat($ids['Obiettivi'], 1, $n, 4, 5, ['type' => 'PERCENT', 'pattern' => '0.0%']);
            $requests[] = $this->repeatNumberFormat($ids['Obiettivi'], 1, $n, 6, 7, $dateFormat);
        }

        if (isset($ids['Categorie'])) {
            $catEnd = max(count($tables['Categorie']['rows'] ?? []) + 1, 2);
            $catSheet = $ids['Categorie'];
            // Tipo ora in col C ($C2) dopo _id
            $requests[] = [
                'addConditionalFormatRule' => [
                    'rule' => [
                        'ranges' => [[
                            'sheetId' => $catSheet,
                            'startRowIndex' => 1,
                            'endRowIndex' => max($catEnd + 20, 50),
                            'startColumnIndex' => 0,
                            'endColumnIndex' => count($tables['Categorie']['headers'] ?? range(0, 5)),
                        ]],
                        'booleanRule' => [
                            'condition' => [
                                'type' => 'CUSTOM_FORMULA',
                                'values' => [['userEnteredValue' => '=$C2="Entrata"']],
                            ],
                            'format' => [
                                'backgroundColor' => ['red' => 0.85, 'green' => 0.95, 'blue' => 0.88],
                            ],
                        ],
                    ],
                    'index' => 0,
                ],
            ];
            $requests[] = [
                'addConditionalFormatRule' => [
                    'rule' => [
                        'ranges' => [[
                            'sheetId' => $catSheet,
                            'startRowIndex' => 1,
                            'endRowIndex' => max($catEnd + 20, 50),
                            'startColumnIndex' => 0,
                            'endColumnIndex' => count($tables['Categorie']['headers'] ?? range(0, 5)),
                        ]],
                        'booleanRule' => [
                            'condition' => [
                                'type' => 'CUSTOM_FORMULA',
                                'values' => [['userEnteredValue' => '=$C2="Uscita"']],
                            ],
                            'format' => [
                                'backgroundColor' => ['red' => 0.98, 'green' => 0.90, 'blue' => 0.88],
                            ],
                        ],
                    ],
                    'index' => 1,
                ],
            ];
        }

        if (isset($ids['Dashboard'])) {
            // B5-B8 saldi/patrimonio; E5-E7 mese; B11-B12 lifestyle euro; B13 %
            $requests[] = $this->repeatNumberFormat($ids['Dashboard'], 4, 8, 1, 2, $euroFormat);
            $requests[] = $this->repeatNumberFormat($ids['Dashboard'], 4, 7, 4, 5, $euroFormat);
            $requests[] = $this->repeatNumberFormat($ids['Dashboard'], 10, 12, 1, 2, $euroFormat);
            $requests[] = $this->repeatNumberFormat($ids['Dashboard'], 12, 13, 1, 2, ['type' => 'PERCENT', 'pattern' => '0.0%']);
            // Brand row
            $requests[] = [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $ids['Dashboard'],
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => 2,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => [
                                'bold' => true,
                                'fontSize' => 18,
                                'foregroundColor' => ['red' => 0.12, 'green' => 0.23, 'blue' => 0.37],
                            ],
                        ],
                    ],
                    'fields' => 'userEnteredFormat.textFormat',
                ],
            ];
        }

        if (isset($ids['_Grafici'])) {
            $cf = (int) ($tables['_Grafici']['chart_meta']['cashflow_rows'] ?? 13);
            $cat = (int) ($tables['_Grafici']['chart_meta']['category_rows'] ?? 40);
            $acc = (int) ($tables['_Grafici']['chart_meta']['account_rows'] ?? 40);
            $requests[] = $this->repeatNumberFormat($ids['_Grafici'], 1, $cf, 1, 4, $euroFormat);
            $requests[] = $this->repeatNumberFormat($ids['_Grafici'], 1, $cat, 6, 7, $euroFormat);
            $requests[] = $this->repeatNumberFormat($ids['_Grafici'], 1, $acc, 9, 10, $euroFormat);
        }

        foreach (array_chunk($requests, 40) as $chunk) {
            $batch = Http::withToken($this->token())
                ->timeout(120)
                ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                    'requests' => $chunk,
                ]);

            if (! $batch->successful()) {
                throw new RuntimeException(
                    'Formattazione workbook fallita ('.$batch->status().'): '.$batch->body()
                );
            }
        }
    }

    /**
     * Convert data ranges into native Google Sheets Tables.
     *
     * @param  array<string, array{
     *     headers?: list<string>,
     *     rows: list<list<mixed>>,
     *     as_table?: bool,
     *     table_columns?: list<array<string, mixed>>,
     *     table_buffer_rows?: int
     * }>  $tables
     */
    public function createNativeTables(string $spreadsheetId, array $tables): void
    {
        $ids = $this->sheetIdsByTitle($spreadsheetId);
        $requests = [];

        foreach ($tables as $title => $table) {
            if (! ($table['as_table'] ?? false) || ! isset($ids[$title])) {
                continue;
            }

            $headers = $table['headers'] ?? [];
            $rowCount = count($table['rows'] ?? []);
            if ($headers === [] || $rowCount === 0) {
                continue;
            }

            $buffer = max(0, (int) ($table['table_buffer_rows'] ?? 0));
            $endRow = $rowCount + 1 + $buffer;

            $columnProperties = [];
            foreach ($table['table_columns'] ?? [] as $col) {
                $type = $this->normalizeTableColumnType($col['columnType'] ?? null);

                // DROPDOWN/BOOLEAN: validazione via setDataValidation dopo la tabella.
                // DATE/DATE_TIME: tipo tabella forza formato locale (spesso MM/DD) e ignora
                // numberFormat dd/mm/yyyy — formattiamo dopo con repeatCell.
                // Su colonne tipizzate Sheets rifiuta setDataValidation ("typed columns").
                if (in_array($type, ['DROPDOWN', 'BOOLEAN', 'DATE', 'DATE_TIME'], true)) {
                    continue;
                }

                $prop = [
                    'columnIndex' => (int) $col['columnIndex'],
                    'columnName' => (string) ($col['columnName'] ?? $headers[$col['columnIndex']] ?? 'col'),
                ];
                if ($type !== null) {
                    $prop['columnType'] = $type;
                }
                $columnProperties[] = $prop;
            }

            $tablePayload = [
                'name' => $this->safeTableName($title),
                'range' => [
                    'sheetId' => $ids[$title],
                    'startRowIndex' => 0,
                    'endRowIndex' => $endRow,
                    'startColumnIndex' => 0,
                    'endColumnIndex' => count($headers),
                ],
            ];
            if ($columnProperties !== []) {
                $tablePayload['columnProperties'] = $columnProperties;
            }

            $requests[] = [
                'addTable' => [
                    'table' => $tablePayload,
                ],
            ];
        }

        foreach (array_chunk($requests, 10) as $chunk) {
            $batch = Http::withToken($this->token())
                ->timeout(120)
                ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                    'requests' => $chunk,
                ]);

            if (! $batch->successful()) {
                throw new RuntimeException(
                    'Creazione tabelle Sheets fallita ('.$batch->status().'): '.$batch->body()
                );
            }
        }
    }

    private function safeTableName(string $title): string
    {
        $name = preg_replace('/[^A-Za-z0-9_]/u', '_', $title) ?? 'Table';
        $name = trim($name, '_');

        return $name !== '' ? $name : 'Table';
    }

    /**
     * Map aliases to Sheets Table ColumnType enum.
     *
     * @see https://developers.google.com/workspace/sheets/api/reference/rest/v4/spreadsheets/sheets#ColumnType
     */
    private function normalizeTableColumnType(mixed $type): ?string
    {
        if (! is_string($type) || $type === '') {
            return null;
        }

        return match ($type) {
            'NUMBER', 'DOUBLE' => 'DOUBLE',
            'CURRENCY', 'CURRENCY_EUR' => 'CURRENCY',
            'CHECKBOX', 'BOOLEAN' => 'BOOLEAN',
            'PERCENT' => 'PERCENT',
            'DATE' => 'DATE',
            'TIME' => 'TIME',
            'DATE_TIME' => 'DATE_TIME',
            'TEXT' => 'TEXT',
            'DROPDOWN' => 'DROPDOWN',
            default => null,
        };
    }

    /**
     * @param  array{type: string, pattern: string}  $format
     * @return array<string, mixed>
     */
    private function repeatNumberFormat(
        int $sheetId,
        int $startRow,
        int $endRow,
        int $startCol,
        int $endCol,
        array $format,
    ): array {
        return [
            'repeatCell' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => $startRow,
                    'endRowIndex' => $endRow,
                    'startColumnIndex' => $startCol,
                    'endColumnIndex' => $endCol,
                ],
                'cell' => [
                    'userEnteredFormat' => [
                        'numberFormat' => $format,
                    ],
                ],
                'fields' => 'userEnteredFormat.numberFormat',
            ],
        ];
    }

    /**
     * Colori condizionali su colonna Stato (testo esatto).
     *
     * @param  array<string, 'green'|'gray'|'amber'|'red'>  $statuses
     * @return list<array<string, mixed>>
     */
    private function statusTextColorRules(
        int $sheetId,
        int $startRow,
        int $endRow,
        int $colIndex,
        array $statuses,
    ): array {
        $palettes = [
            'green' => [
                'bg' => ['red' => 0.78, 'green' => 0.94, 'blue' => 0.80],
                'fg' => ['red' => 0.10, 'green' => 0.40, 'blue' => 0.20],
            ],
            'gray' => [
                'bg' => ['red' => 0.90, 'green' => 0.90, 'blue' => 0.90],
                'fg' => ['red' => 0.35, 'green' => 0.35, 'blue' => 0.35],
            ],
            'amber' => [
                'bg' => ['red' => 1.0, 'green' => 0.92, 'blue' => 0.75],
                'fg' => ['red' => 0.55, 'green' => 0.35, 'blue' => 0.05],
            ],
            'red' => [
                'bg' => ['red' => 0.98, 'green' => 0.80, 'blue' => 0.80],
                'fg' => ['red' => 0.60, 'green' => 0.10, 'blue' => 0.10],
            ],
        ];

        $requests = [];
        $index = 0;
        foreach ($statuses as $text => $paletteKey) {
            $palette = $palettes[$paletteKey] ?? $palettes['gray'];
            $requests[] = [
                'addConditionalFormatRule' => [
                    'rule' => [
                        'ranges' => [[
                            'sheetId' => $sheetId,
                            'startRowIndex' => $startRow,
                            'endRowIndex' => $endRow,
                            'startColumnIndex' => $colIndex,
                            'endColumnIndex' => $colIndex + 1,
                        ]],
                        'booleanRule' => [
                            'condition' => [
                                'type' => 'TEXT_EQ',
                                'values' => [['userEnteredValue' => (string) $text]],
                            ],
                            'format' => [
                                'backgroundColor' => $palette['bg'],
                                'textFormat' => [
                                    'foregroundColor' => $palette['fg'],
                                    'bold' => true,
                                ],
                            ],
                        ],
                    ],
                    'index' => $index,
                ],
            ];
            $index++;
        }

        return $requests;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function amountConditionalRules(int $sheetId, int $startRow, int $endRow, int $startCol, int $endCol): array
    {
        $range = [
            'sheetId' => $sheetId,
            'startRowIndex' => $startRow,
            'endRowIndex' => $endRow,
            'startColumnIndex' => $startCol,
            'endColumnIndex' => $endCol,
        ];

        return [
            [
                'addConditionalFormatRule' => [
                    'rule' => [
                        'ranges' => [$range],
                        'booleanRule' => [
                            'condition' => [
                                'type' => 'NUMBER_LESS',
                                'values' => [['userEnteredValue' => '0']],
                            ],
                            'format' => [
                                'textFormat' => [
                                    'foregroundColor' => ['red' => 0.72, 'green' => 0.11, 'blue' => 0.11],
                                ],
                            ],
                        ],
                    ],
                    'index' => 0,
                ],
            ],
            [
                'addConditionalFormatRule' => [
                    'rule' => [
                        'ranges' => [$range],
                        'booleanRule' => [
                            'condition' => [
                                'type' => 'NUMBER_GREATER',
                                'values' => [['userEnteredValue' => '0']],
                            ],
                            'format' => [
                                'textFormat' => [
                                    'foregroundColor' => ['red' => 0.09, 'green' => 0.47, 'blue' => 0.27],
                                ],
                            ],
                        ],
                    ],
                    'index' => 0,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function oneOfRangeValidation(
        int $targetSheetId,
        int $startRow,
        int $endRow,
        int $startCol,
        int $endCol,
        string $sourceSheetTitle,
        string $sourceA1Range,
    ): array {
        $escaped = str_replace("'", "''", $sourceSheetTitle);

        return [
            'setDataValidation' => [
                'range' => [
                    'sheetId' => $targetSheetId,
                    'startRowIndex' => $startRow,
                    'endRowIndex' => $endRow,
                    'startColumnIndex' => $startCol,
                    'endColumnIndex' => $endCol,
                ],
                'rule' => [
                    'condition' => [
                        'type' => 'ONE_OF_RANGE',
                        'values' => [[
                            'userEnteredValue' => "='{$escaped}'!{$sourceA1Range}",
                        ]],
                    ],
                    'showCustomUi' => true,
                    'strict' => false,
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $values
     * @return array<string, mixed>
     */
    private function oneOfListValidation(
        int $targetSheetId,
        int $startRow,
        int $endRow,
        int $startCol,
        int $endCol,
        array $values,
    ): array {
        return [
            'setDataValidation' => [
                'range' => [
                    'sheetId' => $targetSheetId,
                    'startRowIndex' => $startRow,
                    'endRowIndex' => $endRow,
                    'startColumnIndex' => $startCol,
                    'endColumnIndex' => $endCol,
                ],
                'rule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => array_map(
                            static fn (string $value) => ['userEnteredValue' => $value],
                            $values
                        ),
                    ],
                    'showCustomUi' => true,
                    'strict' => false,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkboxValidation(
        int $sheetId,
        int $startRow,
        int $endRow,
        int $startCol,
        int $endCol,
    ): array {
        return [
            'setDataValidation' => [
                'range' => [
                    'sheetId' => $sheetId,
                    'startRowIndex' => $startRow,
                    'endRowIndex' => $endRow,
                    'startColumnIndex' => $startCol,
                    'endColumnIndex' => $endCol,
                ],
                'rule' => [
                    'condition' => [
                        'type' => 'BOOLEAN',
                    ],
                    'showCustomUi' => true,
                    'strict' => true,
                ],
            ],
        ];
    }

    private function a1(string $sheetTitle, int $startRow): string
    {
        $escaped = str_replace("'", "''", $sheetTitle);

        return "'{$escaped}'!A{$startRow}";
    }

    public static function resolveCredentialsPath(string $path): string
    {
        if ($path !== '' && $path[0] === '/') {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  array<string, array{
     *     headers: list<string>,
     *     rows: list<list<mixed>>,
     *     formulas?: array<int, array<int, string>>,
     *     skip_header?: bool
     * }>  $tables
     */
    public function writeTables(string $spreadsheetId, array $tables, int $chunkRows = 2000): void
    {
        $this->ensureGridCapacity($spreadsheetId, $tables);

        $pendingRaw = [];
        $pendingUser = [];
        $pendingCells = 0;
        $flushThreshold = 40000;

        $flush = function () use ($spreadsheetId, &$pendingRaw, &$pendingUser, &$pendingCells): void {
            if ($pendingRaw !== []) {
                $this->batchUpdateValues($spreadsheetId, $pendingRaw, 'RAW');
                $pendingRaw = [];
            }
            if ($pendingUser !== []) {
                $this->batchUpdateValues($spreadsheetId, $pendingUser, 'USER_ENTERED');
                $pendingUser = [];
            }
            $pendingCells = 0;
        };

        foreach ($tables as $title => $table) {
            $skipHeader = (bool) ($table['skip_header'] ?? false);
            $headers = $table['headers'] ?? [];
            $values = $skipHeader || $headers === []
                ? $table['rows']
                : array_merge([$headers], $table['rows']);

            // Overlay formula cells (1-based sheet row => 0-based col => formula)
            if (! empty($table['formulas'])) {
                foreach ($table['formulas'] as $sheetRow => $cols) {
                    $valueIndex = $skipHeader ? $sheetRow - 1 : $sheetRow - 1; // rows array index includes header at 0 when not skip
                    if (! $skipHeader) {
                        // sheetRow 2 = values[1]
                        $valueIndex = $sheetRow - 1;
                    }
                    if (! isset($values[$valueIndex])) {
                        continue;
                    }
                    foreach ($cols as $colIndex => $formula) {
                        $values[$valueIndex][$colIndex] = $formula;
                    }
                    $values[$valueIndex] = $this->densifyRow($values[$valueIndex]);
                }
            }

            // Date → serial Sheets (poi numberFormat dd/mm/yyyy). Evita ambiguità locale MM/DD.
            $dateCols = $this->dateColumnIndexesBySheet()[$title] ?? [];
            if ($dateCols !== []) {
                foreach ($values as $rowIdx => $row) {
                    if (! $skipHeader && $rowIdx === 0) {
                        continue;
                    }
                    foreach ($dateCols as $colIdx) {
                        if (! array_key_exists($colIdx, $row)) {
                            continue;
                        }
                        $values[$rowIdx][$colIdx] = $this->toSheetsDateSerial($row[$colIdx]);
                    }
                }
            }

            $useUserEntered = in_array($title, [
                'Dashboard',
                'KPI Cards',
                'Conti',
                '_Grafici',
                'Transazioni',
                'Investimenti',
                'Portfolio',
                'Debiti',
                'Budget',
                'Obiettivi',
                'Categorie',
            ], true)
                || ! empty($table['formulas'])
                || ! empty($table['checkbox_columns']);

            $total = count($values);
            $offset = 0;

            while ($offset < $total) {
                $slice = array_slice($values, $offset, $chunkRows);
                $slice = array_map(fn (array $row): array => $this->densifyRow($row), $slice);
                $startRow = $offset + 1;
                $entry = [
                    'range' => $this->a1($title, $startRow),
                    'majorDimension' => 'ROWS',
                    'values' => $slice,
                ];
                if ($useUserEntered) {
                    $pendingUser[] = $entry;
                } else {
                    $pendingRaw[] = $entry;
                }
                $pendingCells += count($slice) * max(1, count($slice[0] ?? []));
                $offset += $chunkRows;

                if ($pendingCells >= $flushThreshold) {
                    $flush();
                }
            }
        }

        $flush();
    }

    /**
     * Hide a sheet by title (e.g. _Grafici helper).
     */
    public function hideSheetByTitle(string $spreadsheetId, string $title): void
    {
        $ids = $this->sheetIdsByTitle($spreadsheetId);
        if (! isset($ids[$title])) {
            return;
        }

        $response = Http::withToken($this->token())
            ->timeout(60)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => [[
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $ids[$title],
                            'hidden' => true,
                        ],
                        'fields' => 'hidden',
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Nascondi foglio fallito ('.$response->status().'): '.$response->body()
            );
        }
    }

    /**
     * Add pie/column/bar charts on Dashboard from _Grafici ranges.
     *
     * @param  array{cashflow_rows: int, category_rows: int, account_rows: int}  $chartMeta
     */
    public function addFinanceDashboardCharts(string $spreadsheetId, array $chartMeta): void
    {
        $ids = $this->sheetIdsByTitle($spreadsheetId);
        if (! isset($ids['Dashboard'], $ids['_Grafici'])) {
            return;
        }

        $dashboardId = $ids['Dashboard'];
        $graficiId = $ids['_Grafici'];
        $cfRows = max(2, (int) $chartMeta['cashflow_rows']);
        $catRows = max(2, (int) $chartMeta['category_rows']);
        $accRows = max(2, (int) $chartMeta['account_rows']);

        $requests = [
            [
                'addChart' => [
                    'chart' => [
                        'spec' => [
                            'title' => 'Cashflow ultimi 12 mesi',
                            'basicChart' => [
                                'chartType' => 'COLUMN',
                                'legendPosition' => 'BOTTOM_LEGEND',
                                'axis' => [
                                    ['position' => 'BOTTOM_AXIS', 'title' => 'Mese'],
                                    ['position' => 'LEFT_AXIS', 'title' => 'EUR'],
                                ],
                                'domains' => [[
                                    'domain' => [
                                        'sourceRange' => [
                                            'sources' => [[
                                                'sheetId' => $graficiId,
                                                'startRowIndex' => 0,
                                                'endRowIndex' => $cfRows,
                                                'startColumnIndex' => 0,
                                                'endColumnIndex' => 1,
                                            ]],
                                        ],
                                    ],
                                ]],
                                'series' => [
                                    [
                                        'series' => [
                                            'sourceRange' => [
                                                'sources' => [[
                                                    'sheetId' => $graficiId,
                                                    'startRowIndex' => 0,
                                                    'endRowIndex' => $cfRows,
                                                    'startColumnIndex' => 1,
                                                    'endColumnIndex' => 2,
                                                ]],
                                            ],
                                        ],
                                        'targetAxis' => 'LEFT_AXIS',
                                    ],
                                    [
                                        'series' => [
                                            'sourceRange' => [
                                                'sources' => [[
                                                    'sheetId' => $graficiId,
                                                    'startRowIndex' => 0,
                                                    'endRowIndex' => $cfRows,
                                                    'startColumnIndex' => 2,
                                                    'endColumnIndex' => 3,
                                                ]],
                                            ],
                                        ],
                                        'targetAxis' => 'LEFT_AXIS',
                                    ],
                                ],
                                'headerCount' => 1,
                            ],
                        ],
                        'position' => [
                            'overlayPosition' => [
                                'anchorCell' => [
                                    'sheetId' => $dashboardId,
                                    'rowIndex' => 15,
                                    'columnIndex' => 0,
                                ],
                                'widthPixels' => 560,
                                'heightPixels' => 320,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'addChart' => [
                    'chart' => [
                        'spec' => [
                            'title' => 'Spese per categoria (anno corrente)',
                            'pieChart' => [
                                'legendPosition' => 'RIGHT_LEGEND',
                                'domain' => [
                                    'sourceRange' => [
                                        'sources' => [[
                                            'sheetId' => $graficiId,
                                            'startRowIndex' => 0,
                                            'endRowIndex' => $catRows,
                                            'startColumnIndex' => 5,
                                            'endColumnIndex' => 6,
                                        ]],
                                    ],
                                ],
                                'series' => [
                                    'sourceRange' => [
                                        'sources' => [[
                                            'sheetId' => $graficiId,
                                            'startRowIndex' => 0,
                                            'endRowIndex' => $catRows,
                                            'startColumnIndex' => 6,
                                            'endColumnIndex' => 7,
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                        'position' => [
                            'overlayPosition' => [
                                'anchorCell' => [
                                    'sheetId' => $dashboardId,
                                    'rowIndex' => 15,
                                    'columnIndex' => 6,
                                ],
                                'widthPixels' => 480,
                                'heightPixels' => 320,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'addChart' => [
                    'chart' => [
                        'spec' => [
                            'title' => 'Saldi conti',
                            'basicChart' => [
                                'chartType' => 'BAR',
                                'legendPosition' => 'NO_LEGEND',
                                'domains' => [[
                                    'domain' => [
                                        'sourceRange' => [
                                            'sources' => [[
                                                'sheetId' => $graficiId,
                                                'startRowIndex' => 0,
                                                'endRowIndex' => $accRows,
                                                'startColumnIndex' => 8,
                                                'endColumnIndex' => 9,
                                            ]],
                                        ],
                                    ],
                                ]],
                                'series' => [[
                                    'series' => [
                                        'sourceRange' => [
                                            'sources' => [[
                                                'sheetId' => $graficiId,
                                                'startRowIndex' => 0,
                                                'endRowIndex' => $accRows,
                                                'startColumnIndex' => 9,
                                                'endColumnIndex' => 10,
                                            ]],
                                        ],
                                    ],
                                    'targetAxis' => 'BOTTOM_AXIS',
                                ]],
                                'headerCount' => 1,
                            ],
                        ],
                        'position' => [
                            'overlayPosition' => [
                                'anchorCell' => [
                                    'sheetId' => $dashboardId,
                                    'rowIndex' => 32,
                                    'columnIndex' => 0,
                                ],
                                'widthPixels' => 560,
                                'heightPixels' => 300,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Remove existing charts on Dashboard to allow re-export
        $meta = Http::withToken($this->token())
            ->timeout(30)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}", [
                'fields' => 'sheets.charts,sheets.properties.sheetId,sheets.properties.title',
            ]);

        if ($meta->successful()) {
            /** @var array{sheets?: list<array{properties?: array{sheetId?: int, title?: string}, charts?: list<array{chartId?: int}>}>} $payload */
            $payload = $meta->json();
            foreach ($payload['sheets'] ?? [] as $sheet) {
                if (($sheet['properties']['title'] ?? '') !== 'Dashboard') {
                    continue;
                }
                foreach ($sheet['charts'] ?? [] as $chart) {
                    if (isset($chart['chartId'])) {
                        array_unshift($requests, [
                            'deleteEmbeddedObject' => [
                                'objectId' => $chart['chartId'],
                            ],
                        ]);
                    }
                }
            }
        }

        $batch = Http::withToken($this->token())
            ->timeout(60)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => $requests,
            ]);

        if (! $batch->successful()) {
            throw new RuntimeException(
                'Creazione grafici Dashboard fallita ('.$batch->status().'): '.$batch->body()
            );
        }
    }

    /**
     * @return array<string, int>
     */
    public function sheetIdsByTitle(string $spreadsheetId): array
    {
        $response = Http::withToken($this->token())
            ->timeout(30)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}", [
                'fields' => 'sheets.properties(sheetId,title)',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Lettura sheet id fallita ('.$response->status().'): '.$response->body()
            );
        }

        $map = [];
        /** @var array{sheets?: list<array{properties?: array{sheetId?: int, title?: string}}>} $data */
        $data = $response->json();
        foreach ($data['sheets'] ?? [] as $sheet) {
            $title = $sheet['properties']['title'] ?? null;
            if (is_string($title)) {
                $map[$title] = (int) ($sheet['properties']['sheetId'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * Expand each sheet grid so writes do not hit "exceeds grid limits".
     *
     * @param  array<string, array{headers: list<string>, rows: list<list<mixed>>}>  $tables
     */
    private function ensureGridCapacity(string $spreadsheetId, array $tables): void
    {
        $response = Http::withToken($this->token())
            ->timeout(60)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}", [
                'fields' => 'sheets.properties(sheetId,title,gridProperties)',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Lettura grid spreadsheet fallita ('.$response->status().'): '.$response->body()
            );
        }

        /** @var array{sheets?: list<array{properties?: array{sheetId?: int, title?: string, gridProperties?: array{rowCount?: int, columnCount?: int}}}>} $data */
        $data = $response->json();
        $byTitle = [];
        foreach ($data['sheets'] ?? [] as $sheet) {
            $title = $sheet['properties']['title'] ?? null;
            if (! is_string($title) || $title === '') {
                continue;
            }
            $byTitle[$title] = [
                'sheetId' => (int) ($sheet['properties']['sheetId'] ?? 0),
                'rowCount' => (int) ($sheet['properties']['gridProperties']['rowCount'] ?? 0),
                'columnCount' => (int) ($sheet['properties']['gridProperties']['columnCount'] ?? 0),
            ];
        }

        $requests = [];
        foreach ($tables as $title => $table) {
            if (! isset($byTitle[$title])) {
                continue;
            }

            $headerRows = ((bool) ($table['skip_header'] ?? false) || ($table['headers'] ?? []) === []) ? 0 : 1;
            $buffer = max(0, (int) ($table['table_buffer_rows'] ?? 0));
            $neededRows = count($table['rows']) + $headerRows + $buffer + 50;
            $colSource = ($table['headers'] ?? []) !== []
                ? $table['headers']
                : ($table['rows'][0] ?? []);
            $neededCols = max(count($colSource) + 5, 26);
            $current = $byTitle[$title];

            if ($current['rowCount'] >= $neededRows && $current['columnCount'] >= $neededCols) {
                continue;
            }

            $requests[] = [
                'updateSheetProperties' => [
                    'properties' => [
                        'sheetId' => $current['sheetId'],
                        'gridProperties' => [
                            'rowCount' => max($current['rowCount'], $neededRows),
                            'columnCount' => max($current['columnCount'], $neededCols),
                        ],
                    ],
                    'fields' => 'gridProperties.rowCount,gridProperties.columnCount',
                ],
            ];
        }

        foreach (array_chunk($requests, 50) as $chunk) {
            $batch = Http::withToken($this->token())
                ->timeout(60)
                ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                    'requests' => $chunk,
                ]);

            if (! $batch->successful()) {
                throw new RuntimeException(
                    'Resize grid spreadsheet fallito ('.$batch->status().'): '.$batch->body()
                );
            }
        }
    }

    public function shareWithUser(string $spreadsheetId, string $email, string $role = 'writer'): void
    {
        $response = Http::withToken($this->token())
            ->timeout(30)
            ->withQueryParameters(['sendNotificationEmail' => 'true'])
            ->post("https://www.googleapis.com/drive/v3/files/{$spreadsheetId}/permissions", [
                'type' => 'user',
                'role' => $role,
                'emailAddress' => $email,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Condivisione spreadsheet fallita ('.$response->status().'): '.$response->body()
            );
        }
    }

    /**
     * Colonne data (0-based) per foglio workbook.
     *
     * @return array<string, list<int>>
     */
    private function dateColumnIndexesBySheet(): array
    {
        return [
            'Transazioni' => [0],
            'Investimenti' => [0, 1],
            'Debiti' => [8, 9],
            'Budget' => [4, 5],
            'Obiettivi' => [6],
        ];
    }

    /**
     * Converte data IT (DD/MM/YYYY) o ISO in serial numerico Sheets/Excel.
     * Evita formula DATA()/DATE (dipende da locale) e parse testo MM/DD.
     * Display: numberFormat dd/mm/yyyy.
     */
    private function toSheetsDateSerial(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        if (str_starts_with($value, '=')) {
            return $value;
        }

        $date = null;
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) === 1) {
            // Sempre DD/MM/YYYY (input italiano dal builder)
            $date = Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
        } elseif (preg_match('#^\d{4}-\d{2}-\d{2}#', $value) === 1) {
            $date = Carbon::parse($value)->startOfDay();
        }

        if ($date === null) {
            return $value;
        }

        // Epoch Sheets/Excel: 1899-12-30. Serial intero = giorno di calendario.
        $epoch = Carbon::create(1899, 12, 30)->startOfDay();

        return (float) $epoch->diffInDays($date->copy()->startOfDay(), false);
    }

    /**
     * PHP sparse rows (hole at key N) encode as JSON objects — Sheets API rejects them.
     *
     * @param  array<int, mixed>  $row
     * @return list<mixed>
     */
    private function densifyRow(array $row): array
    {
        if ($row === []) {
            return [];
        }

        $max = (int) max(array_keys($row));
        $dense = [];
        for ($i = 0; $i <= $max; $i++) {
            $dense[] = $row[$i] ?? '';
        }

        return $dense;
    }

    /**
     * @param  list<array{range: string, majorDimension: string, values: list<list<mixed>>}>  $data
     */
    private function batchUpdateValues(string $spreadsheetId, array $data, string $valueInputOption = 'RAW'): void
    {
        $response = Http::withToken($this->token())
            ->timeout(120)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values:batchUpdate", [
                'valueInputOption' => $valueInputOption,
                'data' => $data,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Scrittura valori Sheets fallita ('.$response->status().'): '.$response->body()
            );
        }
    }

    private function token(): string
    {
        if ($this->accessToken !== null && time() < ($this->tokenExpiresAt - 60)) {
            return $this->accessToken;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->base64UrlEncode(json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/spreadsheets',
                'https://www.googleapis.com/auth/drive.file',
            ]),
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$claim;
        $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
        if ($privateKey === false) {
            throw new RuntimeException('Private key service account Google non valida.');
        }

        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            throw new RuntimeException('Firma JWT Google fallita.');
        }

        $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Auth Google service account fallita ('.$response->status().'): '.$response->body()
            );
        }

        /** @var array{access_token: string, expires_in?: int} $payload */
        $payload = $response->json();
        $this->accessToken = $payload['access_token'];
        $this->tokenExpiresAt = $now + (int) ($payload['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
