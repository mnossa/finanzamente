<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\RecurrenceDateTolerance;
use App\Support\RecurringOccurrenceLabel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service per gestire la generazione delle transazioni ricorrenti.
 */
class RecurringTransactionService
{
    public function __construct(
        private readonly BusinessDayService $businessDayService,
    ) {}

    /**
     * Applica posticipo a primo giorno lavorativo (weekend e festività IT).
     */
    public function resolveOccurrenceDate(Carbon $theoreticalDate): Carbon
    {
        return $this->businessDayService->adjustToNextWorkingDay($theoreticalDate);
    }

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

                if (! RecurrenceDateTolerance::hasOccurrenceNearDate(
                    $recurringTransaction->id,
                    $occurrenceDate,
                    $recurringTransaction->frequency,
                )) {
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
                if (! RecurrenceDateTolerance::hasOccurrenceNearDate(
                    $recurringTransaction->id,
                    $occurrenceDate,
                    $recurringTransaction->frequency,
                )) {
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

        if (RecurrenceDateTolerance::hasOccurrenceNearDate(
            $recurringTransaction->id,
            $nextDate,
            $recurringTransaction->frequency,
        )) {
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
            'description' => RecurringOccurrenceLabel::buildDescriptionWithOccurrence(
                $recurringTransaction->description,
                $date,
                $recurringTransaction->frequency,
            ),
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
        $seenDates = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $resolved = $this->resolveOccurrenceDate($currentDate->copy());
            $dateKey = $resolved->toDateString();

            if (! isset($seenDates[$dateKey])) {
                $occurrences[] = $resolved;
                $seenDates[$dateKey] = true;
            }

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
    /**
     * @param  array<string>|null  $fields  Campi da sincronizzare; null = tutti tranne amount se escluso esplicitamente
     */
    public function syncLinkedTransactionsFromTemplate(
        RecurringTransaction $recurringTransaction,
        ?array $fields = null,
    ): int {
        $linkedTransactions = Transaction::query()
            ->where('recurring_transaction_id', $recurringTransaction->id)
            ->get();

        if ($linkedTransactions->isEmpty()) {
            return 0;
        }

        $syncFields = $fields ?? [
            'account_id',
            'category_id',
            'amount',
            'currency_code',
            'description',
            'debt_credit_id',
        ];

        $affectedAccountIds = [];

        foreach ($linkedTransactions as $transaction) {
            $affectedAccountIds[] = $transaction->account_id;

            $updates = [];
            if (in_array('account_id', $syncFields, true)) {
                $updates['account_id'] = $recurringTransaction->account_id;
            }
            if (in_array('category_id', $syncFields, true)) {
                $updates['category_id'] = $recurringTransaction->category_id;
            }
            if (in_array('amount', $syncFields, true)) {
                $updates['amount'] = $recurringTransaction->amount;
            }
            if (in_array('currency_code', $syncFields, true)) {
                $updates['currency_code'] = $recurringTransaction->currency_code;
            }
            if (in_array('description', $syncFields, true)) {
                $updates['description'] = RecurringOccurrenceLabel::buildDescriptionWithOccurrence(
                    $recurringTransaction->description,
                    Carbon::parse($transaction->date),
                    $recurringTransaction->frequency,
                );
            }
            if (in_array('debt_credit_id', $syncFields, true)) {
                $updates['debt_credit_id'] = $recurringTransaction->debt_credit_id;
            }

            if ($updates !== []) {
                $transaction->update($updates);
            }

            $affectedAccountIds[] = $recurringTransaction->account_id;
        }

        foreach (array_unique(array_filter($affectedAccountIds)) as $accountId) {
            Account::find($accountId)?->recalculateBalance();
        }

        return $linkedTransactions->count();
    }

    /**
     * Allinea le transazioni collegate alle occorrenze attese (add mancanti, remove eccedenze).
     */
    public function reconcileLinkedTransactions(RecurringTransaction $recurringTransaction): RecurringReconcileResult
    {
        $recurringTransaction->refresh();
        $today = Carbon::today();

        $endLimit = $recurringTransaction->end_date && $recurringTransaction->end_date->lt($today)
            ? $recurringTransaction->end_date
            : $today;

        if ($recurringTransaction->start_date->gt($endLimit)) {
            return new RecurringReconcileResult;
        }

        $expectedOccurrences = $this->calculateOccurrences(
            $recurringTransaction->start_date,
            $endLimit,
            $recurringTransaction->frequency
        );
        $frequency = $recurringTransaction->frequency;

        $linked = Transaction::query()
            ->where('recurring_transaction_id', $recurringTransaction->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $result = new RecurringReconcileResult;
        $affectedAccountIds = [];
        $slotAssignments = [];
        $toRemove = [];

        foreach ($linked as $transaction) {
            $txDate = Carbon::parse($transaction->date);
            $slotIndex = RecurrenceDateTolerance::findMatchingExpectedSlotIndex(
                $txDate,
                $expectedOccurrences,
                $frequency,
            );

            if ($slotIndex === null) {
                $toRemove[] = $transaction;

                continue;
            }

            if (! isset($slotAssignments[$slotIndex])) {
                $slotAssignments[$slotIndex] = $transaction;

                continue;
            }

            $existing = $slotAssignments[$slotIndex];
            $expectedDate = $expectedOccurrences[$slotIndex];
            $existingDistance = (int) abs(Carbon::parse($existing->date)->diffInDays($expectedDate));
            $newDistance = (int) abs($txDate->diffInDays($expectedDate));

            if ($newDistance < $existingDistance) {
                $toRemove[] = $existing;
                $slotAssignments[$slotIndex] = $transaction;
            } elseif ($newDistance === $existingDistance && $txDate->lt(Carbon::parse($existing->date))) {
                $toRemove[] = $existing;
                $slotAssignments[$slotIndex] = $transaction;
            } else {
                $toRemove[] = $transaction;
            }
        }

        DB::beginTransaction();
        try {
            foreach ($toRemove as $transaction) {
                $affectedAccountIds[] = $transaction->account_id;
                $transaction->delete();
                $result->removed++;
            }

            foreach ($expectedOccurrences as $index => $expectedDate) {
                if (isset($slotAssignments[$index])) {
                    continue;
                }

                if (RecurrenceDateTolerance::hasOccurrenceNearDate(
                    $recurringTransaction->id,
                    $expectedDate,
                    $frequency,
                )) {
                    continue;
                }

                $this->createTransactionFromRecurring(
                    $recurringTransaction,
                    $expectedDate->copy()
                );
                $affectedAccountIds[] = $recurringTransaction->account_id;
                $result->created++;
            }

            $this->syncLastGeneratedDateFromLinkedTransactions($recurringTransaction);

            foreach (array_unique(array_filter($affectedAccountIds)) as $accountId) {
                Account::find($accountId)?->recalculateBalance();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore riconciliazione ricorrenza', [
                'recurring_transaction_id' => $recurringTransaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Sincronizza template e riconcilia occorrenze (uso job o update sincrono).
     */
    public function syncAndReconcile(RecurringTransaction $recurringTransaction, bool $reconcileSchedule = true): array
    {
        $synced = $this->syncLinkedTransactionsFromTemplate($recurringTransaction);
        $reconcile = $reconcileSchedule
            ? $this->reconcileLinkedTransactions($recurringTransaction)
            : new RecurringReconcileResult;

        return [
            'synced' => $synced,
            'reconcile' => $reconcile,
        ];
    }

    public function countLinkedTransactions(RecurringTransaction $recurringTransaction): int
    {
        return Transaction::where('recurring_transaction_id', $recurringTransaction->id)->count();
    }

    /**
     * Data di decorrenza predefinita per un cambio importo: prossima occorrenza non ancora generata.
     */
    public function defaultEffectiveDateForAmountChange(RecurringTransaction $recurringTransaction): Carbon
    {
        $next = $this->calculateNextDueDate($recurringTransaction);
        if ($next) {
            return $next;
        }

        if ($recurringTransaction->last_generated_date) {
            return $this->resolveOccurrenceDate($recurringTransaction->last_generated_date->copy()->addDay());
        }

        return $this->resolveOccurrenceDate($recurringTransaction->start_date->copy());
    }

    /**
     * Chiude la ricorrenza corrente e ne crea una nuova dal cambio importo in avanti.
     *
     * @param  array{account_id: int, category_id: int, amount: float, frequency: string, description: ?string, debt_credit_id: ?int, currency_code: string}  $attributes
     */
    public function forkOnAmountChange(
        RecurringTransaction $old,
        array $attributes,
        Carbon $effectiveDate,
    ): RecurringTransaction {
        $effectiveDate = $this->resolveOccurrenceDate($effectiveDate);

        if ($old->last_generated_date && $effectiveDate->lte($old->last_generated_date)) {
            throw new \InvalidArgumentException(
                'La data di decorrenza deve essere successiva all\'ultima occorrenza già registrata.'
            );
        }

        if ($effectiveDate->lt($old->start_date)) {
            throw new \InvalidArgumentException('La data di decorrenza non può precedere la data di inizio della ricorrenza.');
        }

        return DB::transaction(function () use ($old, $attributes, $effectiveDate) {
            $closeEnd = $effectiveDate->copy()->subDay();
            if ($closeEnd->lt($old->start_date)) {
                $closeEnd = $old->start_date->copy();
            }

            $old->update(['end_date' => $closeEnd]);

            $new = RecurringTransaction::create([
                'user_id' => $old->user_id,
                'account_id' => $attributes['account_id'],
                'category_id' => $attributes['category_id'],
                'amount' => $attributes['amount'],
                'currency_code' => $attributes['currency_code'],
                'frequency' => $attributes['frequency'],
                'start_date' => $effectiveDate,
                'end_date' => null,
                'description' => $attributes['description'] ?? null,
                'debt_credit_id' => $attributes['debt_credit_id'] ?? null,
                'last_generated_date' => null,
                'predecessor_recurring_transaction_id' => $old->id,
            ]);

            $old->update(['successor_recurring_transaction_id' => $new->id]);

            $this->generateTransactionsUntil($new);

            return $new;
        });
    }
}
