<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSheetsRequest;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\StoreImportLayoutRequest;
use App\Http\Requests\StoreImportTransactionsRequest;
use App\Jobs\ImportTransactionsJob;
use App\Models\Account;
use App\Models\BankImportLayout;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Services\GoogleDriveService;
use App\Services\TransactionImportColumnMappingAdvisor;
use App\Services\TransactionImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionImportController extends Controller
{
    public function __construct(
        private readonly TransactionImportService $importService,
        private readonly GoogleDriveService $driveService,
    ) {}

    /**
     * Restituisce gli import attivi (pending/processing) per il polling lato client.
     */
    public function importStatus(Request $request): JsonResponse
    {
        $user = Auth::user();
        $imports = TransactionImport::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'status', 'rows_total', 'rows_imported', 'created_at']);

        return response()->json(['activeImports' => $imports]);
    }

    /**
     * Mostra il wizard di importazione.
     */
    public function create(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'currency_code']);

        $userLayouts = BankImportLayout::where('user_id', $user->id)
            ->orWhere('household_id', $householdId)
            ->orderBy('name')
            ->get(['id', 'name', 'bank_name', 'icon', 'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding']);

        $categories = Category::where(function ($q) use ($householdId) {
            $q->where('household_id', $householdId)
                ->orWhereNull('household_id');
        })
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color', 'icon']);

        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('Transactions/Import', [
            'accounts' => $accounts,
            'userLayouts' => $userLayouts,
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Restituisce la lista dei fogli (sheets) di un file XLSX.
     * Accetta un file locale o un file da Google Drive.
     */
    public function sheets(ImportSheetsRequest $request): JsonResponse
    {
        $tempPath = null;

        try {
            $tempPath = $this->resolveFilePath($request);

            $extension = strtolower(pathinfo($tempPath, PATHINFO_EXTENSION));
            if ($extension !== 'xlsx') {
                return response()->json(['sheets' => []]);
            }

            $sheets = $this->importService->getXlsxSheets($tempPath);

            return response()->json(['sheets' => $sheets]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Impossibile leggere il file XLSX. Verifica formato e foglio selezionato.',
            ], 422);
        } finally {
            // Rimuovi il file temporaneo Google Drive (non i file locali)
            if ($tempPath !== null && $request->filled('google_drive_file_id') && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Analizza il file (CSV o XLSX) e restituisce l'anteprima delle righe parsate.
     */
    public function preview(PreviewImportRequest $request): JsonResponse
    {
        $tempPath = null;
        $isFromDrive = $request->filled('google_drive_file_id');

        try {
            $filePath = $this->resolveFilePath($request);
            $tempPath = $isFromDrive ? $filePath : null;
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $isXlsx = $extension === 'xlsx';
            $sheetIndex = (int) $request->input('sheet_index', 0);

            $layout = [
                'delimiter' => $request->input('delimiter', ','),
                'date_format' => $request->input('date_format'),
                'has_header' => $request->boolean('has_header', true),
                'encoding' => $request->input('encoding', 'UTF-8'),
                'column_mapping' => $request->input('column_mapping'),
            ];

            if ($isXlsx) {
                $rows = $this->importService->parseXlsx($filePath, $layout, $sheetIndex);
                $headers = $layout['has_header']
                    ? $this->importService->getXlsxHeaders($filePath, $sheetIndex)
                    : [];
            } else {
                $content = file_get_contents($filePath);
                $rows = $this->importService->parseCsv($content, $layout);

                // Estrai intestazioni dalla prima riga CSV
                $headers = [];
                if ($layout['has_header']) {
                    $lines = array_filter(
                        explode("\n", str_replace(["\r\n", "\r"], "\n", $content)),
                        fn ($l) => trim($l) !== '',
                    );
                    $lines = array_values($lines);
                    if (! empty($lines)) {
                        $handle = fopen('php://temp', 'r+');
                        fwrite($handle, $lines[0]);
                        rewind($handle);
                        $row = fgetcsv($handle, 0, $layout['delimiter'], '"', '\\');
                        fclose($handle);
                        $headers = $row !== false ? $row : [];
                    }
                }
            }

            $validated = $this->importService->validateRows($rows);

            $uniqueCategories = $this->uniqueField($validated['valid'], 'category_name');
            $uniqueAccounts = $this->uniqueField($validated['valid'], 'account_name');
            $uniqueCurrencies = $this->uniqueField($validated['valid'], 'currency_code');

            $columnMapping = $request->input('column_mapping', []);
            $mappingWarnings = is_array($columnMapping)
                ? TransactionImportColumnMappingAdvisor::warnings($columnMapping, $headers)
                : [];

            return response()->json([
                'headers' => $headers,
                'valid' => $validated['valid'],
                'invalid' => $validated['invalid'],
                'total' => count($rows),
                'valid_count' => count($validated['valid']),
                'invalid_count' => count($validated['invalid']),
                'unique_categories' => $uniqueCategories,
                'unique_accounts' => $uniqueAccounts,
                'unique_currencies' => $uniqueCurrencies,
                'mapping_warnings' => $mappingWarnings,
            ]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Errore durante la lettura del file. Verifica il tracciato e riprova.',
            ], 422);
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Raccoglie i valori distinti e non-vuoti di un campo da un array di righe validate.
     */
    private function uniqueField(array $rows, string $key): array
    {
        return collect($rows)
            ->pluck($key)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Risolve il percorso del file da elaborare:
     * - file locale caricato → pathname del file temporaneo PHP
     * - Google Drive → scarica il file e restituisce il percorso temp
     *
     * @throws \RuntimeException|\InvalidArgumentException
     */
    private function resolveFilePath(Request $request): string
    {
        if ($request->filled('google_drive_file_id')) {
            return $this->driveService->downloadFile(
                accessToken: $request->input('google_drive_access_token'),
                fileId: $request->input('google_drive_file_id'),
                mimeType: $request->input('google_drive_mime_type', 'text/csv'),
            );
        }

        return $request->file('csv_file')->getPathname();
    }

    /**
     * Controlla se le righe da importare hanno potenziali duplicati nel conto.
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['nullable', 'integer'],
            'rows' => ['required', 'array'],
            'rows.*.date' => ['required', 'date'],
            'rows.*.amount' => ['required', 'numeric'],
            'rows.*.account_id' => ['nullable', 'integer'],
        ]);

        $user = Auth::user();
        $globalAccount = $request->filled('account_id')
            ? Account::where('id', $request->input('account_id'))
                ->where('household_id', $user->active_household_id)
                ->first()
            : null;

        $accountsCache = $globalAccount ? [$globalAccount->id => $globalAccount] : [];
        $duplicates = [];
        foreach ($request->input('rows') as $index => $row) {
            // Risolvi il conto per questa riga (per-row oppure globale)
            $rowAccountId = isset($row['account_id']) ? (int) $row['account_id'] : null;
            if ($rowAccountId) {
                if (! isset($accountsCache[$rowAccountId])) {
                    $acc = Account::where('id', $rowAccountId)
                        ->where('household_id', $user->active_household_id)
                        ->first();
                    if ($acc) {
                        $accountsCache[$rowAccountId] = $acc;
                    }
                }
                $account = $accountsCache[$rowAccountId] ?? $globalAccount;
            } else {
                $account = $globalAccount;
            }

            if (! $account) {
                continue;
            }
            // Confronta per modulo: in DB le uscite sono spesso negative (categoria expense / FX)
            // mentre nel file l'importo può essere ancora positivo → altrimenti il secondo import
            // non rileva duplicati e procede senza modale.
            $rowAbs = abs((float) $row['amount']);
            $existing = Transaction::where('account_id', $account->id)
                ->whereDate('date', $row['date'])
                ->whereRaw('ABS(ABS(amount) - ?) < 0.005', [$rowAbs])
                ->get(['id', 'date', 'amount', 'description'])
                ->toArray();

            if (! empty($existing)) {
                $duplicates[] = [
                    'row_index' => $index,
                    'date' => $row['date'],
                    'amount' => (float) $row['amount'],
                    'description' => $row['description'] ?? '',
                    'existing' => $existing,
                ];
            }
        }

        return response()->json(['duplicates' => $duplicates]);
    }

    /**
     * Importa le transazioni selezionate.
     * Ogni riga può avere duplicate_action: 'import'|'ignore'|'replace'|'update'.
     */
    public function store(StoreImportTransactionsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $importRecord = TransactionImport::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'status' => 'pending',
            'rows_total' => count($validated['rows']),
        ]);

        ImportTransactionsJob::dispatch($user->id, $user->active_household_id, $validated, $importRecord->id);

        return redirect()
            ->route('transactions.index')
            ->with('info', 'Importazione avviata. Riceverai una notifica al termine.');
    }

    /**
     * Elenco dei layout salvati.
     */
    public function layouts(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $layouts = BankImportLayout::where('user_id', $user->id)
            ->orWhere('household_id', $householdId)
            ->orderBy('name')
            ->get();

        return Inertia::render('Transactions/ImportLayouts', [
            'layouts' => $layouts,
        ]);
    }

    /**
     * Salva un nuovo layout.
     */
    public function storeLayout(StoreImportLayoutRequest $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $layout = BankImportLayout::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'name' => $validated['name'],
            'bank_name' => $validated['bank_name'],
            'icon' => $validated['icon'] ?? null,
            'column_mapping' => $validated['column_mapping'],
            'delimiter' => $validated['delimiter'],
            'date_format' => $validated['date_format'],
            'has_header' => $validated['has_header'] ?? true,
            'encoding' => $validated['encoding'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Layout salvato con successo.',
                'layout' => $layout->only(['id', 'name', 'bank_name', 'icon', 'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding']),
            ]);
        }

        return redirect()
            ->route('bank-import-layouts.index')
            ->with('success', 'Layout salvato con successo.');
    }

    /**
     * Aggiorna un layout esistente.
     */
    public function updateLayout(StoreImportLayoutRequest $request, BankImportLayout $bankImportLayout): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $bankImportLayout);

        $validated = $request->validated();
        $bankImportLayout->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Layout aggiornato con successo.',
                'layout' => $bankImportLayout->fresh()->only(['id', 'name', 'bank_name', 'icon', 'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding']),
            ]);
        }

        return redirect()
            ->route('bank-import-layouts.index')
            ->with('success', 'Layout aggiornato con successo.');
    }

    /**
     * Elimina un layout.
     */
    public function destroyLayout(BankImportLayout $bankImportLayout): RedirectResponse
    {
        $this->authorize('delete', $bankImportLayout);

        $bankImportLayout->delete();

        return redirect()
            ->route('bank-import-layouts.index')
            ->with('success', 'Layout eliminato con successo.');
    }
}
