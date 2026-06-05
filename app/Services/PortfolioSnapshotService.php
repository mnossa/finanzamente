<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Investment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PortfolioSnapshotService
{
    /**
     * @return array{
     *     positions: array<int, array<string, mixed>>,
     *     allocation: array<int, array<string, mixed>>,
     *     totalValue: float,
     *     liquidValue: float,
     *     investedValue: float,
     *     riskIndex: float,
     *     riskLabel: string,
     *     accounts: array<int, array<string, mixed>>,
     *     classColors: array<string, string>,
     *     classLabels: array<string, string>
     * }
     */
    public function build(User $user): array
    {
        $householdId = $user->active_household_id;

        $investments = Investment::with(['asset.currency:code,symbol', 'account:id,name'])
            ->where('household_id', $householdId)
            ->whereNull('sell_date')
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)->orWhere('user_id', $user->id);
            })
            ->get();

        $positions = [];
        $investedValue = 0.0;

        foreach ($investments as $inv) {
            $assetType = $inv->asset->type ?? 'other';
            $assetClass = AssetClassificationService::ASSET_TYPE_CLASS[$assetType] ?? 'other';
            $risk = AssetClassificationService::ASSET_TYPE_RISK[$assetType] ?? 3;
            $value = $inv->total_buy_value;
            $investedValue += $value;

            $positions[] = [
                'id' => $inv->id,
                'type' => 'investment',
                'name' => $inv->asset->name,
                'symbol' => $inv->asset->symbol,
                'asset_type' => $assetType,
                'asset_type_label' => $inv->asset->type_label,
                'asset_class' => $assetClass,
                'asset_class_label' => AssetClassificationService::CLASS_LABELS[$assetClass] ?? $assetClass,
                'risk' => $risk,
                'value' => $value,
                'quantity' => (float) $inv->quantity,
                'buy_price' => (float) $inv->buy_price,
                'buy_date' => $inv->buy_date->format('Y-m-d'),
                'account' => $inv->account ? ['id' => $inv->account->id, 'name' => $inv->account->name] : null,
                'currency' => [
                    'code' => $inv->asset->currency->code ?? $inv->asset->currency_code,
                    'symbol' => $inv->asset->currency->symbol ?? '€',
                ],
                'notes' => $inv->notes,
            ];
        }

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->whereNotIn('type', ['broker'])
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get();

        $accountRows = [];
        $liquidValue = 0.0;

        if ($accounts->isNotEmpty()) {
            $transactionSums = Transaction::whereIn('account_id', $accounts->pluck('id'))
                ->where(function ($q) use ($user) {
                    $q->where('is_private', false)->orWhere('user_id', $user->id);
                })
                ->groupBy('account_id')
                ->pluck(DB::raw('SUM(amount)'), 'account_id');

            foreach ($accounts as $account) {
                $balance = (float) $account->initial_balance + (float) ($transactionSums[$account->id] ?? 0);
                if ($balance <= 0) {
                    continue;
                }

                $liquidValue += $balance;
                $accountType = $account->type ?? 'other';
                $assetClass = AssetClassificationService::ACCOUNT_TYPE_CLASS[$accountType] ?? 'liquidity';
                $risk = AssetClassificationService::ACCOUNT_TYPE_RISK[$accountType] ?? 1;

                $accountRows[] = [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $accountType,
                    'type_label' => Account::TYPES[$accountType] ?? $accountType,
                    'balance' => round($balance, 2),
                    'currency_code' => $account->currency_code,
                ];

                $positions[] = [
                    'id' => 'account_'.$account->id,
                    'type' => 'account',
                    'name' => $account->name,
                    'symbol' => null,
                    'asset_type' => $accountType,
                    'asset_type_label' => Account::TYPES[$accountType] ?? $accountType,
                    'asset_class' => $assetClass,
                    'asset_class_label' => AssetClassificationService::CLASS_LABELS[$assetClass] ?? $assetClass,
                    'risk' => $risk,
                    'value' => $balance,
                    'quantity' => null,
                    'buy_price' => null,
                    'buy_date' => null,
                    'account' => ['id' => $account->id, 'name' => $account->name],
                    'currency' => ['code' => $account->currency_code, 'symbol' => '€'],
                    'notes' => null,
                ];
            }
        }

        $totalValue = $liquidValue + $investedValue;

        foreach ($accountRows as &$accountRow) {
            $accountRow['portfolio_percentage'] = $totalValue > 0
                ? round(($accountRow['balance'] / $totalValue) * 100, 2)
                : 0;
        }
        unset($accountRow);

        $classMap = [];
        foreach ($positions as $pos) {
            $cls = $pos['asset_class'];
            if (! isset($classMap[$cls])) {
                $classMap[$cls] = [
                    'asset_class' => $cls,
                    'label' => AssetClassificationService::CLASS_LABELS[$cls] ?? $cls,
                    'color' => AssetClassificationService::CLASS_COLORS[$cls] ?? '#94a3b8',
                    'value' => 0.0,
                    'risk_weight' => 0.0,
                ];
            }
            $classMap[$cls]['value'] += $pos['value'];
            $classMap[$cls]['risk_weight'] += $pos['value'] * $pos['risk'];
        }

        $allocation = [];
        foreach ($classMap as $cls => $data) {
            $allocation[] = [
                'asset_class' => $cls,
                'label' => $data['label'],
                'color' => $data['color'],
                'value' => round($data['value'], 2),
                'percentage' => $totalValue > 0 ? round(($data['value'] / $totalValue) * 100, 1) : 0,
                'risk' => $data['value'] > 0 ? round($data['risk_weight'] / $data['value'], 1) : 0,
            ];
        }

        usort($allocation, fn ($a, $b) => $b['value'] <=> $a['value']);

        $riskNumerator = 0.0;
        foreach ($positions as $pos) {
            $riskNumerator += $pos['value'] * $pos['risk'];
        }
        $riskIndex = $totalValue > 0
            ? min(7, max(1, round($riskNumerator / $totalValue, 1)))
            : 1;

        foreach ($positions as &$pos) {
            $pos['portfolio_percentage'] = $totalValue > 0
                ? round(($pos['value'] / $totalValue) * 100, 2)
                : 0;
        }
        unset($pos);

        return [
            'positions' => array_values($positions),
            'allocation' => $allocation,
            'totalValue' => round($totalValue, 2),
            'liquidValue' => round($liquidValue, 2),
            'investedValue' => round($investedValue, 2),
            'riskIndex' => (float) $riskIndex,
            'riskLabel' => AssetClassificationService::getRiskLabel($riskIndex),
            'accounts' => array_values($accountRows),
            'classColors' => AssetClassificationService::CLASS_COLORS,
            'classLabels' => AssetClassificationService::CLASS_LABELS,
        ];
    }
}
