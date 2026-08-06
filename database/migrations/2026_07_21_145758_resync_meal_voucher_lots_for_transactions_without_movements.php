<?php

use App\Models\Account;
use App\Models\MealVoucherLotMovement;
use App\Models\Transaction;
use App\Services\MealVoucherLedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot: TX già spostate su conti buoni pasto senza movimenti lotti
 * (update pre-fix) → allinea ticket count al ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meal_voucher_lot_movements') || ! Schema::hasTable('transactions')) {
            return;
        }

        $ledger = app(MealVoucherLedgerService::class);
        $txIdsWithMovements = MealVoucherLotMovement::query()
            ->distinct()
            ->pluck('transaction_id')
            ->all();

        Transaction::query()
            ->with('account')
            ->whereHas('account', fn ($q) => $q->where('type', Account::MEAL_VOUCHER_TYPE))
            ->when($txIdsWithMovements !== [], fn ($q) => $q->whereNotIn('id', $txIdsWithMovements))
            ->orderBy('id')
            ->each(function (Transaction $transaction) use ($ledger): void {
                try {
                    $ledger->resyncTransaction($transaction);
                } catch (InvalidArgumentException) {
                    // Importi non multipli / ticket insufficienti: lascia per correzione manuale.
                }
            });
    }

    public function down(): void
    {
        // Irreversibile: non rimuovere movimenti già allineati.
    }
};
