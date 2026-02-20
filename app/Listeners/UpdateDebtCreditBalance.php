<?php

namespace App\Listeners;

use App\Events\ModelChanged;
use App\Models\DebtCredit;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * UpdateDebtCreditBalance
 * 
 * Listener che aggiorna automaticamente l'importo pagato di un debito/credito
 * quando viene creata, modificata o eliminata una transazione associata.
 */
class UpdateDebtCreditBalance
{
    /**
     * Handle the event.
     */
    public function handle(ModelChanged $event): void
    {
        $model = $event->model;

        // Gestisci solo transazioni
        if (! $model instanceof Transaction) {
            return;
        }

        // Se la transazione non è associata a un debito/credito, non fare nulla
        if (! $model->debt_credit_id) {
            return;
        }

        $debtCredit = DebtCredit::lockForUpdate()->find($model->debt_credit_id);
        
        if (! $debtCredit) {
            return;
        }

        // Ricalcola l'importo totale pagato sommando tutte le transazioni associate
        $totalPaid = Transaction::where('debt_credit_id', $debtCredit->id)
            ->whereNull('deleted_at')
            ->sum(DB::raw('ABS(amount)'));

        $debtCredit->paid_amount = $totalPaid;

        // Aggiorna lo stato del debito/credito
        $remaining = $debtCredit->getRemainingAmount();
        
        if ($remaining <= 0.01) { // Tolleranza per arrotondamenti
            $debtCredit->status = 'closed';
        } elseif ($debtCredit->due_date && now()->isAfter($debtCredit->due_date)) {
            $debtCredit->status = 'overdue';
        } else {
            $debtCredit->status = 'open';
        }
        
        $debtCredit->save();
    }
}
