<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionSuggestion;
use App\Models\Transaction;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service per rilevare pattern di transazioni ricorrenti e proporre
 * la creazione di ricorrenze all'utente.
 */
class RecurrenceDetectionService
{
    /**
     * Numero minimo di occorrenze per considerare una sequenza ricorrente.
     */
    private const MIN_OCCURRENCES = 3;

    /**
     * Tolleranza percentuale massima nella varianza dei gap tra date (15 %).
     */
    private const GAP_TOLERANCE = 0.15;

    /**
     * Gap attesi in giorni per ciascuna frequenza.
     */
    private const FREQUENCY_GAPS = [
        'daily' => 1,
        'weekly' => 7,
        'monthly' => 30,
        'yearly' => 365,
    ];

    /**
     * Finestre di accettazione (±giorni) per ciascuna frequenza.
     */
    private const FREQUENCY_WINDOWS = [
        'daily' => 0,
        'weekly' => 1,
        'monthly' => 5,
        'yearly' => 15,
    ];

    private const VARIABLE_AMOUNT_LOOKBACK_MONTHS = 8;

    private const VARIABLE_AMOUNT_MAX_VARIANCE = 0.25;

    /**
     * Esegue il rilevamento per tutti gli account di un household
     * e persiste i nuovi suggerimenti trovati.
     *
     * @return int Numero di nuovi suggerimenti creati
     */
    public function detectForHousehold(int $householdId): int
    {
        $accountIds = Account::where('household_id', $householdId)
            ->where('active', true)
            ->pluck('id');

        $created = 0;
        foreach ($accountIds as $accountId) {
            $created += $this->detectForAccount($accountId);
        }

        return $created;
    }

    /**
     * Esegue il rilevamento per un singolo account.
     *
     * @return int Numero di nuovi suggerimenti creati
     */
    public function detectForAccount(int $accountId): int
    {
        // Carica le transazioni non già collegate a una ricorrenza,
        // non trasferimenti, non rimborsi — degli ultimi 36 mesi.
        $transactions = Transaction::where('account_id', $accountId)
            ->whereNull('recurring_transaction_id')
            ->whereNull('transfer_id')
            ->whereNull('refund_id')
            ->whereNull('inter_household_transfer_id')
            ->where('date', '>=', Carbon::today()->subMonths(36))
            ->orderBy('date')
            ->get(['id', 'user_id', 'account_id', 'category_id', 'amount', 'currency_code', 'date', 'description']);

        if ($transactions->isEmpty()) {
            return 0;
        }

        // Raggruppa per (amount, currency_code, category_id).
        // Per transazioni senza categoria raggruppiamo anche per description normalizzata.
        $groups = $transactions->groupBy(function (Transaction $t) {
            $desc = $t->category_id ? '' : $this->normalizeDescription($t->description ?? '');

            return implode('|', [
                bcmul((string) $t->amount, '1', 2),
                $t->currency_code,
                $t->category_id ?? '__none__',
                $desc,
            ]);
        });

        $created = 0;
        $usedTransactionIds = [];
        foreach ($groups as $group) {
            if ($group->count() < self::MIN_OCCURRENCES) {
                continue;
            }

            $suggestion = $this->analyzeCandidateGroup($group);
            if ($suggestion === null) {
                continue;
            }

            if ($this->suggestionAlreadyExists($suggestion)) {
                continue;
            }

            RecurringTransactionSuggestion::create($suggestion);
            $created++;
            $usedTransactionIds = array_merge($usedTransactionIds, $suggestion['transaction_ids'] ?? []);
        }

        $created += $this->detectVariableAmountPatterns($transactions, $usedTransactionIds);

        return $created;
    }

    /**
     * Collega le transazioni di un suggerimento accettato a una ricorrenza
     * e crea il record RecurringTransaction corrispondente.
     */
    public function acceptSuggestion(
        RecurringTransactionSuggestion $suggestion,
        RecurringTransactionService $recurringService,
        string $mode = 'auto'
    ): AcceptedRecurringSuggestion {
        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) ($suggestion->transaction_ids ?? [])
        )));

        $transactions = Transaction::withTrashed()
            ->whereIn('id', $ids)
            ->where('account_id', $suggestion->account_id)
            ->orderBy('date')
            ->get();

        if ($transactions->isEmpty()) {
            throw new DomainException(
                'Questo suggerimento non ha più transazioni collegate (forse eliminate dal registro). Ignoralo o rilancia il rilevamento.'
            );
        }

        DB::beginTransaction();
        try {
            $today = Carbon::today();
            $historicalTransactions = $transactions
                ->filter(fn (Transaction $t) => Carbon::parse($t->date)->lte($today))
                ->values();
            $futureTransactions = $transactions
                ->filter(fn (Transaction $t) => Carbon::parse($t->date)->gt($today))
                ->values();

            $startDate = $transactions->first()->date;
            $lastDate = $historicalTransactions->isNotEmpty()
                ? Carbon::parse($historicalTransactions->last()->date)
                : Carbon::parse($transactions->last()->date);
            $endDate = match ($mode) {
                'closed' => $lastDate,
                'closed_fill_gaps' => $lastDate,
                'active_fill_gaps' => null,
                'active' => null,
                default => $this->shouldAutoCloseAtLastDate(
                    $suggestion->detected_frequency,
                    $lastDate,
                    $suggestion->description,
                    $suggestion->account_id
                ) ? $lastDate : null,
            };

            $recurring = RecurringTransaction::create([
                'user_id' => $suggestion->user_id,
                'account_id' => $suggestion->account_id,
                'category_id' => $suggestion->category_id,
                'amount' => $suggestion->amount,
                'currency_code' => $suggestion->currency_code,
                'frequency' => $suggestion->detected_frequency,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'description' => $suggestion->description,
                // In modalità "*_fill_gaps" lasciamo null temporaneamente per
                // permettere il backfill dei buchi storici.
                'last_generated_date' => in_array($mode, ['closed_fill_gaps', 'active_fill_gaps'], true)
                    ? null
                    : ($historicalTransactions->isNotEmpty() ? $historicalTransactions->last()->date : null),
            ]);

            // Collega le transazioni storiche alla ricorrenza.
            if ($historicalTransactions->isNotEmpty()) {
                Transaction::withTrashed()->whereIn('id', $historicalTransactions->pluck('id')->all())->update([
                    'recurring' => true,
                    'recurring_transaction_id' => $recurring->id,
                ]);
            }

            // Rimuove le occorrenze future già registrate: verranno rigenerate
            // dal motore ricorrenze. Delete per modello (eventi ModelChanged → saldi).
            foreach ($futureTransactions as $futureTx) {
                if (! $futureTx->trashed()) {
                    $futureTx->delete();
                }
            }

            if ($mode === 'closed_fill_gaps' && $endDate) {
                $recurringService->backfillMissingOccurrences($recurring, Carbon::parse($endDate));
            }

            if ($mode === 'active_fill_gaps') {
                $recurringService->backfillMissingOccurrences($recurring, Carbon::today());
            }

            $suggestion->update(['status' => 'accepted']);

            DB::commit();

            Log::info('Suggerimento ricorrenza accettato', [
                'suggestion_id' => $suggestion->id,
                'recurring_transaction_id' => $recurring->id,
                'transactions_linked' => count($suggestion->transaction_ids),
                'removed_future_transactions' => $futureTransactions->count(),
            ]);

            return new AcceptedRecurringSuggestion($recurring->fresh(), $futureTransactions->count());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore accettazione suggerimento ricorrenza', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Metodi privati di analisi
    // -------------------------------------------------------------------------

    /**
     * Analizza un gruppo di transazioni candidate e restituisce i dati
     * per un suggerimento, oppure null se non è rilevabile una ricorrenza.
     *
     * @param  Collection<Transaction>  $group
     */
    private function analyzeCandidateGroup(Collection $group): ?array
    {
        $sorted = $group->sortBy('date')->values();
        $dates = $sorted->map(fn ($t) => Carbon::parse($t->date))->all();

        $frequency = $this->detectFrequency($dates);
        if ($frequency === null) {
            return null;
        }

        $confidence = $this->calculateConfidence($sorted, $frequency);
        $first = $sorted->first();

        return [
            'user_id' => $first->user_id,
            'account_id' => $first->account_id,
            'category_id' => $first->category_id,
            'amount' => $first->amount,
            'currency_code' => $first->currency_code,
            'description' => $this->representativeDescription($sorted),
            'detected_frequency' => $frequency,
            'confidence' => $confidence,
            'status' => 'pending',
            'transaction_ids' => $sorted->pluck('id')->all(),
        ];
    }

    /**
     * Rileva la frequenza dominante di una sequenza di date ordinate.
     *
     * @param  Carbon[]  $dates
     */
    private function detectFrequency(array $dates): ?string
    {
        if (count($dates) < self::MIN_OCCURRENCES) {
            return null;
        }

        // Calcola i gap consecutivi in giorni
        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $gaps[] = $dates[$i - 1]->diffInDays($dates[$i]);
        }

        $medianGap = $this->median($gaps);

        foreach (self::FREQUENCY_GAPS as $frequency => $expectedGap) {
            $window = self::FREQUENCY_WINDOWS[$frequency];
            if (abs($medianGap - $expectedGap) <= $window) {
                // Verifica che la varianza sia contenuta
                $variance = $this->relativeVariance($gaps, $medianGap);
                if (
                    $variance <= self::GAP_TOLERANCE
                    || $this->matchesFrequencyWithSingleGapOutlier($gaps, $medianGap)
                ) {
                    return $frequency;
                }
            }
        }

        return null;
    }

    /**
     * Calcola il livello di confidenza da 0.0 a 1.0.
     *
     * @param  Collection<Transaction>  $transactions
     */
    private function calculateConfidence(Collection $transactions, string $frequency): float
    {
        $score = 0.0;

        // Stesso importo esatto in tutte le transazioni
        $amounts = $transactions->pluck('amount')->map(fn ($a) => (float) $a)->unique();
        if ($amounts->count() === 1) {
            $score += 0.40;
        }

        // Categoria presente e coerente
        $categories = $transactions->pluck('category_id')->filter()->unique();
        if ($categories->count() === 1) {
            $score += 0.20;
        }

        // Descrizione simile
        $descs = $transactions->pluck('description')->filter()->map(
            fn ($d) => $this->normalizeDescription($d)
        )->unique();
        if ($descs->count() === 1) {
            $score += 0.20;
        }

        // Numero di occorrenze (bonus per più di 3)
        $count = $transactions->count();
        if ($count >= 6) {
            $score += 0.20;
        } elseif ($count >= 4) {
            $score += 0.10;
        }

        // Penalità se la frequenza è ambigua (daily/weekly su mensile reale, etc.)
        if ($frequency === 'daily' && $count < 7) {
            $score -= 0.10;
        }

        return max(0.0, min(1.0, round($score, 2)));
    }

    /**
     * Verifica se esiste già un suggerimento pending/accepted/ignored
     * con gli stessi parametri chiave.
     */
    private function suggestionAlreadyExists(array $data): bool
    {
        return RecurringTransactionSuggestion::where('account_id', $data['account_id'])
            ->where('amount', $data['amount'])
            ->where('currency_code', $data['currency_code'])
            ->where('detected_frequency', $data['detected_frequency'])
            ->whereIn('status', ['pending', 'accepted', 'ignored'])
            ->exists();
    }

    /**
     * Restituisce la descrizione più rappresentativa del gruppo.
     */
    private function representativeDescription(Collection $transactions): ?string
    {
        $descriptions = $transactions->pluck('description')->filter();
        if ($descriptions->isEmpty()) {
            return null;
        }

        // Usa la descrizione più frequente
        return $descriptions->countBy()->sortDesc()->keys()->first();
    }

    /**
     * Normalizza una descrizione rimuovendo numeri e caratteri non alfabetici.
     */
    private function normalizeDescription(string $description): string
    {
        $lower = mb_strtolower(trim($description));

        return preg_replace('/[^a-zàèéìòù\s]/u', '', $lower);
    }

    /**
     * Calcola la mediana di un array di valori numerici.
     *
     * @param  int[]  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);
        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2.0;
        }

        return (float) $values[$mid];
    }

    /**
     * Calcola la varianza relativa rispetto alla mediana.
     *
     * @param  int[]  $gaps
     */
    private function relativeVariance(array $gaps, float $median): float
    {
        if ($median === 0.0) {
            return 1.0;
        }
        $deviations = array_map(fn ($g) => abs($g - $median) / $median, $gaps);

        return array_sum($deviations) / count($deviations);
    }

    /**
     * Tolleranza per dataset reali: accetta una singola anomalia di gap
     * (es. un mese saltato) se il resto della sequenza è regolare.
     *
     * @param  int[]  $gaps
     */
    private function matchesFrequencyWithSingleGapOutlier(array $gaps, float $median): bool
    {
        if (count($gaps) < 5 || $median <= 0.0) {
            return false;
        }

        $deviations = array_map(fn ($g) => abs($g - $median) / $median, $gaps);
        rsort($deviations);
        array_shift($deviations); // rimuove l'outlier peggiore

        if (count($deviations) === 0) {
            return false;
        }

        $trimmedVariance = array_sum($deviations) / count($deviations);

        return $trimmedVariance <= self::GAP_TOLERANCE;
    }

    /**
     * Analizza eventuali buchi nella sequenza: se tra due movimenti consecutivi
     * passa più tempo del previsto, stima quante occorrenze mancano.
     *
     * @param  Carbon[]  $dates
     * @return array{
     *   has_gaps: bool,
     *   missing_occurrences: int,
     *   largest_gap_days: int,
     *   has_internal_gaps: bool,
     *   internal_missing_occurrences: int,
     *   has_trailing_gap: bool,
     *   trailing_missing_occurrences: int
     * }
     */
    public function calculateGapInsights(string $frequency, array $dates): array
    {
        if (count($dates) < 2) {
            return [
                'has_gaps' => false,
                'missing_occurrences' => 0,
                'largest_gap_days' => 0,
                'has_internal_gaps' => false,
                'internal_missing_occurrences' => 0,
                'has_trailing_gap' => false,
                'trailing_missing_occurrences' => 0,
            ];
        }

        usort($dates, fn (Carbon $a, Carbon $b) => $a->lt($b) ? -1 : 1);

        $expectedGapDays = self::FREQUENCY_GAPS[$frequency] ?? 30;
        $window = self::FREQUENCY_WINDOWS[$frequency] ?? 0;
        $maxAllowedGap = $expectedGapDays + $window;

        $missingOccurrences = 0;
        $largestGapDays = 0;
        $internalMissingOccurrences = 0;

        for ($i = 1; $i < count($dates); $i++) {
            $gapDays = $dates[$i - 1]->diffInDays($dates[$i]);
            if ($gapDays <= $maxAllowedGap) {
                continue;
            }

            $largestGapDays = max($largestGapDays, $gapDays);
            $estimatedMissing = max(1, (int) floor($gapDays / $expectedGapDays) - 1);
            $missingOccurrences += $estimatedMissing;
            $internalMissingOccurrences += $estimatedMissing;
        }

        $lastDate = end($dates);
        $trailingGapDays = $lastDate instanceof Carbon ? $lastDate->diffInDays(Carbon::today()) : 0;
        $hasTrailingGap = $trailingGapDays > $maxAllowedGap;
        $trailingMissingOccurrences = $hasTrailingGap
            ? max(1, (int) floor($trailingGapDays / $expectedGapDays) - 1)
            : 0;

        if ($hasTrailingGap) {
            $largestGapDays = max($largestGapDays, $trailingGapDays);
            $missingOccurrences += $trailingMissingOccurrences;
        }

        return [
            'has_gaps' => $missingOccurrences > 0,
            'missing_occurrences' => $missingOccurrences,
            'largest_gap_days' => $largestGapDays,
            'has_internal_gaps' => $internalMissingOccurrences > 0,
            'internal_missing_occurrences' => $internalMissingOccurrences,
            'has_trailing_gap' => $hasTrailingGap,
            'trailing_missing_occurrences' => $trailingMissingOccurrences,
        ];
    }

    /**
     * Se l'ultima occorrenza è troppo vecchia rispetto alla frequenza,
     * considera la ricorrenza dismessa e la chiude all'ultima data nota.
     */
    public function shouldAutoCloseAtLastDate(
        string $frequency,
        Carbon $lastDate,
        ?string $description = null,
        ?int $accountId = null
    ): bool {
        $expectedGapDays = self::FREQUENCY_GAPS[$frequency] ?? 30;
        $graceDays = max(2, $expectedGapDays * 2);
        $staleThreshold = Carbon::today()->subDays($graceDays);

        if (! $lastDate->lt($staleThreshold)) {
            return false;
        }

        // Protezione anti-falsi positivi: se esistono movimenti più recenti con
        // stessa descrizione sullo stesso conto, evitiamo la chiusura automatica.
        if ($accountId && $description && $this->hasNewerSimilarTransaction($accountId, $description, $lastDate)) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se esistono transazioni più recenti sullo stesso conto con
     * descrizione sostanzialmente equivalente.
     */
    private function hasNewerSimilarTransaction(int $accountId, string $description, Carbon $afterDate): bool
    {
        $normalizedTarget = $this->normalizeDescription($description);
        if ($normalizedTarget === '') {
            return false;
        }

        $candidates = Transaction::where('account_id', $accountId)
            ->whereDate('date', '>', $afterDate->toDateString())
            ->whereNotNull('description')
            ->whereNull('transfer_id')
            ->whereNull('refund_id')
            ->whereNull('inter_household_transfer_id')
            ->get(['description']);

        foreach ($candidates as $candidate) {
            if ($this->normalizeDescription((string) $candidate->description) === $normalizedTarget) {
                return true;
            }
        }

        return false;
    }

    /**
     * Secondo pass: rileva ricorrenze recenti con importo variabile.
     *
     * @param  Collection<Transaction>  $transactions
     * @param  int[]  $usedTransactionIds
     */
    private function detectVariableAmountPatterns(Collection $transactions, array $usedTransactionIds): int
    {
        $cutoffDate = Carbon::today()->subMonths(self::VARIABLE_AMOUNT_LOOKBACK_MONTHS);

        $recentTransactions = $transactions
            ->filter(fn (Transaction $t) => Carbon::parse($t->date)->gte($cutoffDate))
            ->filter(fn (Transaction $t) => ! in_array($t->id, $usedTransactionIds, true))
            ->values();

        if ($recentTransactions->count() < self::MIN_OCCURRENCES) {
            return 0;
        }

        $groups = $recentTransactions->groupBy(function (Transaction $t) {
            return implode('|', [
                $t->currency_code,
                $t->category_id ?? '__none__',
                $this->normalizeDescription((string) ($t->description ?? '')),
            ]);
        });

        $created = 0;
        foreach ($groups as $group) {
            if ($group->count() < self::MIN_OCCURRENCES) {
                continue;
            }

            $distinctAmounts = $group->pluck('amount')->map(fn ($a) => (float) $a)->unique();
            if ($distinctAmounts->count() < 2) {
                continue;
            }

            if (! $this->hasAcceptableVariableAmountVariance($group)) {
                continue;
            }

            $suggestion = $this->analyzeVariableCandidateGroup($group);
            if ($suggestion === null || $this->suggestionAlreadyExists($suggestion)) {
                continue;
            }

            RecurringTransactionSuggestion::create($suggestion);
            $created++;
        }

        return $created;
    }

    /**
     * @param  Collection<Transaction>  $group
     */
    private function analyzeVariableCandidateGroup(Collection $group): ?array
    {
        $sorted = $group->sortBy('date')->values();
        $dates = $sorted->map(fn ($t) => Carbon::parse($t->date))->all();

        $frequency = $this->detectFrequency($dates);
        if ($frequency === null) {
            return null;
        }

        $latest = $sorted->last();
        $confidence = min(0.9, max(0.5, $this->calculateConfidence($sorted, $frequency) - 0.1));

        return [
            'user_id' => $latest->user_id,
            'account_id' => $latest->account_id,
            'category_id' => $latest->category_id,
            'amount' => (float) $latest->amount,
            'currency_code' => $latest->currency_code,
            'description' => $this->representativeDescription($sorted),
            'detected_frequency' => $frequency,
            'confidence' => round($confidence, 2),
            'status' => 'pending',
            'transaction_ids' => $sorted->pluck('id')->all(),
        ];
    }

    /**
     * @param  Collection<Transaction>  $group
     */
    private function hasAcceptableVariableAmountVariance(Collection $group): bool
    {
        $amounts = $group->pluck('amount')->map(fn ($a) => (float) $a)->values()->all();
        $mean = array_sum($amounts) / max(1, count($amounts));
        if ($mean == 0.0) {
            return false;
        }

        $variance = array_sum(array_map(fn ($a) => (($a - $mean) ** 2), $amounts)) / max(1, count($amounts));
        $stdDev = sqrt($variance);
        $relativeVariance = abs($stdDev / $mean);

        return $relativeVariance <= self::VARIABLE_AMOUNT_MAX_VARIANCE;
    }
}
