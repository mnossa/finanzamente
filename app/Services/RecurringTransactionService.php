<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service per gestire la generazione delle transazioni ricorrenti.
 */
class RecurringTransactionService
{
    /**
     * Genera tutte le transazioni mancanti dalla data di inizio fino alla data target.
     *
     * @param  Carbon|null  $targetDate  Data fino alla quale generare le transazioni (default: oggi)
     * @return int Numero di transazioni generate
     */
    public function generateTransactionsUntil(RecurringTransaction $recurringTransaction, ?Carbon $targetDate = null): int
    {
        $targetDate = $targetDate ?? Carbon::today();
        $generatedCount = 0;

        // Ricarica il model per avere i dati più aggiornati dal database
        $recurringTransaction->refresh();

        // Sincronizza last_generated_date con le transazioni esistenti
        $this->syncLastGeneratedDate($recurringTransaction);

        // Se c'è una end_date e è prima del target, usa quella come limite
        $endLimit = $recurringTransaction->end_date && $recurringTransaction->end_date->lt($targetDate)
            ? $recurringTransaction->end_date
            : $targetDate;

        // Calcola tutte le occorrenze teoriche dalla start_date fino all'endLimit
        $occurrences = $this->calculateOccurrences(
            $recurringTransaction->start_date,
            $endLimit,
            $recurringTransaction->frequency
        );

        DB::beginTransaction();
        try {
            $lastGenerated = null;

            foreach ($occurrences as $occurrenceDate) {
                // Salta le occorrenze già generate (se esiste last_generated_date)
                if ($recurringTransaction->last_generated_date &&
                    $occurrenceDate->lte($recurringTransaction->last_generated_date)) {
                    continue;
                }

                // Verifica che non esista già una transazione per questa data
                $exists = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
                    ->whereDate('date', $occurrenceDate->toDateString())
                    ->exists();

                if (! $exists) {
                    $this->createTransactionFromRecurring($recurringTransaction, $occurrenceDate->copy());
                    $generatedCount++;
                    $lastGenerated = $occurrenceDate->copy();
                }
            }

            // Aggiorna la data dell'ultima generazione solo se ci sono state modifiche
            if ($lastGenerated) {
                $recurringTransaction->last_generated_date = $lastGenerated;
                $recurringTransaction->save();
            }

            DB::commit();

            Log::info('Transazioni ricorrenti generate', [
                'recurring_transaction_id' => $recurringTransaction->id,
                'count' => $generatedCount,
                'last_generated_date' => $lastGenerated?->format('Y-m-d'),
            ]);

            return $generatedCount;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore nella generazione transazioni ricorrenti', [
                'recurring_transaction_id' => $recurringTransaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Backfill storico: genera solo le occorrenze mancanti tra start_date e target,
     * senza sincronizzare last_generated_date prima del calcolo.
     */
    public function backfillMissingOccurrences(RecurringTransaction $recurringTransaction, Carbon $targetDate): int
    {
        $generatedCount = 0;
        $recurringTransaction->refresh();

        $endLimit = $recurringTransaction->end_date && $recurringTransaction->end_date->lt($targetDate)
            ? $recurringTransaction->end_date
            : $targetDate;

        $occurrences = $this->calculateOccurrences(
            $recurringTransaction->start_date,
            $endLimit,
            $recurringTransaction->frequency
        );

        DB::beginTransaction();
        try {
            foreach ($occurrences as $occurrenceDate) {
                $exists = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
                    ->whereDate('date', $occurrenceDate->toDateString())
                    ->exists();

                if (! $exists) {
                    $this->createTransactionFromRecurring($recurringTransaction, $occurrenceDate->copy());
                    $generatedCount++;
                }
            }

            // Dopo il backfill sincronizza all'ultima data effettivamente presente.
            $this->syncLastGeneratedDate($recurringTransaction);

            DB::commit();

            return $generatedCount;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Genera la prossima transazione ricorrente.
     */
    public function generateNextTransaction(RecurringTransaction $recurringTransaction): ?Transaction
    {
        $nextDate = $this->calculateNextDueDate($recurringTransaction);

        if (! $nextDate) {
            return null;
        }

        // Verifica che non esista già
        $exists = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
            ->whereDate('date', $nextDate)
            ->exists();

        if ($exists) {
            return null;
        }

        DB::beginTransaction();
        try {
            $transaction = $this->createTransactionFromRecurring($recurringTransaction, $nextDate);

            $recurringTransaction->last_generated_date = $nextDate;
            $recurringTransaction->save();

            DB::commit();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore nella generazione transazione singola', [
                'recurring_transaction_id' => $recurringTransaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Crea una transazione dalla ricorrente per una specifica data.
     */
    private function createTransactionFromRecurring(RecurringTransaction $recurringTransaction, Carbon $date): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $recurringTransaction->user_id,
            'account_id' => $recurringTransaction->account_id,
            'category_id' => $recurringTransaction->category_id,
            'amount' => $recurringTransaction->amount,
            'currency_code' => $recurringTransaction->currency_code,
            'date' => $date,
            'description' => $recurringTransaction->description,
            'recurring' => true,
            'recurring_transaction_id' => $recurringTransaction->id,
            'debt_credit_id' => $recurringTransaction->debt_credit_id,
            'is_private' => false,
        ]);

        // Aggiorna il saldo del conto
        $account = $recurringTransaction->account;
        $account->current_balance += (float) $recurringTransaction->amount;
        $account->save();

        return $transaction;
    }

    /**
     * Calcola tutte le occorrenze di una ricorrenza tra due date.
     *
     * @param  Carbon  $startDate  Data di inizio
     * @param  Carbon  $endDate  Data di fine
     * @param  string  $frequency  Frequenza (daily, weekly, monthly, yearly)
     * @return array Array di oggetti Carbon con tutte le occorrenze
     */
    private function calculateOccurrences(Carbon $startDate, Carbon $endDate, string $frequency): array
    {
        $occurrences = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $occurrences[] = $currentDate->copy();

            switch ($frequency) {
                case 'daily':
                    $currentDate->addDay();
                    break;
                case 'weekly':
                    $currentDate->addWeek();
                    break;
                case 'monthly':
                    $currentDate->addMonth();
                    break;
                case 'yearly':
                    $currentDate->addYear();
                    break;
            }
        }

        return $occurrences;
    }

    /**
     * Calcola la prossima data di scadenza per una transazione ricorrente.
     */
    public function calculateNextDueDate(RecurringTransaction $rt): ?Carbon
    {
        $today = Carbon::today();

        // Se la data di fine è passata, non c'è prossima scadenza
        if ($rt->end_date && $rt->end_date->lt($today)) {
            return null;
        }

        // Determina da quale data cercare la prossima occorrenza
        $searchFrom = $rt->last_generated_date
            ? $rt->last_generated_date->copy()
            : $rt->start_date->copy()->subDay(); // Sottraggo un giorno per includere start_date

        // Calcola le occorrenze dalla searchFrom fino a un anno nel futuro
        // (un anno dovrebbe essere sufficiente per trovare la prossima occorrenza)
        $futureLimit = $today->copy()->addYear();

        if ($rt->end_date && $rt->end_date->lt($futureLimit)) {
            $futureLimit = $rt->end_date;
        }

        $occurrences = $this->calculateOccurrences(
            $rt->start_date,
            $futureLimit,
            $rt->frequency
        );

        // Trova la prima occorrenza dopo searchFrom
        foreach ($occurrences as $occurrence) {
            if ($occurrence->gt($searchFrom)) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * Verifica se la transazione ricorrente è ancora attiva.
     */
    public function isActive(RecurringTransaction $rt): bool
    {
        $today = Carbon::today();

        // Non ancora iniziata
        if ($rt->start_date->gt($today)) {
            return true;
        }

        // Ha una data di fine ed è passata
        if ($rt->end_date && $rt->end_date->lt($today)) {
            return false;
        }

        return true;
    }

    /**
     * Genera le transazioni per tutte le ricorrenti attive fino alla data target.
     *
     * @return array ['total_recurring' => int, 'total_generated' => int]
     */
    public function processAllRecurringTransactions(?Carbon $targetDate = null): array
    {
        $targetDate = $targetDate ?? Carbon::today();
        $totalGenerated = 0;
        $totalRecurring = 0;

        // Ottieni tutte le ricorrenti attive
        $recurringTransactions = RecurringTransaction::where(function ($q) use ($targetDate) {
            $q->where('start_date', '<=', $targetDate)
                ->where(function ($q2) use ($targetDate) {
                    $q2->whereNull('end_date')
                        ->orWhere('end_date', '>=', $targetDate);
                });
        })->get();

        foreach ($recurringTransactions as $recurring) {
            $totalRecurring++;
            $count = $this->generateTransactionsUntil($recurring, $targetDate);
            $totalGenerated += $count;
        }

        return [
            'total_recurring' => $totalRecurring,
            'total_generated' => $totalGenerated,
        ];
    }

    /**
     * Rimuove transazioni collegate con data oltre oggi e riallinea last_generated_date.
     * Utile per ricorrenze create prima del fix che lasciavano occorrenze future manuali.
     *
     * @return int Numero di transazioni eliminate (soft delete)
     */
    public function removeFutureLinkedTransactions(RecurringTransaction $recurringTransaction): int
    {
        $today = Carbon::today()->toDateString();
        $removed = 0;

        $transactions = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
            ->whereDate('date', '>', $today)
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->delete();
            $removed++;
        }

        if ($removed > 0) {
            $this->syncLastGeneratedDateFromLinkedTransactions($recurringTransaction);
        }

        return $removed;
    }

    /**
     * Sincronizza last_generated_date dall'ultima transazione collegata ancora attiva.
     * Se non ci sono transazioni collegate, il campo viene azzerato.
     */
    public function syncLastGeneratedDateFromLinkedTransactions(RecurringTransaction $recurringTransaction): void
    {
        $recurringTransaction->refresh();

        $lastTransaction = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
            ->orderBy('date', 'desc')
            ->first();

        if (! $lastTransaction) {
            $recurringTransaction->last_generated_date = null;
            $recurringTransaction->saveQuietly();

            return;
        }

        $lastTransactionDate = Carbon::parse($lastTransaction->date);
        $current = $recurringTransaction->last_generated_date;

        if ($current === null || ! $current->isSameDay($lastTransactionDate)) {
            $recurringTransaction->last_generated_date = $lastTransactionDate;
            $recurringTransaction->saveQuietly();
        }
    }

    /**
     * Sincronizza il campo last_generated_date basandosi sulle transazioni esistenti.
     * Questo previene duplicazioni quando il comando viene eseguito più volte.
     */
    private function syncLastGeneratedDate(RecurringTransaction $recurringTransaction): void
    {
        $this->syncLastGeneratedDateFromLinkedTransactions($recurringTransaction);
    }

    /**
     * Allinea tutte le transazioni collegate al template della ricorrenza aggiornato.
     */
    public function syncLinkedTransactionsFromTemplate(RecurringTransaction $recurringTransaction): int
    {
        $linkedTransactions = Transaction::query()
            ->where('recurring_transaction_id', $recurringTransaction->id)
            ->get();

        if ($linkedTransactions->isEmpty()) {
            return 0;
        }

        $affectedAccountIds = [];

        foreach ($linkedTransactions as $transaction) {
            $affectedAccountIds[] = $transaction->account_id;

            $transaction->update([
                'account_id' => $recurringTransaction->account_id,
                'category_id' => $recurringTransaction->category_id,
                'amount' => $recurringTransaction->amount,
                'currency_code' => $recurringTransaction->currency_code,
                'description' => $recurringTransaction->description,
                'debt_credit_id' => $recurringTransaction->debt_credit_id,
            ]);

            $affectedAccountIds[] = $recurringTransaction->account_id;
        }

        foreach (array_unique(array_filter($affectedAccountIds)) as $accountId) {
            Account::find($accountId)?->recalculateBalance();
        }

        return $linkedTransactions->count();
    }
}
