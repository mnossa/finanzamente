<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class InvestmentLedgerService
{
    public function totalCost(Investment $investment): float
    {
        return (float) $investment->total_buy_value + (float) ($investment->fees ?? 0);
    }

    public function isLinkedToLedger(Investment $investment): bool
    {
        if ($investment->relationLoaded('transactions')) {
            return $investment->transactions->isNotEmpty();
        }

        return $investment->transactions()->exists();
    }

    /**
     * Investimenti con conto ma senza transazione collegata (da sincronizzare).
     */
    public function countPendingSync(User $user): int
    {
        return $this->pendingSyncQuery($user)->count();
    }

    /**
     * @return Builder<Investment>
     */
    public function pendingSyncQuery(User $user): Builder
    {
        $householdId = $user->active_household_id;

        return Investment::query()
            ->where('household_id', $householdId)
            ->whereNotNull('account_id')
            ->whereDoesntHave('transactions')
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id));
    }

    /**
     * Acquisti nel periodo senza transazione collegata (PAC senza conto, ecc.).
     *
     * @return array{amount: float, items: array<int, array{name: string, amount: float}>}
     */
    public function unsyncedPurchasesInPeriod(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $investments = Investment::query()
            ->with('asset:id,name')
            ->where('household_id', $user->active_household_id)
            ->whereBetween('buy_date', [$startDate, $endDate])
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereDoesntHave('transactions')
            ->get();

        $amount = 0.0;
        $items = [];

        foreach ($investments as $investment) {
            $cost = $this->totalCost($investment);
            $amount += $cost;
            $items[] = [
                'name' => $investment->asset?->name ?? 'Investimento',
                'amount' => round($cost, 2),
            ];
        }

        return [
            'amount' => round($amount, 2),
            'items' => $items,
        ];
    }

    /**
     * Valore investito a fine mese (solo posizioni collegate al ledger).
     */
    public function linkedInvestedValueAt(User $user, Carbon $asOfDate): float
    {
        $householdId = $user->active_household_id;
        $date = $asOfDate->toDateString();

        $investments = Investment::query()
            ->where('household_id', $householdId)
            ->whereDate('buy_date', '<=', $date)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereHas('transactions')
            ->where(function ($q) use ($date) {
                $q->whereNull('sell_date')->orWhereDate('sell_date', '>', $date);
            })
            ->get();

        return round((float) $investments->sum(fn (Investment $inv) => $this->totalCost($inv)), 2);
    }
}
