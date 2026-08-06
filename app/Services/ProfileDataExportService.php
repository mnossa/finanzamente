<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Consent;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;

class ProfileDataExportService
{
    public function buildExportPayload(User $user): array
    {
        $user->loadMissing(['households', 'activeHousehold', 'consents']);

        $householdIds = $user->households->pluck('id')->all();

        $accounts = Account::query()
            ->whereIn('household_id', $householdIds)
            ->orderBy('id')
            ->get();

        $accountIds = $accounts->pluck('id')->all();

        $transactions = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->orderByDesc('date')
            ->limit(5000)
            ->get();

        return [
            'generated_at' => now()->toIso8601String(),
            'export_version' => '1.0',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'default_currency_code' => $user->default_currency_code,
                'profile_settings' => $user->profile_settings,
                'preferences' => $user->preferences,
                'created_at' => optional($user->created_at)->toIso8601String(),
            ],
            'households' => $user->households->map(fn (Household $household) => [
                'id' => $household->id,
                'name' => $household->name,
                'financial_management_type' => $household->financial_management_type,
                'role' => $household->pivot?->role,
                'is_active' => $user->active_household_id === $household->id,
            ])->values(),
            'accounts' => $accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'household_id' => $account->household_id,
                'name' => $account->name,
                'type' => $account->type,
                'currency_code' => $account->currency_code,
                'current_balance' => $account->current_balance,
                'active' => $account->active,
            ])->values(),
            'transactions' => $transactions->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'date' => optional($transaction->date)->format('Y-m-d'),
                'amount' => $transaction->amount,
                'currency_code' => $transaction->currency_code,
                'description' => $transaction->description,
                'is_private' => $transaction->is_private,
            ])->values(),
            'consents' => $user->consents->map(fn (Consent $consent) => [
                'purpose' => $consent->purpose,
                'status' => $consent->status,
                'policy_version' => $consent->policy_version,
                'granted_at' => optional($consent->granted_at)->toIso8601String(),
                'revoked_at' => optional($consent->revoked_at)->toIso8601String(),
            ])->values(),
        ];
    }
}
