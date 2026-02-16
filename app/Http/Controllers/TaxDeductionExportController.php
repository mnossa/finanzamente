<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
// use ZipArchive;

class TaxDeductionExportController extends Controller
{
    /**
     * Mostra la pagina di gestione detrazioni fiscali.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;
        $year = $request->input('year', now()->year);

        // Ottieni le transazioni detraibili per l'anno specificato
        $transactions = Transaction::with(['account:id,name,currency_code', 'account.currency:code', 'category:id,name,color,icon', 'attachments'])
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where('is_tax_deductible', true)
            ->where(function ($q) use ($year) {
                // Transazioni con tax_year impostato o con data nell'anno selezionato
                $q->where('tax_year', $year)
                  ->orWhere(function ($q) use ($year) {
                      $q->whereNull('tax_year')
                        ->whereYear('date', $year);
                  });
            })
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('date')
            ->get();

        // Mappa le transazioni nel formato atteso dal frontend
        $mappedTransactions = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'date' => $transaction->date->format('Y-m-d'),
                'description' => $transaction->description,
                'amount' => (float) $transaction->amount,
                'tax_deduction_rate' => (float) $transaction->tax_deduction_rate,
                'tax_deduction_type' => $transaction->tax_deduction_type,
                'tax_year' => $transaction->tax_year,
                'category' => $transaction->category ? [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                    'icon' => $transaction->category->icon,
                ] : null,
                'account' => [
                    'id' => $transaction->account->id,
                    'name' => $transaction->account->name,
                    'currency_code' => $transaction->account->currency->code ?? 'EUR',
                ],
            ];
        });

        // Raggruppa le transazioni per tipo
        $groupedByType = $mappedTransactions->groupBy('tax_deduction_type')->map(function ($group) {
            return $group->values()->all();
        })->all();

        // Calcola il summary nel formato atteso dal frontend
        $summary = [
            'total_transactions' => $mappedTransactions->count(),
            'total_amount' => (float) $mappedTransactions->sum(fn($t) => abs($t['amount'])),
            'total_deductible' => (float) $mappedTransactions->sum(fn($t) => abs($t['amount']) * $t['tax_deduction_rate'] / 100),
            'years' => $this->getAvailableYears($householdId),
            'grouped_by_type' => $groupedByType,
        ];

        return Inertia::render('TaxDeductions/Index', [
            'transactions' => $mappedTransactions->values()->all(),
            'summary' => $summary,
            'year' => (int) $year,
        ]);
    }

    /**
     * Esporta un PDF con il report delle detrazioni fiscali.
     */
    public function exportPdf(Request $request): Response
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $user = Auth::user();
        $householdId = $user->active_household_id;
        $year = $request->input('year');

        $transactions = Transaction::with(['account:id,name', 'category:id,name'])
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where('is_tax_deductible', true)
            ->where(function ($q) use ($year) {
                $q->where('tax_year', $year)
                  ->orWhere(function ($q) use ($year) {
                      $q->whereNull('tax_year')
                        ->whereYear('date', $year);
                  });
            })
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('tax_deduction_type')
            ->orderBy('date')
            ->get();

        // Genera HTML per il PDF
        $html = view('pdf.tax-deductions', [
            'transactions' => $transactions,
            'year' => $year,
            'user' => $user,
            'generatedAt' => now(),
        ])->render();

        // In una implementazione reale, useresti una libreria come Dompdf o Snappy
        // Per ora, restituiamo l'HTML
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="detrazioni_fiscali_' . $year . '.html"');
    }

    /**
     * Esporta uno ZIP con tutti gli allegati delle transazioni detraibili.
     */
    public function exportAttachments(Request $request): BinaryFileResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $user = Auth::user();
        $householdId = $user->active_household_id;
        $year = $request->input('year');

        $transactions = Transaction::with(['attachments', 'category:id,name'])
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where('is_tax_deductible', true)
            ->where(function ($q) use ($year) {
                $q->where('tax_year', $year)
                  ->orWhere(function ($q) use ($year) {
                      $q->whereNull('tax_year')
                        ->whereYear('date', $year);
                  });
            })
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->get();

        // Crea un file ZIP temporaneo
        $zipFilename = 'detrazioni_fiscali_' . $year . '_' . time() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFilename);

        // Assicurati che la directory temp esista
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Impossibile creare il file ZIP.');
        }

        // Aggiungi gli allegati al ZIP
        $fileCount = 0;
        foreach ($transactions as $transaction) {
            foreach ($transaction->attachments as $attachment) {
                $filePath = Storage::disk('private')->path($attachment->file_path);
                if (file_exists($filePath)) {
                    // Organizza per tipo di detrazione
                    $folder = $transaction->tax_deduction_type ?? 'altro';
                    $folder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
                    
                    // Nome file con data
                    $date = $transaction->date->format('Y-m-d');
                    $filename = $date . '_' . $attachment->filename;
                    
                    $zip->addFile($filePath, $folder . '/' . $filename);
                    $fileCount++;
                }
            }
        }

        // Aggiungi un file README
        $readme = "Detrazioni Fiscali - Anno $year\n";
        $readme .= "Generato il: " . now()->format('d/m/Y H:i') . "\n";
        $readme .= "Utente: {$user->name}\n\n";
        $readme .= "Totale allegati: $fileCount\n";
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        // Restituisci il file ZIP e poi elimina
        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend(true);
    }

    /**
     * Ottieni gli anni disponibili per le detrazioni fiscali.
     */
    private function getAvailableYears(int $householdId): array
    {
        $years = Transaction::whereHas('account', function ($q) use ($householdId) {
            $q->where('household_id', $householdId);
        })
            ->where('is_tax_deductible', true)
            ->selectRaw('COALESCE(tax_year, YEAR(date)) as year')
            ->distinct()
            ->pluck('year')
            ->sort()
            ->values()
            ->toArray();

        // Aggiungi l'anno corrente se non presente
        if (!in_array(now()->year, $years)) {
            $years[] = now()->year;
            sort($years);
        }

        return $years;
    }
}
