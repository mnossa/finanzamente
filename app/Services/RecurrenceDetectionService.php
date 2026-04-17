<?php

namespace App\Services;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionSuggestion;
use App\Models\Transaction;
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
        'daily'   => 1,
        'weekly'  => 7,
        'monthly' => 30,
        'yearly'  => 365,
    ];

    /**
     * Finestre di accettazione (±giorni) per ciascuna frequenza.
     */
    private const FREQUENCY_WINDOWS = [
        'daily'   => 0,
        'weekly'  => 1,
        'monthly' => 5,
        'yearly'  => 15,
    ];

    /**
     * Esegue il rilevamento per tutti gli account di un household
     * e persiste i nuovi suggerimenti trovati.
     *
     * @param int $householdId
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
     * @param int $accountId
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
        }

        return $created;
    }

    /**
     * Collega le transazioni di un suggerimento accettato a una ricorrenza
     * e crea il record RecurringTransaction corrispondente.
     *
     * @param RecurringTransactionSuggestion $suggestion
     * @param RecurringTransactionService $recurringService
     * @return RecurringTransaction
     */
    public function acceptSuggestion(
        RecurringTransactionSuggestion $suggestion,
        RecurringTransactionService $recurringService
    ): RecurringTransaction {
        DB::beginTransaction();
        try {
            $transactions = Transaction::whereIn('id', $suggestion->transaction_ids)
                ->orderBy('date')
                ->get();

            $startDate = $transactions->first()->date;

            $recurring = RecurringTransaction::create([
                'user_id'             => $suggestion->user_id,
                'account_id'          => $suggestion->account_id,
                'category_id'         => $suggestion->category_id,
                'amount'              => $suggestion->amount,
                'currency_code'       => $suggestion->currency_code,
                'frequency'           => $suggestion->detected_frequency,
                'start_date'          => $startDate,
                'end_date'            => null,
                'description'         => $suggestion->description,
                'last_generated_date' => $transactions->last()->date,
            ]);

            // Collega le transazioni esistenti alla ricorrenza
            Transaction::whereIn('id', $suggestion->transaction_ids)->update([
                'recurring'                => true,
                'recurring_transaction_id' => $recurring->id,
            ]);

            $suggestion->update(['status' => 'accepted']);

            DB::commit();

            Log::info('Suggerimento ricorrenza accettato', [
                'suggestion_id'          => $suggestion->id,
                'recurring_transaction_id' => $recurring->id,
                'transactions_linked'    => count($suggestion->transaction_ids),
            ]);

            return $recurring;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore accettazione suggerimento ricorrenza', [
                'suggestion_id' => $suggestion->id,
                'error'         => $e->getMessage(),
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
     * @param Collection<Transaction> $group
     * @return array|null
     */
    private function analyzeCandidateGroup(Collection $group): ?array
    {
        $sorted = $group->sortBy('date')->values();
        $dates  = $sorted->map(fn ($t) => Carbon::parse($t->date))->all();

        $frequency = $this->detectFrequency($dates);
        if ($frequency === null) {
            return null;
        }

        $confidence = $this->calculateConfidence($sorted, $frequency);
        $first      = $sorted->first();

        return [
            'user_id'            => $first->user_id,
            'account_id'         => $first->account_id,
            'category_id'        => $first->category_id,
            'amount'             => $first->amount,
            'currency_code'      => $first->currency_code,
            'description'        => $this->representativeDescription($sorted),
            'detected_frequency' => $frequency,
            'confidence'         => $confidence,
            'status'             => 'pending',
            'transaction_ids'    => $sorted->pluck('id')->all(),
        ];
    }

    /**
     * Rileva la frequenza dominante di una sequenza di date ordinate.
     *
     * @param Carbon[] $dates
     * @return string|null
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
                if ($variance <= self::GAP_TOLERANCE) {
                    return $frequency;
                }
            }
        }

        return null;
    }

    /**
     * Calcola il livello di confidenza da 0.0 a 1.0.
     *
     * @param Collection<Transaction> $transactions
     * @param string $frequency
     * @return float
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
     * @param int[] $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $mid   = (int) floor($count / 2);
        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2.0;
        }
        return (float) $values[$mid];
    }

    /**
     * Calcola la varianza relativa rispetto alla mediana.
     *
     * @param int[] $gaps
     */
    private function relativeVariance(array $gaps, float $median): float
    {
        if ($median === 0.0) {
            return 1.0;
        }
        $deviations = array_map(fn ($g) => abs($g - $median) / $median, $gaps);
        return array_sum($deviations) / count($deviations);
    }
}
