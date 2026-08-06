<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\DashboardLayout;
use App\Models\DebtCredit;
use App\Models\FinancialGoal;
use App\Models\Investment;
use App\Models\User;
use Carbon\Carbon;

class DashboardDataVersionService
{
    public function __construct(
        private readonly FormulaWidgetDataVersionService $formulaWidgetDataVersionService,
    ) {}

    /**
     * Fingerprint dei dati che influenzano il payload Inertia della dashboard.
     * Cambia automaticamente quando transazioni, conti, budget, investimenti,
     * layout o profilo utente vengono aggiornati; include anche il giorno corrente
     * per le statistiche rolling (ultimi 30 giorni).
     */
    public function resolveForUser(User $user): string
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return '0';
        }

        $accountStats = Account::query()
            ->where('household_id', $householdId)
            ->selectRaw('COUNT(id) as row_count')
            ->selectRaw('MAX(updated_at) as max_updated_at')
            ->first();

        $budgetStats = Budget::query()
            ->where('household_id', $householdId)
            ->selectRaw('COUNT(id) as row_count')
            ->selectRaw('MAX(updated_at) as max_updated_at')
            ->first();

        $debtStats = DebtCredit::query()
            ->where('household_id', $householdId)
            ->selectRaw('COUNT(id) as row_count')
            ->selectRaw('MAX(updated_at) as max_updated_at')
            ->first();

        $goalStats = FinancialGoal::query()
            ->where('household_id', $householdId)
            ->selectRaw('COUNT(id) as row_count')
            ->selectRaw('MAX(updated_at) as max_updated_at')
            ->first();

        $investmentStats = Investment::query()
            ->where('household_id', $householdId)
            ->selectRaw('COUNT(id) as row_count')
            ->selectRaw('MAX(updated_at) as max_updated_at')
            ->first();

        $layoutUpdatedAt = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->value('updated_at');

        $parts = [
            $this->formulaWidgetDataVersionService->resolveForUser($user),
            (string) $householdId,
            (string) ($accountStats->row_count ?? 0),
            $this->timestampFromValue($accountStats->max_updated_at ?? null),
            (string) ($budgetStats->row_count ?? 0),
            $this->timestampFromValue($budgetStats->max_updated_at ?? null),
            (string) ($debtStats->row_count ?? 0),
            $this->timestampFromValue($debtStats->max_updated_at ?? null),
            (string) ($goalStats->row_count ?? 0),
            $this->timestampFromValue($goalStats->max_updated_at ?? null),
            (string) ($investmentStats->row_count ?? 0),
            $this->timestampFromValue($investmentStats->max_updated_at ?? null),
            $this->timestampFromValue($layoutUpdatedAt),
            $this->timestampFromValue($user->updated_at),
            Carbon::now()->format('Y-m-d'),
        ];

        return substr(hash('xxh128', implode('|', $parts)), 0, 20);
    }

    private function timestampFromValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if ($value instanceof Carbon) {
            return (string) $value->timestamp;
        }

        return (string) Carbon::parse((string) $value)->timestamp;
    }
}
