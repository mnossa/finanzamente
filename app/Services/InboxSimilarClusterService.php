<?php

namespace App\Services;

use App\Models\InboxItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Raggruppa voci Inbox pendenti simili (stesso conto/categoria/descrizione/tipo,
 * data entro una finestra — tipicamente ±1 giorno per mezzanotte).
 * Gli importi possono differire: il merge somma.
 */
class InboxSimilarClusterService
{
    /**
     * @param  Collection<int, InboxItem>  $items
     * @return Collection<int, Collection<int, InboxItem>>
     */
    public function findClusters(Collection $items, int $windowDays = 1): Collection
    {
        $eligible = $items
            ->filter(fn (InboxItem $item) => $item->amount !== null && $this->effectiveDate($item) !== null)
            ->values();

        $count = $eligible->count();
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
                if ($this->areSimilarPair($eligible[$i], $eligible[$j], $windowDays)) {
                    $union($i, $j);
                }
            }
        }

        $buckets = [];
        for ($i = 0; $i < $count; $i++) {
            $buckets[$find($i)][] = $i;
        }

        return collect($buckets)
            ->filter(fn (array $indices) => count($indices) >= 2)
            ->map(function (array $indices) use ($eligible) {
                return collect($indices)
                    ->map(fn (int $index) => $eligible[$index])
                    ->sortBy(fn (InboxItem $item) => $this->effectiveDate($item)?->timestamp ?? 0)
                    ->values();
            })
            ->values();
    }

    public function areSimilarPair(InboxItem $a, InboxItem $b, int $windowDays = 1): bool
    {
        if (($a->type ?? 'expense') !== ($b->type ?? 'expense')) {
            return false;
        }

        if (mb_strtolower(trim((string) ($a->description ?? ''))) !== mb_strtolower(trim((string) ($b->description ?? '')))) {
            return false;
        }

        if ((int) ($a->account_id ?? 0) !== (int) ($b->account_id ?? 0)) {
            return false;
        }

        if ((int) ($a->category_id ?? 0) !== (int) ($b->category_id ?? 0)) {
            return false;
        }

        $dateA = $this->effectiveDate($a);
        $dateB = $this->effectiveDate($b);
        if ($dateA === null || $dateB === null) {
            return false;
        }

        return abs((int) $dateA->diffInDays($dateB)) <= $windowDays;
    }

    public function effectiveDate(InboxItem $item): ?Carbon
    {
        if ($item->transaction_date !== null) {
            return Carbon::parse($item->transaction_date)->startOfDay();
        }

        if ($item->created_at !== null) {
            return Carbon::parse($item->created_at)->startOfDay();
        }

        return null;
    }
}
