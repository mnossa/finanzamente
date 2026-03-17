<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSheetsRequest;
use App\Http\Requests\PreviewInvestmentImportRequest;
use App\Http\Requests\StoreImportInvestmentsRequest;
use App\Http\Requests\StoreInvestmentImportLayoutRequest;
use App\Models\Account;
use App\Models\BankImportLayout;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\Category;
use App\Services\GoogleDriveService;
use App\Services\InvestmentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentImportController extends Controller
{
    public function __construct(
        private readonly InvestmentImportService $importService,
        private readonly GoogleDriveService $driveService,
    ) {}

    /**
     * Mostra il wizard di importazione investimenti.
     */
    public function create(Request $request): Response
    {
        $user        = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'currency_code']);

        $userLayouts = BankImportLayout::where('model_type', 'investment')
            ->where(function ($q) use ($user, $householdId) {
                $q->where('user_id', $user->id)
                    ->orWhere('household_id', $householdId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'bank_name', 'icon', 'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding']);

        $assets = InvestmentAsset::select(['id', 'name', 'symbol', 'isin', 'type', 'currency_code'])
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Investments/Import', [
            'accounts'    => $accounts,
            'userLayouts' => $userLayouts,
            'assets'      => $assets,
            'assetTypes'  => InvestmentAsset::TYPES,
        ]);
    }

    /**
     * Restituisce la lista dei fogli (sheets) di un file XLSX.
     * Accetta un file locale o un file da Google Drive.
     */
    public function sheets(ImportSheetsRequest $request): \Illuminate\Http\JsonResponse
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
        } finally {
            if ($tempPath !== null && $request->filled('google_drive_file_id') && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Analizza il file (CSV o XLSX) e restituisce l'anteprima delle righe parsate
     * con risoluzione automatica degli asset tramite ticker/ISIN.
     */
    public function preview(PreviewInvestmentImportRequest $request): \Illuminate\Http\JsonResponse
    {
        $tempPath    = null;
        $isFromDrive = $request->filled('google_drive_file_id');

        try {
            $filePath   = $this->resolveFilePath($request);
            $tempPath   = $isFromDrive ? $filePath : null;
            $extension  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $isXlsx     = $extension === 'xlsx';
            $sheetIndex = (int) $request->input('sheet_index', 0);

            $layout = [
                'delimiter'      => $request->input('delimiter', ','),
                'date_format'    => $request->input('date_format', 'd/m/Y'),
                'has_header'     => $request->boolean('has_header', true),
                'encoding'       => $request->input('encoding', 'UTF-8'),
                'column_mapping' => $request->input('column_mapping'),
            ];

            if ($isXlsx) {
                $rows    = $this->importService->parseXlsx($filePath, $layout, $sheetIndex);
                $headers = $layout['has_header']
                    ? $this->importService->getXlsxHeaders($filePath, $sheetIndex)
                    : [];
            } else {
                $content = file_get_contents($filePath);
                $rows    = $this->importService->parseCsv($content, $layout);

                $headers = [];
                if ($layout['has_header']) {
                    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
                    $lines      = array_values(array_filter(
                        explode("\n", $normalized),
                        fn ($l) => trim($l) !== ''
                    ));
                    if (!empty($lines)) {
                        $handle = fopen('php://temp', 'r+');
                        fwrite($handle, $lines[0]);
                        rewind($handle);
                        $row     = fgetcsv($handle, 0, $layout['delimiter'], '"', '\\');
                        fclose($handle);
                        $headers = $row !== false ? $row : [];
                    }
                }
            }

            $validated = $this->importService->validateRows($rows);

            // Risolvi gli asset per le righe valide
            $validWithAssets   = $this->importService->resolveAssets($validated['valid']);
            $missingAssetCount = collect($validWithAssets)->where('asset_missing', true)->count();

            return response()->json([
                'headers'              => $headers,
                'valid'                => $validWithAssets,
                'invalid'              => $validated['invalid'],
                'total'                => count($rows),
                'valid_count'          => count($validated['valid']),
                'invalid_count'        => count($validated['invalid']),
                'missing_asset_count'  => $missingAssetCount,
            ]);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } finally {
            if ($tempPath !== null && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
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
                fileId:      $request->input('google_drive_file_id'),
                mimeType:    $request->input('google_drive_mime_type', 'text/csv'),
            );
        }

        return $request->file('csv_file')->getPathname();
    }

    /**
     * Importa gli investimenti in modo atomico.
     * Opzionalmente genera una Transaction di tipo spesa per ogni investimento.
     */
    public function store(StoreImportInvestmentsRequest $request): RedirectResponse
    {
        $user      = Auth::user();
        $validated = $request->validated();

        $account                = null;
        $createCashTransaction  = (bool) ($validated['create_cash_transaction'] ?? false);

        if (!empty($validated['account_id'])) {
            $account = Account::findOrFail($validated['account_id']);
            if ($account->household_id !== $user->active_household_id) {
                abort(403, 'Il conto non appartiene alla household attiva.');
            }
        }

        // Cerca o crea la categoria "Investimento" per le transazioni cash
        $investmentCategory = null;
        if ($createCashTransaction && $account !== null) {
            $investmentCategory = Category::firstOrCreate(
                [
                    'household_id' => $user->active_household_id,
                    'name'         => 'Investimento',
                    'type'         => 'expense',
                ],
                ['color' => '#6366f1', 'icon' => '📈']
            );
        }

        $imported = 0;

        DB::transaction(function () use ($user, $validated, $account, $createCashTransaction, $investmentCategory, &$imported) {
            $balanceDelta = 0.0;

            foreach ($validated['rows'] as $row) {
                $investment = Investment::create([
                    'user_id'      => $user->id,
                    'household_id' => $user->active_household_id,
                    'account_id'   => $account?->id,
                    'asset_id'     => $row['asset_id'],
                    'quantity'     => $row['quantity'],
                    'buy_price'    => $row['buy_price'],
                    'buy_date'     => $row['buy_date'],
                    'fees'         => $row['fees'] ?? null,
                    'notes'        => $row['notes'] ?? null,
                    'is_private'   => $row['is_private'] ?? false,
                ]);

                // Genera transazione cash se richiesto e conto disponibile
                if ($createCashTransaction && $account !== null) {
                    $totalCost   = (float) $investment->quantity * (float) $investment->buy_price
                        + (float) ($investment->fees ?? 0);
                    $description = "Acquisto investimento - {$investment->asset->name}";

                    Transaction::create([
                        'user_id'       => $user->id,
                        'account_id'    => $account->id,
                        'category_id'   => $investmentCategory?->id,
                        'amount'        => -$totalCost,
                        'currency_code' => $account->currency_code,
                        'date'          => $investment->buy_date,
                        'description'   => mb_substr($description, 0, 1000),
                        'is_private'    => $investment->is_private,
                    ]);

                    $balanceDelta -= $totalCost;
                }

                $imported++;
            }

            if ($createCashTransaction && $account !== null && $balanceDelta !== 0.0) {
                $account->current_balance += $balanceDelta;
                $account->save();
            }
        });

        $msg = "Importazione completata: {$imported} " .
            ($imported === 1 ? 'investimento importato' : 'investimenti importati') .
            ' con successo.';

        return redirect()
            ->route('investments.index')
            ->with('success', $msg);
    }

    /**
     * Elenco dei layout di import per investimenti.
     */
    public function layouts(Request $request): Response
    {
        $user        = Auth::user();
        $householdId = $user->active_household_id;

        $layouts = BankImportLayout::where('model_type', 'investment')
            ->where(function ($q) use ($user, $householdId) {
                $q->where('user_id', $user->id)
                    ->orWhere('household_id', $householdId);
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Investments/ImportLayouts', [
            'layouts' => $layouts,
        ]);
    }

    /**
     * Salva un nuovo layout di import per investimenti.
     */
    public function storeLayout(StoreInvestmentImportLayoutRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user      = Auth::user();
        $validated = $request->validated();

        $layout = BankImportLayout::create([
            'user_id'        => $user->id,
            'household_id'   => $user->active_household_id,
            'model_type'     => 'investment',
            'name'           => $validated['name'],
            'bank_name'      => $validated['bank_name'] ?? '',
            'icon'           => $validated['icon'] ?? null,
            'column_mapping' => $validated['column_mapping'],
            'delimiter'      => $validated['delimiter'],
            'date_format'    => $validated['date_format'],
            'has_header'     => $validated['has_header'] ?? true,
            'encoding'       => $validated['encoding'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Layout salvato con successo.',
                'layout'  => $layout->only(['id', 'name', 'bank_name', 'icon', 'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding']),
            ]);
        }

        return redirect()
            ->route('investments.import.layouts')
            ->with('success', 'Layout salvato con successo.');
    }

    /**
     * Aggiorna un layout di import per investimenti.
     */
    public function updateLayout(StoreInvestmentImportLayoutRequest $request, BankImportLayout $bankImportLayout): RedirectResponse
    {
        $this->authorize('update', $bankImportLayout);

        $validated = $request->validated();
        $bankImportLayout->update(array_merge($validated, ['model_type' => 'investment']));

        return redirect()
            ->route('investments.import.layouts')
            ->with('success', 'Layout aggiornato con successo.');
    }

    /**
     * Elimina un layout di import per investimenti.
     */
    public function destroyLayout(BankImportLayout $bankImportLayout): RedirectResponse
    {
        $this->authorize('delete', $bankImportLayout);

        $bankImportLayout->delete();

        return redirect()
            ->route('investments.import.layouts')
            ->with('success', 'Layout eliminato con successo.');
    }
}
