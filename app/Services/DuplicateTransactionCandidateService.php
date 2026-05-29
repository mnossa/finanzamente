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
                return [
                    'type' => self::PAIR_SAME_RECURRING,
                    'recurring_side' => null,
                    'manual_side' => null,
                    'recurring' => RecurringTransaction::query()->find($primaryFk),
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

        $recurrings = $cluster
            ->map(fn (Transaction $t) => $t->recurringTransaction)
            ->filter();

        if ($recurrings->count() !== $cluster->count()) {
            return false;
        }

        if (! $recurrings->every(fn (RecurringTransaction $r) => $r->isEnded())) {
            return false;
        }

        $frequency = (string) ($recurrings->first()->frequency ?? 'monthly');
        $periodKeys = $cluster
            ->map(fn (Transaction $t) => $this->periodKeyForDate(Carbon::parse($t->date), $frequency))
            ->unique();

        return $periodKeys->count() === $cluster->count();
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

        return $this->classifyPair($primary, $candidateTx)['type'] === self::PAIR_ENDED_RECURRING_HISTORY;
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

    public function entrySourceForSide(string $side, array $pair): string
    {
        if ($pair['type'] !== self::PAIR_RECURRING_VS_MANUAL) {
            return 'unknown';
        }

        if ($pair['recurring_side'] === $side) {
            return 'recurring';
        }

        if ($pair['manual_side'] === $side) {
            return 'manual';
        }

        return 'unknown';
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

        $frequency = (string) ($recPrimary->frequency ?? 'monthly');
        $primaryDate = Carbon::parse($primary->date);
        $candidateDate = Carbon::parse($candidate->date);

        return $this->periodKeyForDate($primaryDate, $frequency)
            !== $this->periodKeyForDate($candidateDate, $frequency);
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

    private function resolveRecurringForTransaction(Transaction $transaction): ?RecurringTransaction
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
