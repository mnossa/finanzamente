<?php

namespace App\Services;

use App\Models\FormulaWidget;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class FormulaWidgetDataVersionService
{
    /**
     * Fingerprint dei dati che influenzano i payload dei widget a formula
     * (transazioni del nucleo + configurazione widget dell'utente).
     */
    public function resolveForUser(User $user): string
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return '0';
        }

        $transactionStats = Transaction::query()
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('accounts.household_id', $householdId)
            ->selectRaw('COUNT(transactions.id) as transaction_count')
            ->selectRaw('MAX(transactions.updated_at) as max_transaction_updated_at')
            ->first();

        $widgetStats = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(id) as widget_count')
            ->selectRaw('MAX(updated_at) as max_widget_updated_at')
            ->first();

        $parts = [
            (string) $householdId,
            (string) ($transactionStats->transaction_count ?? 0),
            (string) $this->timestampFromDate($transactionStats->max_transaction_updated_at ?? null),
            (string) ($widgetStats->widget_count ?? 0),
            (string) $this->timestampFromDate($widgetStats->max_widget_updated_at ?? null),
        ];

        return substr(hash('xxh128', implode('|', $parts)), 0, 16);
    }

    private function timestampFromDate(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if ($value instanceof Carbon) {
            return $value->timestamp;
        }

        return Carbon::parse((string) $value)->timestamp;
    }
}
