<?php

namespace App\Services\GoogleSheets;

use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\File;
use RuntimeException;

class GoogleSheetsPushService
{
    public function __construct(
        private readonly HouseholdFinanceWorkbookBuilder $workbookBuilder,
        private readonly HouseholdGoogleSheetsExportBuilder $rawBuilder,
    ) {}

    /**
     * @return array{
     *     sheets: array<string, array{headers: list<string>, rows: list<list<mixed>>}>,
     *     counts: array<string, int>,
     *     mode: string,
     *     spreadsheetId?: string,
     *     spreadsheetUrl?: string,
     *     csvPath?: string,
     *     serviceAccountEmail?: string
     * }
     */
    public function export(
        Household $household,
        User $user,
        bool $withTrashed = false,
        bool $includeExchangeRates = true,
        bool $dryRun = false,
        ?string $csvOutputDir = null,
        ?string $shareWith = null,
        ?string $credentialsPath = null,
        ?string $spreadsheetId = null,
        bool $raw = false,
    ): array {
        if ($raw) {
            $sheets = $this->rawBuilder->build($household, $user, $withTrashed, $includeExchangeRates);
            $mode = 'raw';
        } else {
            $sheets = $this->workbookBuilder->build($household, $user);
            $mode = 'workbook';
        }

        $counts = [];
        foreach ($sheets as $title => $table) {
            $counts[$title] = count($table['rows']);
        }

        $result = [
            'sheets' => $sheets,
            'counts' => $counts,
            'mode' => $mode,
        ];

        if ($dryRun) {
            return $result;
        }

        if ($csvOutputDir !== null) {
            $result['csvPath'] = $this->writeCsvDirectory($csvOutputDir, $sheets);

            return $result;
        }

        $path = $credentialsPath ?: (string) config('services.google_sheets.credentials_path');
        $client = GoogleSheetsApiClient::fromCredentialsFile($path);
        $titles = array_keys($sheets);

        if ($spreadsheetId !== null && $spreadsheetId !== '') {
            $target = $client->prepareExistingSpreadsheet($spreadsheetId, $titles);
        } else {
            $title = sprintf(
                'Finanzamente — %s — %s',
                $household->name,
                now()->format('Y-m-d H:i')
            );
            $target = $client->createSpreadsheet($title, $titles);

            $email = $shareWith ?: (string) config('services.google_sheets.share_with');
            if ($email !== '') {
                $client->shareWithUser($target['spreadsheetId'], $email);
            }
        }

        $client->writeTables($target['spreadsheetId'], $sheets);

        if ($mode === 'workbook') {
            // Tabelle native prima: poi formati (date dd/mm/yyyy) così non vengono sovrascritti
            $client->createNativeTables($target['spreadsheetId'], $sheets);
            $client->applyWorkbookFormatting($target['spreadsheetId'], $sheets);

            if (isset($sheets['_Grafici']['chart_meta'])) {
                /** @var array{cashflow_rows: int, category_rows: int, account_rows: int} $meta */
                $meta = $sheets['_Grafici']['chart_meta'];
                $client->addFinanceDashboardCharts($target['spreadsheetId'], $meta);
            }

            $client->hideSheetByTitle($target['spreadsheetId'], '_Grafici');
        }

        $result['spreadsheetId'] = $target['spreadsheetId'];
        $result['spreadsheetUrl'] = $target['spreadsheetUrl'];
        $result['serviceAccountEmail'] = $client->clientEmail();
        $result['appsheet'] = [
            'supported' => false,
            'reason' => 'AppSheet API espone solo CRUD su app esistenti, non la creazione app da spreadsheet.',
            'howto' => 'Apri lo spreadsheet → Estensioni → AppSheet → Crea un\'app. Key AppSheet = colonne `_id` / `_conto_id` / `_categoria_id` / `_debito_id` / `_asset_id` (Label = Nome/Asset/Controparte). Tabelle: Conti, Portfolio, Debiti, Transazioni, Investimenti.',
            'url' => 'https://www.appsheet.com/',
        ];

        return $result;
    }

    /**
     * @param  array<string, array{headers: list<string>, rows: list<list<mixed>>, skip_header?: bool}>  $sheets
     */
    public function writeCsvDirectory(string $directory, array $sheets): string
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        foreach ($sheets as $title => $table) {
            $path = rtrim($directory, '/').'/'.$title.'.csv';
            $handle = fopen($path, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Impossibile creare CSV: {$path}");
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $skipHeader = (bool) ($table['skip_header'] ?? false);
            if (! $skipHeader && ($table['headers'] ?? []) !== []) {
                fputcsv($handle, $table['headers'], ';');
            }
            foreach ($table['rows'] as $row) {
                fputcsv($handle, array_map(
                    static fn ($value) => $value === null ? '' : (string) $value,
                    $row
                ), ';');
            }
            fclose($handle);
        }

        return $directory;
    }
}
