<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\StoreImportLayoutRequest;
use App\Http\Requests\StoreImportTransactionsRequest;
use App\Models\Account;
use App\Models\BankImportLayout;
use App\Models\Transaction;
use App\Services\TransactionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionImportController extends Controller
{
    public function __construct(
        private readonly TransactionImportService $importService
    ) {}

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

        return Inertia::render('Transactions/Import', [
            'accounts' => $accounts,
            'userLayouts' => $userLayouts,
        ]);
    }

    /**
     * Analizza il file (CSV o XLSX) e restituisce l'anteprima delle righe parsate.
     */
    public function preview(PreviewImportRequest $request): \Illuminate\Http\JsonResponse
    {
        $file      = $request->file('csv_file');
        $extension = strtolower($file->getClientOriginalExtension());
        $isXlsx    = $extension === 'xlsx';

        $layout = [
            'delimiter'      => $request->input('delimiter', ','),
            'date_format'    => $request->input('date_format'),
            'has_header'     => $request->boolean('has_header', true),
            'encoding'       => $request->input('encoding', 'UTF-8'),
            'column_mapping' => $request->input('column_mapping'),
        ];

        if ($isXlsx) {
            $filePath  = $file->getPathname();
            $rows      = $this->importService->parseXlsx($filePath, $layout);
            $headers   = $layout['has_header']
                ? $this->importService->getXlsxHeaders($filePath)
                : [];
        } else {
            $content = file_get_contents($file->getPathname());
            $rows    = $this->importService->parseCsv($content, $layout);

            // Estrai intestazioni dalla prima riga CSV
            $headers = [];
            if ($layout['has_header']) {
                $lines = array_filter(
                    explode("\n", str_replace(["\r\n", "\r"], "\n", $content)),
                    fn ($l) => trim($l) !== '',
                );
                $lines = array_values($lines);
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

        return response()->json([
            'headers'       => $headers,
            'valid'         => $validated['valid'],
            'invalid'       => $validated['invalid'],
            'total'         => count($rows),
            'valid_count'   => count($validated['valid']),
            'invalid_count' => count($validated['invalid']),
        ]);
    }

    /**
     * Controlla se le righe da importare hanno potenziali duplicati nel conto.
     */
    public function checkDuplicates(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'account_id'    => ['required', 'integer'],
            'rows'          => ['required', 'array'],
            'rows.*.date'   => ['required', 'date'],
            'rows.*.amount' => ['required', 'numeric'],
        ]);

        $user    = Auth::user();
        $account = Account::where('id', $request->input('account_id'))
            ->where('household_id', $user->active_household_id)
            ->firstOrFail();

        $duplicates = [];
        foreach ($request->input('rows') as $index => $row) {
            $existing = Transaction::where('account_id', $account->id)
                ->whereDate('date', $row['date'])
                ->whereRaw('ABS(amount - ?) < 0.005', [(float) $row['amount']])
                ->get(['id', 'date', 'amount', 'description'])
                ->toArray();

            if (!empty($existing)) {
                $duplicates[] = [
                    'row_index'   => $index,
                    'date'        => $row['date'],
                    'amount'      => (float) $row['amount'],
                    'description' => $row['description'] ?? '',
                    'existing'    => $existing,
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
        $user      = Auth::user();
        $validated = $request->validated();
        $account   = Account::findOrFail($validated['account_id']);

        $imported = 0;
        $skipped  = 0;

        foreach ($validated['rows'] as $row) {
            $action = $row['duplicate_action'] ?? 'import';

            if ($action === 'ignore') {
                $skipped++;
                continue;
            }

            $amount      = (float) $row['amount'];
            $description = $row['description'];
            if (!empty($row['notes'])) {
                $description .= ' - ' . $row['notes'];
            }
            $description = mb_substr($description, 0, 1000);

            if (in_array($action, ['replace', 'update'], true) && !empty($row['duplicate_transaction_id'])) {
                $existing = Transaction::where('id', (int) $row['duplicate_transaction_id'])
                    ->where('account_id', $account->id)
                    ->first();

                if ($existing) {
                    $oldAmount = (float) $existing->amount;
                    if ($action === 'replace') {
                        $account->current_balance -= $oldAmount;
                        $existing->delete();
                        Transaction::create([
                            'user_id'       => $user->id,
                            'account_id'    => $account->id,
                            'category_id'   => null,
                            'amount'        => $amount,
                            'currency_code' => $account->currency_code,
                            'date'          => $row['date'],
                            'description'   => $description,
                            'is_private'    => false,
                        ]);
                    } else {
                        $existing->update([
                            'amount'      => $amount,
                            'date'        => $row['date'],
                            'description' => $description,
                        ]);
                        $account->current_balance -= $oldAmount;
                    }
                    $account->current_balance += $amount;
                    $imported++;
                    continue;
                }
            }

            Transaction::create([
                'user_id'       => $user->id,
                'account_id'    => $account->id,
                'category_id'   => null,
                'amount'        => $amount,
                'currency_code' => $account->currency_code,
                'date'          => $row['date'],
                'description'   => $description,
                'is_private'    => false,
            ]);
            $account->current_balance += $amount;
            $imported++;
        }

        $account->save();

        $msg = "Importazione completata: {$imported} " . ($imported === 1 ? 'transazione importata' : 'transazioni importate') . ' con successo.';
        if ($skipped > 0) {
            $msg .= " {$skipped} " . ($skipped === 1 ? 'transazione ignorata' : 'transazioni ignorate') . '.';
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', $msg);
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
    public function storeLayout(StoreImportLayoutRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
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
    public function updateLayout(StoreImportLayoutRequest $request, BankImportLayout $bankImportLayout): RedirectResponse
    {
        $this->authorize('update', $bankImportLayout);

        $validated = $request->validated();
        $bankImportLayout->update($validated);

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
