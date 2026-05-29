<?php

namespace App\Services;

use App\Models\DuplicateTransactionCandidate;
use App\Models\InterHouseholdTransfer;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DuplicateTransactionCandidateService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISMISSED = 'ignored';

    public const PAIR_MANUAL = 'manual';

    public const PAIR_RECURRING_VS_MANUAL = 'recurring_vs_manual';

    public const PAIR_SAME_RECURRING = 'same_recurring';

    public const PAIR_DIFFERENT_RECURRINGS = 'different_recurrings';

    public const PAIR_ENDED_RECURRING_HISTORY = 'ended_recurring_history';

    /** Rate della stessa ricorrenza in periodi distinti (es. mutuo mensile). */
    public const PAIR_RECURRING_OCCURRENCES = 'recurring_occurrences';

    /**
     * @return array{
     *     type: string,
     *     recurring_side: 'primary'|'candidate'|null,
     *     manual_side: 'primary'|'candidate'|null,
     *     recurring: RecurringTransaction|null
     * }
     */
    public function classifyPair(Transaction $primary, Transaction $candidate): array
    {
        $primaryFk = $primary->recurring_transaction_id;
        $candidateFk = $candidate->recurring_transaction_id;

        if ($primaryFk !== null && $candidateFk !== null) {
            if ((int) $primaryFk === (int) $candidateFk) {
                $recurring = $primary->recurringTransaction ?? RecurringTransaction::query()->find($primaryFk);

                if ($recurring && $this->areDistinctRecurringPeriods($primary, $candidate, $recurring)) {
                    return [
                        'type' => self::PAIR_RECURRING_OCCURRENCES,
                        'recurring_side' => null,
                        'manual_side' => null,
                        'recurring' => $recurring,
                    ];
                }

                return [
                    'type' => self::PAIR_SAME_RECURRING,
                    'recurring_side' => null,
                    'manual_side' => null,
                    'recurring' => $recurring,
                ];
            }

            if ($this->isEndedRecurringHistoryPair($primary, $candidate)) {
                return [
                    'type' => self::PAIR_ENDED_RECURRING_HISTORY,
                    'recurring_side' => null,
                    'manual_side' => null,
                    'recurring' => $primary->recurringTransaction,
                ];
            }

            return [
                'type' => self::PAIR_DIFFERENT_RECURRINGS,
                'recurring_side' => null,
                'manual_side' => null,
                'recurring' => null,
            ];
        }

        if ($primaryFk !== null xor $candidateFk !== null) {
            $recurringSide = $primaryFk !== null ? 'primary' : 'candidate';
            $recurring = RecurringTransaction::query()->find($primaryFk ?? $candidateFk);

            return [
                'type' => self::PAIR_RECURRING_VS_MANUAL,
                'recurring_side' => $recurringSide,
                'manual_side' => $recurringSide === 'primary' ? 'candidate' : 'primary',
                'recurring' => $recurring,
            ];
        }

        $primaryRecurring = $this->resolveRecurringForTransaction($primary);
        $candidateRecurring = $this->resolveRecurringForTransaction($candidate);

        if (
            $primaryRecurring !== null
            && $candidateRecurring !== null
            && (int) $primaryRecurring->id === (int) $candidateRecurring->id
        ) {
            if ($this->areDistinctRecurringPeriods($primary, $candidate, $primaryRecurring)) {
                return [
                    'type' => self::PAIR_RECURRING_OCCURRENCES,
                    'recurring_side' => null,
                    'manual_side' => null,
                    'recurring' => $primaryRecurring,
                ];
            }

            $recurringSide = $this->preferredRecurringSide($primary, $candidate, $primaryRecurring);

            return [
                'type' => self::PAIR_RECURRING_VS_MANUAL,
                'recurring_side' => $recurringSide,
                'manual_side' => $recurringSide === 'primary' ? 'candidate' : 'primary',
                'recurring' => $primaryRecurring,
            ];
        }

        if ($primaryRecurring !== null xor $candidateRecurring !== null) {
            $recurringSide = $primaryRecurring !== null ? 'primary' : 'candidate';
            $recurring = $primaryRecurring ?? $candidateRecurring;

            return [
                'type' => self::PAIR_RECURRING_VS_MANUAL,
                'recurring_side' => $recurringSide,
                'manual_side' => $recurringSide === 'primary' ? 'candidate' : 'primary',
                'recurring' => $recurring,
            ];
        }

        return [
            'type' => self::PAIR_MANUAL,
            'recurring_side' => null,
            'manual_side' => null,
            'recurring' => null,
        ];
    }

    /**
     * Elimina la transazione inserita a mano e mantiene quella generata dalla ricorrenza.
     */
    public function keepRecurringGenerated(
        DuplicateTransactionCandidate $candidate,
        User $user,
    ): void {
        $primary = $candidate->primaryTransaction;
        $candidateTx = $candidate->candidateTransaction;

        if ($primary === null || $candidateTx === null) {
            throw new InvalidArgumentException('Transazioni non trovate.');
        }

        $pair = $this->classifyPair($primary, $candidateTx);

        if ($pair['type'] !== self::PAIR_RECURRING_VS_MANUAL || $pair['manual_side'] === null) {
            throw new InvalidArgumentException('Questa coppia non è un duplicato ricorrenza vs manuale.');
        }

        $this->removeTransaction($candidate, $user, $pair['manual_side']);
    }

    /**
     * Risolve tutte le segnalazioni pending «ricorrenza vs inserimento manuale» per l'utente.
     */
    public function resolveAllRecurringVsManual(User $user): int
    {
        $resolved = 0;

        DuplicateTransactionCandidate::query()
            ->with(['primaryTransaction', 'candidateTransaction'])
            ->where('user_id', $user->id)
            ->where('status', self::STATUS_PENDING)
            ->orderBy('id')
            ->each(function (DuplicateTransactionCandidate $candidate) use ($user, &$resolved): void {
                $primary = $candidate->primaryTransaction;
                $candidateTx = $candidate->candidateTransaction;

                if ($primary === null || $candidateTx === null) {
                    return;
                }

                if ($this->classifyPair($primary, $candidateTx)['type'] !== self::PAIR_RECURRING_VS_MANUAL) {
                    return;
                }

                $this->keepRecurringGenerated($candidate, $user);
                $resolved++;
            });

        return $resolved;
    }

    /**
     * L'utente conferma che non si tratta di un duplicato: entrambe le transazioni restano.
     */
    public function dismiss(DuplicateTransactionCandidate $candidate): void
    {
        $candidate->update(['status' => self::STATUS_DISMISSED]);
    }

    /**
     * Elimina una delle due transazioni e chiude la segnalazione.
     *
     * @param  'primary'|'candidate'  $transactionToRemove
     */
    public function removeTransaction(
        DuplicateTransactionCandidate $candidate,
        User $user,
        string $transactionToRemove,
    ): void {
        if (! in_array($transactionToRemove, ['primary', 'candidate'], true)) {
            throw new InvalidArgumentException('transaction_to_remove non valido.');
        }

        $transaction = $transactionToRemove === 'primary'
            ? $candidate->primaryTransaction
            : $candidate->candidateTransaction;

        if ($transaction === null) {
            throw new InvalidArgumentException('Transazione non trovata.');
        }

        DB::transaction(function () use ($candidate, $transaction, $user): void {
            $this->deleteTransaction($transaction, $user);
            $candidate->delete();
        });
    }

    /**
     * Occorrenze collegate a ricorrenze già terminate, in periodi distinti: non sono duplicati.
     *
     * @param  Collection<int, Transaction>  $cluster
     */
    public function shouldIgnoreCluster(Collection $cluster): bool
    {
        if ($cluster->count() < 2) {
            return false;
        }

        $recurring = $this->resolveCommonRecurringForCluster($cluster);
        if ($recurring !== null) {
            return $this->clusterHasDistinctRecurringPeriods($cluster, $recurring);
        }

        return $this->shouldIgnoreEndedRecurringTemplateCluster($cluster);
    }

    /**
     * Stesso abbonamento rinnovato con più record ricorrenza terminati (periodi distinti).
     *
     * @param  Collection<int, Transaction>  $cluster
     */
    private function shouldIgnoreEndedRecurringTemplateCluster(Collection $cluster): bool
    {
        $recurrings = $cluster
            ->map(fn (Transaction $t) => $t->recurringTransaction)
            ->filter();

        if ($recurrings->count() !== $cluster->count()) {
            return false;
        }

        if (! $recurrings->every(fn (RecurringTransaction $r) => $r->isEnded())) {
            return false;
        }

        $template = $recurrings->first();

        if (! $recurrings->every(fn (RecurringTransaction $r) => $this->matchesSameRecurringTemplate($template, $r))) {
            return false;
        }

        return $this->clusterHasDistinctRecurringPeriods($cluster, $template);
    }

    /**
     * @param  Collection<int, Transaction>  $cluster
     */
    private function clusterHasDistinctRecurringPeriods(Collection $cluster, RecurringTransaction $recurring): bool
    {
        $frequency = (string) ($recurring->frequency ?? 'monthly');
        $periodKeys = $cluster
            ->map(fn (Transaction $t) => $this->periodKeyForDate(Carbon::parse($t->date), $frequency))
            ->unique();

        if ($periodKeys->count() !== $cluster->count()) {
            return false;
        }

        return $this->clusterHasMinimumOccurrenceSpacing($cluster, $frequency);
    }

    /**
     * @param  Collection<int, Transaction>  $cluster
     */
    private function clusterHasMinimumOccurrenceSpacing(Collection $cluster, string $frequency): bool
    {
        $dates = $cluster
            ->map(fn (Transaction $t) => Carbon::parse($t->date))
            ->sort()
            ->values();

        $minDays = $this->minDaysBetweenOccurrences($frequency);

        for ($i = 1; $i < $dates->count(); $i++) {
            if (abs((int) $dates[$i]->diffInDays($dates[$i - 1])) < $minDays) {
                return false;
            }
        }

        return true;
    }

    /**
     * Due movimenti sono rate della stessa ricorrenza in periodi di fatturazione distinti.
     */
    public function areScheduledRecurringOccurrences(Transaction $a, Transaction $b): bool
    {
        $recurring = $this->resolveCommonRecurringForPair($a, $b);

        return $recurring !== null
            && $this->areDistinctRecurringPeriods($a, $b, $recurring);
    }

    public function shouldIgnoreCandidate(DuplicateTransactionCandidate $candidate): bool
    {
        $ids = $candidate->cluster_transaction_ids
            ?? [$candidate->primary_transaction_id, $candidate->candidate_transaction_id];

        $transactions = Transaction::query()
            ->with('recurringTransaction:id,description,frequency,end_date,amount,account_id')
            ->whereIn('id', array_map('intval', $ids))
            ->get();

        if ($transactions->count() < 2) {
            return false;
        }

        if ($this->shouldIgnoreCluster($transactions)) {
            return true;
        }

        $primary = $candidate->primaryTransaction;
        $candidateTx = $candidate->candidateTransaction;

        if ($primary === null || $candidateTx === null) {
            return false;
        }

        $pairType = $this->classifyPair($primary, $candidateTx)['type'];

        return in_array($pairType, [
            self::PAIR_ENDED_RECURRING_HISTORY,
            self::PAIR_RECURRING_OCCURRENCES,
        ], true);
    }

    /**
     * Ricorrenza collegata o riconoscibile dal movimento (attiva o terminata).
     */
    public function resolveRecurringTemplateForTransaction(Transaction $transaction): ?RecurringTransaction
    {
        if ($transaction->recurring_transaction_id !== null) {
            return $transaction->recurringTransaction
                ?? RecurringTransaction::query()->find($transaction->recurring_transaction_id);
        }

        return $this->resolveRecurringForTransaction($transaction);
    }

    /**
     * Ricorrenza terminata il cui periodo copriva la data del movimento (per etichetta UI).
     */
    public function resolveEndedRecurringTemplateForTransaction(Transaction $transaction): ?RecurringTransaction
    {
        if ($transaction->recurring_transaction_id !== null) {
            $linked = $transaction->recurringTransaction;

            return ($linked && $linked->isEnded()) ? $linked : null;
        }

        $description = mb_strtolower(trim((string) $transaction->description));
        if ($description === '') {
            return null;
        }

        $amount = round((float) $transaction->amount, 2);
        $onDate = Carbon::parse($transaction->date);

        return RecurringTransaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('account_id', $transaction->account_id)
            ->whereNotNull('end_date')
            ->where('end_date', '<', Carbon::today())
            ->where('end_date', '>=', $onDate)
            ->where('start_date', '<=', $onDate)
            ->orderByDesc('end_date')
            ->get()
            ->first(function (RecurringTransaction $recurring) use ($description, $amount): bool {
                return mb_strtolower(trim((string) $recurring->description)) === $description
                    && round((float) $recurring->amount, 2) === $amount;
            });
    }

    public function entrySourceForSide(string $side, array $pair, ?Transaction $transaction = null): string
    {
        if (in_array($pair['type'], [self::PAIR_RECURRING_OCCURRENCES, self::PAIR_ENDED_RECURRING_HISTORY], true)) {
            return 'recurring';
        }

        if ($pair['type'] === self::PAIR_RECURRING_VS_MANUAL) {
            if ($pair['recurring_side'] === $side) {
                return 'recurring';
            }

            if ($pair['manual_side'] === $side) {
                return 'manual';
            }
        }

        if ($transaction?->recurring_transaction_id !== null) {
            return 'recurring';
        }

        if ($this->resolveRecurringTemplateForTransaction($transaction) !== null) {
            return 'recurring';
        }

        return 'unknown';
    }

    /**
     * @param  Collection<int, Transaction>  $cluster
     */
    private function resolveCommonRecurringForCluster(Collection $cluster): ?RecurringTransaction
    {
        $recurringIds = [];

        foreach ($cluster as $transaction) {
            $recurringId = $transaction->recurring_transaction_id
                ?? $this->resolveRecurringForTransaction($transaction)?->id;

            if ($recurringId === null) {
                return null;
            }

            $recurringIds[] = (int) $recurringId;
        }

        $unique = array_values(array_unique($recurringIds));

        if (count($unique) !== 1) {
            return null;
        }

        $first = $cluster->first();

        return $first->recurringTransaction ?? RecurringTransaction::query()->find($unique[0]);
    }

    private function resolveCommonRecurringForPair(Transaction $a, Transaction $b): ?RecurringTransaction
    {
        $idA = $a->recurring_transaction_id ?? $this->resolveRecurringForTransaction($a)?->id;
        $idB = $b->recurring_transaction_id ?? $this->resolveRecurringForTransaction($b)?->id;

        if ($idA === null || $idB === null || (int) $idA !== (int) $idB) {
            return null;
        }

        return $a->recurringTransaction ?? $b->recurringTransaction ?? RecurringTransaction::query()->find($idA);
    }

    private function areDistinctRecurringPeriods(
        Transaction $primary,
        Transaction $candidate,
        RecurringTransaction $recurring,
    ): bool {
        $frequency = (string) ($recurring->frequency ?? 'monthly');
        $primaryDate = Carbon::parse($primary->date);
        $candidateDate = Carbon::parse($candidate->date);

        if ($this->periodKeyForDate($primaryDate, $frequency) === $this->periodKeyForDate($candidateDate, $frequency)) {
            return false;
        }

        return abs((int) $primaryDate->diffInDays($candidateDate)) >= $this->minDaysBetweenOccurrences($frequency);
    }

    private function minDaysBetweenOccurrences(string $frequency): int
    {
        return match ($frequency) {
            'weekly' => 6,
            'daily' => 1,
            'yearly' => 335,
            default => 28,
        };
    }

    private function isEndedRecurringHistoryPair(Transaction $primary, Transaction $candidate): bool
    {
        $recPrimary = $primary->recurringTransaction;
        $recCandidate = $candidate->recurringTransaction;

        if ($recPrimary === null || $recCandidate === null) {
            return false;
        }

        if (! $recPrimary->isEnded() || ! $recCandidate->isEnded()) {
            return false;
        }

        if (! $this->matchesSameRecurringTemplate($recPrimary, $recCandidate)) {
            return false;
        }

        return $this->areDistinctRecurringPeriods($primary, $candidate, $recPrimary);
    }

    private function matchesSameRecurringTemplate(RecurringTransaction $a, RecurringTransaction $b): bool
    {
        if ((int) $a->account_id !== (int) $b->account_id) {
            return false;
        }

        if (round((float) $a->amount, 2) !== round((float) $b->amount, 2)) {
            return false;
        }

        return mb_strtolower(trim((string) $a->description))
            === mb_strtolower(trim((string) $b->description));
    }

    private function periodKeyForDate(Carbon $date, string $frequency): string
    {
        return match ($frequency) {
            'weekly' => $date->format('o-W'),
            'yearly' => $date->format('Y'),
            'daily' => $date->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }

    public function resolveRecurringForTransaction(Transaction $transaction): ?RecurringTransaction
    {
        $description = mb_strtolower(trim((string) $transaction->description));
        if ($description === '') {
            return null;
        }

        $amount = round((float) $transaction->amount, 2);

        return RecurringTransaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('account_id', $transaction->account_id)
            ->where('start_date', '<=', $transaction->date)
            ->where(function ($query) use ($transaction) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $transaction->date);
            })
            ->orderByDesc('start_date')
            ->get()
            ->first(function (RecurringTransaction $recurring) use ($description, $amount): bool {
                return mb_strtolower(trim((string) $recurring->description)) === $description
                    && round((float) $recurring->amount, 2) === $amount;
            });
    }

    /**
     * @return 'primary'|'candidate'
     */
    private function preferredRecurringSide(
        Transaction $primary,
        Transaction $candidate,
        RecurringTransaction $recurring,
    ): string {
        if ($primary->recurring_transaction_id !== null && $candidate->recurring_transaction_id === null) {
            return 'primary';
        }

        if ($candidate->recurring_transaction_id !== null && $primary->recurring_transaction_id === null) {
            return 'candidate';
        }

        if ($primary->recurring && ! $candidate->recurring) {
            return 'primary';
        }

        if ($candidate->recurring && ! $primary->recurring) {
            return 'candidate';
        }

        if ($recurring->last_generated_date !== null) {
            $lastGenerated = $recurring->last_generated_date->format('Y-m-d');
            if ($primary->date->format('Y-m-d') === $lastGenerated) {
                return 'primary';
            }
            if ($candidate->date->format('Y-m-d') === $lastGenerated) {
                return 'candidate';
            }
        }

        return $primary->date->lte($candidate->date) ? 'primary' : 'candidate';
    }

    private function deleteTransaction(Transaction $transaction, User $user): void
    {
        $this->authorizeTransaction($transaction, $user);

        $interHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
            $q->where('source_transaction_id', $transaction->id)
                ->orWhere('dest_transaction_id', $transaction->id);
        })->first();

        if ($interHouseholdTransfer) {
            $interHouseholdTransfer->delete();

            return;
        }

        $transaction->delete();

        $transaction->account?->recalculateBalance();
    }

    private function authorizeTransaction(Transaction $transaction, User $user): void
    {
        $account = $transaction->account;

        if ($account->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questa transazione.');
        }

        if ($transaction->is_private && $transaction->user_id !== $user->id) {
            abort(403, 'Questa transazione è privata.');
        }
    }
}
