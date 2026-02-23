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
            ->get(['id', 'name', 'bank_name', 'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding']);

        return Inertia::render('Transactions/Import', [
            'accounts' => $accounts,
            'predefinedLayouts' => $this->importService->getPredefinedLayouts(),
            'userLayouts' => $userLayouts,
            'bankNames' => BankImportLayout::BANK_NAMES,
        ]);
    }

    /**
     * Analizza il CSV e restituisce l'anteprima delle righe parsate.
     */
    public function preview(PreviewImportRequest $request): \Illuminate\Http\JsonResponse
    {
        $file = $request->file('csv_file');
        $content = file_get_contents($file->getPathname());

        $layout = [
            'delimiter' => $request->input('delimiter'),
            'date_format' => $request->input('date_format'),
            'has_header' => $request->boolean('has_header', true),
            'encoding' => $request->input('encoding'),
            'column_mapping' => $request->input('column_mapping'),
        ];

        $rows = $this->importService->parseCsv($content, $layout);
        $validated = $this->importService->validateRows($rows);

        // Extract headers from first line if has_header
        $headers = [];
        if ($layout['has_header']) {
            $lines = array_filter(explode("\n", str_replace(["\r\n", "\r"], "\n", $content)), fn($l) => trim($l) !== '');
            $lines = array_values($lines);
            if (!empty($lines)) {
                $handle = fopen('php://temp', 'r+');
                fwrite($handle, $lines[0]);
                rewind($handle);
                $row = fgetcsv($handle, 0, $layout['delimiter'], '"', '\\');
                fclose($handle);
                $headers = $row !== false ? $row : [];
            }
        }

        return response()->json([
            'headers' => $headers,
            'valid' => $validated['valid'],
            'invalid' => $validated['invalid'],
            'total' => count($rows),
            'valid_count' => count($validated['valid']),
            'invalid_count' => count($validated['invalid']),
        ]);
    }

    /**
     * Importa le transazioni selezionate.
     */
    public function store(StoreImportTransactionsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $account = Account::findOrFail($validated['account_id']);

        $imported = 0;
        foreach ($validated['rows'] as $row) {
            $amount = (float) $row['amount'];
            $description = $row['description'];
            if (!empty($row['notes'])) {
                $description = $description . ' - ' . $row['notes'];
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => null,
                'amount' => $amount,
                'currency_code' => $account->currency_code,
                'date' => $row['date'],
                'description' => mb_substr($description, 0, 1000),
                'is_private' => false,
            ]);

            $account->current_balance += $amount;
            $imported++;
        }

        $account->save();

        return redirect()
            ->route('transactions.index')
            ->with('success', "Importazione completata: {$imported} transazioni importate con successo.");
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
            'bankNames' => BankImportLayout::BANK_NAMES,
        ]);
    }

    /**
     * Salva un nuovo layout.
     */
    public function storeLayout(StoreImportLayoutRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        BankImportLayout::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'name' => $validated['name'],
            'bank_name' => $validated['bank_name'],
            'column_mapping' => $validated['column_mapping'],
            'delimiter' => $validated['delimiter'],
            'date_format' => $validated['date_format'],
            'has_header' => $validated['has_header'] ?? true,
            'encoding' => $validated['encoding'],
        ]);

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
