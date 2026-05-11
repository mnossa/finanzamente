<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Elimina transazioni Future collegate a una ricorrenza (soft delete),
 * poi riallinea last_generated_date. Corregge dati legacy come mutuo con rate future anticipate.
 */
class StripFutureLinkedRecurringTransactions extends Command
{
    protected $signature = 'recurring:strip-future
                            {--id=* : ID della ricorrenza (Ripetibile)}
                            {--description-contains= : Filtra ricorrenze la cui descrizione contiene questo testo (case-insensitive)}
                            {--household= : Limita alla household (ID)}
                            {--dry-run : Mostra cosa verrebbe fatto senza modificare il database}';

    protected $description = 'Rimuove transazioni collegate alla ricorrenza con data oltre oggi e riallinea la data dell\'ultima generazione';

    public function handle(RecurringTransactionService $recurringService): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $this->option('id')))));
        $needle = trim((string) $this->option('description-contains'));
        $householdId = $this->option('household') !== null && $this->option('household') !== ''
            ? (int) $this->option('household')
            : null;

        if ($ids === [] && $needle === '') {
            $this->error('Specificare almeno --id=... oppure --description-contains=...');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = RecurringTransaction::query()->with('account:id,household_id');

        if ($householdId !== null) {
            $query->whereHas('account', fn ($q) => $q->where('household_id', $householdId));
        }

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($needle !== '') {
            $query->whereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($needle).'%']);
        }

        /** @var Collection<int, RecurringTransaction> $recurrings */
        $recurrings = $query->orderBy('id')->get();

        if ($recurrings->isEmpty()) {
            $this->warn('Nessuna ricorrenza trovata con i filtri indicati.');

            return self::SUCCESS;
        }

        $today = now()->toDateString();

        foreach ($recurrings as $recurring) {
            $count = Transaction::where('recurring_transaction_id', $recurring->id)
                ->whereDate('date', '>', $today)
                ->count();

            $this->line(sprintf(
                'Ricorrenza #%d — %s (household sul conto: %s): transazioni future collegate = %d',
                $recurring->id,
                mb_substr((string) $recurring->description, 0, 80),
                $recurring->account?->household_id ?? 'n/d',
                $count
            ));

            if ($count === 0) {
                continue;
            }

            if ($dryRun) {
                $this->warn('  (dry-run: nessuna modifica)');

                continue;
            }

            $removed = $recurringService->removeFutureLinkedTransactions($recurring);
            $this->info("  Rimosse {$removed} transazioni future e sincronizzata last_generated_date.");
        }

        return self::SUCCESS;
    }
}
