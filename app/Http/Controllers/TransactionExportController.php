<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesTransactionFilters;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    use AppliesTransactionFilters;

    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $householdId = (int) $user->active_household_id;

        $query = Transaction::with(['account:id,name', 'category:id,name', 'tags:id,name'])
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId));

        $this->applyTransactionFilters($query, $request, $householdId, $user);

        $filename = 'transazioni-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Data',
                'Descrizione',
                'Importo',
                'Tipo',
                'Conto',
                'Categoria',
                'Tag',
                'Ricorrente',
            ], ';');

            $query->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->chunk(500, function ($transactions) use ($handle) {
                    foreach ($transactions as $transaction) {
                        $type = (float) $transaction->amount >= 0 ? 'entrata' : 'uscita';
                        fputcsv($handle, [
                            $transaction->date->format('Y-m-d'),
                            $transaction->description ?? '',
                            number_format((float) $transaction->amount, 2, ',', ''),
                            $type,
                            $transaction->account?->name ?? '',
                            $transaction->category?->name ?? '',
                            $transaction->tags->pluck('name')->join(', '),
                            $transaction->recurring_transaction_id ? 'sì' : 'no',
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
