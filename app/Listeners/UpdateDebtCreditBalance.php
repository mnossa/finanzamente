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

        // Raccoglie tutti gli ID di debiti/crediti da ricalcolare
        $debtCreditIds = [];

        // ID attuale dopo la modifica
        if ($model->debt_credit_id) {
            $debtCreditIds[] = (int) $model->debt_credit_id;
        }

        // Se è un aggiornamento e debt_credit_id è cambiato, ricalcola anche il vecchio debito
        if ($event->action === 'updated' && array_key_exists('debt_credit_id', $model->getChanges())) {
            $oldDebtCreditId = $model->getOriginal('debt_credit_id');
            if ($oldDebtCreditId && (int) $oldDebtCreditId !== (int) $model->debt_credit_id) {
                $debtCreditIds[] = (int) $oldDebtCreditId;
            }
        }

        if (empty($debtCreditIds)) {
            return;
        }

        foreach (array_unique($debtCreditIds) as $id) {
            $this->recalculateBalance($id);
        }
    }

    /**
     * Ricalcola il saldo pagato e lo stato di un debito/credito.
     */
    private function recalculateBalance(int $debtCreditId): void
    {
        DB::transaction(function () use ($debtCreditId) {
            $debtCredit = DebtCredit::lockForUpdate()->find($debtCreditId);

            if (! $debtCredit) {
                return;
            }

            // Ricalcola l'importo totale pagato sommando tutte le transazioni associate
            $totalPaid = Transaction::where('debt_credit_id', $debtCreditId)
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
        });
    }
}
