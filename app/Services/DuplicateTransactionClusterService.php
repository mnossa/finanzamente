<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Raggruppa transazioni simili in cluster connessi (evita N coppie per N movimenti).
 */
class DuplicateTransactionClusterService
{
    public function __construct(
        private readonly DuplicateTransactionCandidateService $duplicateCandidateService,
    ) {}

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Collection<int, Transaction>>
     */
    public function findClusters(Collection $transactions, int $windowDays): Collection
    {
        $sorted = $transactions->sortBy('date')->values();
        $count = $sorted->count();

        if ($count < 2) {
            return collect();
        }

        $parent = range(0, $count - 1);

        $find = function (int $x) use (&$parent, &$find): int {
            if ($parent[$x] !== $x) {
                $parent[$x] = $find($parent[$x]);
            }

            return $parent[$x];
        };

        $union = function (int $a, int $b) use (&$parent, $find): void {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootA] = $rootB;
            }
        };

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->areSimilarPair($sorted[$i], $sorted[$j], $windowDays)) {
                    $union($i, $j);
                }
            }
        }

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $root = $find($i);
            $buckets[$root][] = $i;
        }

        return collect($buckets)
            ->filter(fn (array $indices) => count($indices) >= 2)
            ->map(function (array $indices) use ($sorted) {
                return collect($indices)
                    ->map(fn (int $index) => $sorted[$index])
                    ->values();
            })
            ->values();
    }

    /**
     * Sceglie la coppia con date più vicine come rappresentante del cluster.
     *
     * @param  Collection<int, Transaction>  $cluster
     * @return array{0: Transaction, 1: Transaction, 2: int}
     */
    public function pickClosestPair(Collection $cluster): array
    {
        $list = $cluster->values();
        $bestDistance = PHP_INT_MAX;
        $bestA = $list[0];
        $bestB = $list[1];

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                $distance = abs((int) $list[$i]->date->diffInDays($list[$j]->date));
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestA = $list[$i];
                    $bestB = $list[$j];
                }
            }
        }

        return [$bestA, $bestB, $bestDistance];
    }

    /**
     * @param  Collection<int, Transaction>  $cluster
     * @return int[]
     */
    public function clusterTransactionIds(Collection $cluster): array
    {
        return $cluster->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    public function areSimilarPair(Transaction $a, Transaction $b, int $windowDays): bool
    {
        if (mb_strtolower(trim((string) $a->description)) !== mb_strtolower(trim((string) $b->description))) {
            return false;
        }

        if (round((float) $a->amount, 2) !== round((float) $b->amount, 2)) {
            return false;
        }

        if (abs((int) $a->date->diffInDays($b->date)) > $windowDays) {
            return false;
        }

        if ($this->duplicateCandidateService->areScheduledRecurringOccurrences($a, $b)) {
            return false;
        }

        return true;
    }

    /**
     * Normalizza descrizione + importo + conto per il raggruppamento iniziale.
     */
    public function groupKey(Transaction $transaction): string
    {
        $description = mb_strtolower(trim((string) $transaction->description));

        return implode('|', [
            (int) $transaction->account_id,
            bcmul((string) $transaction->amount, '1', 2),
            $description,
        ]);
    }
}
