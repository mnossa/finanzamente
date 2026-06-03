<?php

namespace App\Services;

use App\Models\DuplicateTransactionCandidate;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class DuplicateTransactionDetectionService
{
    public function __construct(
        private readonly DuplicateTransactionClusterService $clusterService,
        private readonly DuplicateTransactionCandidateService $duplicateService,
    ) {}

    /**
     * @return array{created: int, pruned: int}
     */
    public function detectForUser(int $userId, int $windowDays = 3): array
    {
        $windowDays = max(1, $windowDays);

        $this->consolidatePendingCandidates($userId);
        $pruned = $this->pruneHistoricalEndedRecurringCandidates($userId);

        $transactions = Transaction::query()
            ->select(['id', 'user_id', 'account_id', 'description', 'amount', 'date', 'recurring_transaction_id'])
            ->with('recurringTransaction:id,description,frequency,end_date,amount,account_id')
            ->where('user_id', $userId)
            ->whereNotNull('description')
            ->orderBy('date')
            ->get();

        return [
            'created' => $this->detectClustersForUser($userId, $transactions, $windowDays),
            'pruned' => $pruned,
        ];
    }

    private function pruneHistoricalEndedRecurringCandidates(int $userId): int
    {
        $removed = 0;

        DuplicateTransactionCandidate::query()
            ->with([
                'primaryTransaction.recurringTransaction',
                'candidateTransaction.recurringTransaction',
            ])
            ->where('user_id', $userId)
            ->where('status', DuplicateTransactionCandidateService::STATUS_PENDING)
            ->orderBy('id')
            ->each(function (DuplicateTransactionCandidate $candidate) use (&$removed): void {
                if (! $this->duplicateService->shouldIgnoreCandidate($candidate)) {
                    return;
                }

                $candidate->delete();
                $removed++;
            });

        return $removed;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function detectClustersForUser(int $userId, Collection $transactions, int $windowDays): int
    {
        $userCreated = 0;

        $groups = $transactions->groupBy(fn (Transaction $t) => $this->clusterService->groupKey($t));

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $clusters = $this->clusterService->findClusters($group, $windowDays);

            foreach ($clusters as $cluster) {
                if ($this->duplicateService->shouldIgnoreCluster($cluster)) {
                    continue;
                }

                [$txA, $txB, $distance] = $this->clusterService->pickClosestPair($cluster);
                $clusterIds = $this->clusterService->clusterTransactionIds($cluster);

                if ($this->pendingClusterExists($userId, $clusterIds)) {
                    continue;
                }

                $this->removePendingOverlappingCluster($userId, $clusterIds);

                $candidate = DuplicateTransactionCandidate::firstOrCreate([
                    'primary_transaction_id' => min($txA->id, $txB->id),
                    'candidate_transaction_id' => max($txA->id, $txB->id),
                ], [
                    'user_id' => $userId,
                    'status' => DuplicateTransactionCandidateService::STATUS_PENDING,
                    'distance_days' => min(255, $distance),
                    'cluster_transaction_ids' => $clusterIds,
                ]);

                if ($candidate->wasRecentlyCreated) {
                    $userCreated++;
                }
            }
        }

        return $userCreated;
    }

    /**
     * Unisce segnalazioni pending che condividono movimenti (dati legacy a coppie).
     */
    private function consolidatePendingCandidates(int $userId): void
    {
        $pending = DuplicateTransactionCandidate::query()
            ->where('user_id', $userId)
            ->where('status', DuplicateTransactionCandidateService::STATUS_PENDING)
            ->get();

        if ($pending->count() < 2) {
            return;
        }

        $parent = [];
        $find = function (int $rowId) use (&$parent, &$find): int {
            if (! isset($parent[$rowId])) {
                $parent[$rowId] = $rowId;
            }
            if ($parent[$rowId] !== $rowId) {
                $parent[$rowId] = $find($parent[$rowId]);
            }

            return $parent[$rowId];
        };
        $union = function (int $a, int $b) use ($find, &$parent): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        };

        $rowIdsByTx = [];
        foreach ($pending as $row) {
            $parent[$row->id] = $row->id;
            foreach ($this->transactionIdsForCandidate($row) as $txId) {
                if (isset($rowIdsByTx[$txId])) {
                    $union($row->id, $rowIdsByTx[$txId]);
                } else {
                    $rowIdsByTx[$txId] = $row->id;
                }
            }
        }

        $buckets = [];
        foreach ($pending as $row) {
            $buckets[$find($row->id)][] = $row;
        }

        foreach ($buckets as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            $mergedIds = [];
            foreach ($rows as $row) {
                $mergedIds = array_merge($mergedIds, $this->transactionIdsForCandidate($row));
            }
            $mergedIds = array_values(array_unique(array_map('intval', $mergedIds)));
            sort($mergedIds);

            $keeper = $rows[0];

            foreach (array_slice($rows, 1) as $duplicateRow) {
                $duplicateRow->delete();
            }

            $this->upsertClusterRow($userId, $keeper, $mergedIds);
        }
    }

    /**
     * @param  int[]  $clusterIds
     */
    private function pendingClusterExists(int $userId, array $clusterIds): bool
    {
        return DuplicateTransactionCandidate::query()
            ->where('user_id', $userId)
            ->where('status', DuplicateTransactionCandidateService::STATUS_PENDING)
            ->where(function ($query) use ($clusterIds) {
                foreach ($clusterIds as $id) {
                    $query->orWhere('primary_transaction_id', $id)
                        ->orWhere('candidate_transaction_id', $id);
                }
            })
            ->exists();
    }

    /**
     * @param  int[]  $clusterIds
     */
    private function removePendingOverlappingCluster(int $userId, array $clusterIds): void
    {
        DuplicateTransactionCandidate::query()
            ->where('user_id', $userId)
            ->where('status', DuplicateTransactionCandidateService::STATUS_PENDING)
            ->where(function ($query) use ($clusterIds) {
                foreach ($clusterIds as $id) {
                    $query->orWhere('primary_transaction_id', $id)
                        ->orWhere('candidate_transaction_id', $id);
                }
            })
            ->delete();
    }

    /**
     * @param  int[]  $clusterIds
     */
    private function upsertClusterRow(int $userId, DuplicateTransactionCandidate $keeper, array $clusterIds): void
    {
        $transactions = Transaction::query()
            ->whereIn('id', $clusterIds)
            ->orderBy('date')
            ->get();

        if ($transactions->count() < 2) {
            return;
        }

        [$txA, $txB, $distance] = $this->clusterService->pickClosestPair($transactions);
        $keeper->update([
            'primary_transaction_id' => min($txA->id, $txB->id),
            'candidate_transaction_id' => max($txA->id, $txB->id),
            'distance_days' => min(255, $distance),
            'cluster_transaction_ids' => $clusterIds,
        ]);
    }

    /**
     * @return int[]
     */
    private function transactionIdsForCandidate(DuplicateTransactionCandidate $candidate): array
    {
        $ids = $candidate->cluster_transaction_ids
            ?? [$candidate->primary_transaction_id, $candidate->candidate_transaction_id];

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
