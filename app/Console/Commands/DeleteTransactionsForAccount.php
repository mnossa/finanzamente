<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\InterHouseholdTransfer;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Console\Command;

class DeleteTransactionsForAccount extends Command
{
    protected $signature = 'transactions:delete-for-account
        {account_id : ID del conto (tabella accounts)}
        {--dry-run : Mostra solo quante transazioni verrebbero eliminate}
        {--force : Obbligatorio per eseguire la cancellazione (oltre a non usare --dry-run)}';

    protected $description = 'Elimina tutte le transazioni associate a un conto (saldi aggiornati; trasferimenti interni gestiti).';

    public function handle(): int
    {
        $accountId = (int) $this->argument('account_id');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Per eliminare davvero le transazioni passa --force oppure usa --dry-run per una simulazione.');

            return self::FAILURE;
        }

        $account = Account::query()->find($accountId);
        if (! $account) {
            $this->error("Conto {$accountId} non trovato.");

            return self::FAILURE;
        }

        $this->info("Conto: {$account->name} (ID {$account->id}, household {$account->household_id})");

        $transferIds = Transaction::query()
            ->where('account_id', $account->id)
            ->whereNotNull('transfer_id')
            ->pluck('transfer_id')
            ->unique()
            ->filter()
            ->values();

        if ($dryRun) {
            $nTransfers = $transferIds->count();
            $nTx = Transaction::query()->where('account_id', $account->id)->count();
            $this->line("Simulazione: verrebbero rimossi {$nTransfers} trasferimento/i interni (coppie di movimenti) e {$nTx} transazione/i sul conto.");

            return self::SUCCESS;
        }

        foreach ($transferIds as $tid) {
            Transfer::query()->find($tid)?->delete();
        }

        $deleted = 0;
        while (true) {
            $transaction = Transaction::query()
                ->where('account_id', $account->id)
                ->first();

            if (! $transaction) {
                break;
            }

            $interHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
                $q->where('source_transaction_id', $transaction->id)
                    ->orWhere('dest_transaction_id', $transaction->id);
            })->first();

            if ($interHouseholdTransfer) {
                $interHouseholdTransfer->delete();
                $deleted++;

                continue;
            }

            $transaction->delete();
            $deleted++;
        }

        $account->refresh();
        $this->info("Eliminate {$deleted} transazione/i (o coppie trasferimento). Saldo corrente conto: {$account->current_balance}.");

        return self::SUCCESS;
    }
}
