<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Suggerisce chip di categorie frequenti per l'inserimento rapido transazioni.
 */
class TransactionQuickChipService
{
    private const LOOKBACK_DAYS = 90;

    private const MAX_CHIPS = 8;

    /**
     * @return array<int, array{category_id: int, label: string, icon: string|null, color: string|null, type: string, account_id: int}>
     */
    public function forUser(User $user): array
    {
        $householdId = $user->active_household_id;
        if ($householdId === null) {
            return [];
        }

        $since = Carbon::today()->subDays(self::LOOKBACK_DAYS);

        $transactions = Transaction::query()
            ->whereNotNull('category_id')
            ->where('date', '>=', $since)
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->get(['id', 'category_id', 'account_id', 'date']);

        if ($transactions->isEmpty()) {
            return [];
        }

        $fallbackAccountId = $this->resolveFallbackAccountId($user, $householdId);

        $categoryIds = $this->rankCategoryIds($transactions);

        if ($categoryIds->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->get(['id', 'name', 'type', 'color', 'icon'])
            ->keyBy('id');

        $chips = [];
        foreach ($categoryIds as $categoryId) {
            $category = $categories->get($categoryId);
            if ($category === null) {
                continue;
            }

            $accountId = $this->resolveAccountIdForCategory($transactions, $categoryId, $fallbackAccountId);
            if ($accountId === null) {
                continue;
            }

            $chips[] = [
                'category_id' => (int) $category->id,
                'label' => $category->name,
                'icon' => $category->icon,
                'color' => $category->color,
                'type' => $category->type,
                'account_id' => $accountId,
            ];
        }

        return $chips;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, int>
     */
    private function rankCategoryIds(Collection $transactions): Collection
    {
        return $transactions
            ->groupBy('category_id')
            ->map(function (Collection $group, $categoryId) {
                return [
                    'category_id' => (int) $categoryId,
                    'count' => $group->count(),
                    'last_date' => $group->max(fn (Transaction $t) => $t->date?->format('Y-m-d') ?? ''),
                ];
            })
            ->sortBy([
                ['count', 'desc'],
                ['last_date', 'desc'],
            ])
            ->take(self::MAX_CHIPS)
            ->pluck('category_id')
            ->values();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function resolveAccountIdForCategory(
        Collection $transactions,
        int $categoryId,
        ?int $fallbackAccountId,
    ): ?int {
        $categoryTransactions = $transactions->where('category_id', $categoryId);

        $accountCounts = $categoryTransactions
            ->countBy('account_id')
            ->sortDesc();

        $topAccountId = $accountCounts->keys()->first();

        if ($topAccountId !== null) {
            return (int) $topAccountId;
        }

        return $fallbackAccountId;
    }

    private function resolveFallbackAccountId(User $user, int $householdId): ?int
    {
        return Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->value('id');
    }
}
