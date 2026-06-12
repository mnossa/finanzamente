<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PortfolioSnapshotService
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly InvestmentLedgerService $investmentLedgerService,
    ) {}

    /**
     * @return array{
     *     positions: array<int, array<string, mixed>>,
     *     allocation: array<int, array<string, mixed>>,
     *     totalValue: float,
     *     allocationTotalValue: float,
     *     liquidValue: float,
     *     investedValue: float,
     *     investedLinkedValue: float,
     *     investedUnlinkedValue: float,
     *     riskIndex: float,
     *     riskLabel: string,
     *     allocationRiskIndex: float,
     *     allocationRiskLabel: string,
     *     accounts: array<int, array<string, mixed>>,
     *     classColors: array<string, string>,
     *     classLabels: array<string, string>
     * }
     */
    public function build(User $user): array
    {
        $cacheKey = sprintf('portfolio_snapshot:%d:%d', $user->id, $user->active_household_id ?? 0);

        /** @var array{
         *     positions: array<int, array<string, mixed>>,
         *     allocation: array<int, array<string, mixed>>,
         *     totalValue: float,
         *     allocationTotalValue: float,
         *     liquidValue: float,
         *     investedValue: float,
         *     investedLinkedValue: float,
         *     investedUnlinkedValue: float,
         *     riskIndex: float,
         *     riskLabel: string,
         *     allocationRiskIndex: float,
         *     allocationRiskLabel: string,
         *     accounts: array<int, array<string, mixed>>,
         *     classColors: array<string, string>,
         *     classLabels: array<string, string>
         * } */
        return Cache::store('array')->remember($cacheKey, 60, fn () => $this->computeSnapshot($user));
    }

    /**
     * @return array{
     *     positions: array<int, array<string, mixed>>,
     *     allocation: array<int, array<string, mixed>>,
     *     totalValue: float,
     *     allocationTotalValue: float,
     *     liquidValue: float,
     *     investedValue: float,
     *     investedLinkedValue: float,
     *     investedUnlinkedValue: float,
     *     riskIndex: float,
     *     riskLabel: string,
     *     allocationRiskIndex: float,
     *     allocationRiskLabel: string,
     *     accounts: array<int, array<string, mixed>>,
     *     classColors: array<string, string>,
     *     classLabels: array<string, string>
     * }
     */
    private function computeSnapshot(User $user): array
    {
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get();

        /** @var array<int, float> $accountBalances */
        $accountBalances = [];
        foreach ($accounts as $account) {
            $accountBalances[$account->id] = $this->accountBalanceService->computeBalance($account, $user);
        }

        $investments = Investment::with([
            'asset.currency:code,symbol',
            'account:id,name',
            'transactions:id,investment_id',
            'investmentPac:id,status,investment_asset_id',
            'investmentPac.asset:id,name,symbol',
        ])
            ->where('household_id', $householdId)
            ->whereNull('sell_date')
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)->orWhere('user_id', $user->id);
            })
            ->get();

        $positions = [];
        $investedValue = 0.0;
        $investedLinkedValue = 0.0;
        $investedUnlinkedValue = 0.0;
        $allocationInvestedValue = 0.0;

        foreach ($investments as $inv) {
            $assetType = $inv->asset->type ?? 'other';
            $assetClass = AssetClassificationService::resolveInvestmentAssetClass($inv->asset);
            $risk = AssetClassificationService::ASSET_TYPE_RISK[$assetType] ?? 3;
            $value = $this->investmentLedgerService->totalCost($inv);
            $isLinked = $this->investmentLedgerService->isLinkedToLedger($inv);
            $includeInAllocation = $this->includesInAllocation($inv, $isLinked, $accountBalances);

            $investedValue += $value;
            if ($isLinked) {
                $investedLinkedValue += $value;
            } else {
                $investedUnlinkedValue += $value;
            }
            if ($includeInAllocation) {
                $allocationInvestedValue += $value;
            }

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
                'is_linked_to_ledger' => $isLinked,
                'include_in_allocation' => $includeInAllocation,
                'quantity' => (float) $inv->quantity,
                'buy_price' => (float) $inv->buy_price,
                'buy_date' => $inv->buy_date->format('Y-m-d'),
                'account' => $inv->account ? ['id' => $inv->account->id, 'name' => $inv->account->name] : null,
                'currency' => [
                    'code' => $inv->asset->currency->code ?? $inv->asset->currency_code,
                    'symbol' => $inv->asset->currency->symbol ?? '€',
                ],
                'notes' => $inv->notes,
                'investment_pac_id' => $inv->investment_pac_id,
                'investment_pac' => $inv->investmentPac ? [
                    'id' => $inv->investmentPac->id,
                    'status' => $inv->investmentPac->status,
                ] : null,
            ];
        }

        $accountRows = [];
        $liquidValue = $this->accountBalanceService->computeHouseholdTotal($user, $accounts);
        $allocationLiquidValue = 0.0;

        foreach ($accounts as $account) {
            $balance = $accountBalances[$account->id];
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

            if ($balance <= 0) {
                continue;
            }

            $allocationLiquidValue += $balance;

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
                'include_in_allocation' => true,
                'quantity' => null,
                'buy_price' => null,
                'buy_date' => null,
                'account' => ['id' => $account->id, 'name' => $account->name],
                'currency' => ['code' => $account->currency_code, 'symbol' => '€'],
                'notes' => null,
            ];
        }

        $totalValue = $liquidValue + $investedLinkedValue;
        $allocationTotalValue = $allocationLiquidValue + $allocationInvestedValue;

        foreach ($accountRows as &$accountRow) {
            $accountRow['portfolio_percentage'] = $totalValue > 0
                ? round(($accountRow['balance'] / $totalValue) * 100, 2)
                : 0;
        }
        unset($accountRow);

        $classMap = [];
        foreach ($positions as $pos) {
            if (($pos['type'] ?? '') === 'investment' && ! ($pos['include_in_allocation'] ?? false)) {
                continue;
            }

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
                'percentage' => $allocationTotalValue > 0 ? round(($data['value'] / $allocationTotalValue) * 100, 1) : 0,
                'risk' => $data['value'] > 0 ? round($data['risk_weight'] / $data['value'], 1) : 0,
            ];
        }

        usort($allocation, fn ($a, $b) => $b['value'] <=> $a['value']);

        $allocationRisk = $this->computeRiskIndex($positions, fn (array $pos) => ($pos['include_in_allocation'] ?? false) || (($pos['type'] ?? '') === 'account' && ($pos['value'] ?? 0) > 0));
        $patrimonioRisk = $this->computeRiskIndex($positions, fn (array $pos) => $pos['type'] === 'account' || ($pos['is_linked_to_ledger'] ?? false));

        foreach ($positions as &$pos) {
            if (($pos['type'] ?? '') === 'investment' && ! ($pos['include_in_allocation'] ?? false)) {
                $pos['portfolio_percentage'] = 0;

                continue;
            }
            if (($pos['type'] ?? '') === 'account' && ($pos['value'] ?? 0) <= 0) {
                $pos['portfolio_percentage'] = 0;

                continue;
            }
            $pos['portfolio_percentage'] = $allocationTotalValue > 0
                ? round(($pos['value'] / $allocationTotalValue) * 100, 2)
                : 0;
        }
        unset($pos);

        return [
            'positions' => array_values($positions),
            'allocation' => $allocation,
            'totalValue' => round($totalValue, 2),
            'allocationTotalValue' => round($allocationTotalValue, 2),
            'liquidValue' => round($liquidValue, 2),
            'investedValue' => round($investedValue, 2),
            'investedLinkedValue' => round($investedLinkedValue, 2),
            'investedUnlinkedValue' => round($investedUnlinkedValue, 2),
            'riskIndex' => (float) $patrimonioRisk['index'],
            'riskLabel' => $patrimonioRisk['label'],
            'allocationRiskIndex' => (float) $allocationRisk['index'],
            'allocationRiskLabel' => $allocationRisk['label'],
            'accounts' => array_values($accountRows),
            'classColors' => AssetClassificationService::CLASS_COLORS,
            'classLabels' => AssetClassificationService::CLASS_LABELS,
        ];
    }

    /**
     * Raggruppa posizioni investimento per la UI patrimonio: PAC aggregati, singole a parte.
     *
     * @param  array<int, array<string, mixed>>  $investmentPositions
     * @return array<int, array<string, mixed>>
     */
    public function groupInvestmentPositionsForDisplay(array $investmentPositions): array
    {
        $standalone = [];
        $pacBuckets = [];

        foreach ($investmentPositions as $position) {
            $pacId = $position['investment_pac_id'] ?? null;
            if ($pacId !== null) {
                $pacBuckets[$pacId][] = $position;

                continue;
            }
            $standalone[] = [
                'kind' => 'standalone',
                'key' => 'investment_'.$position['id'],
                'id' => $position['id'],
                'name' => $position['name'],
                'symbol' => $position['symbol'],
                'value' => $position['value'],
                'portfolio_percentage' => $position['portfolio_percentage'],
                'buy_date' => $position['buy_date'],
                'account' => $position['account'],
                'currency' => $position['currency'],
                'asset_class' => $position['asset_class'],
                'asset_class_label' => $position['asset_class_label'],
            ];
        }

        $groups = $standalone;

        foreach ($pacBuckets as $pacId => $movements) {
            usort($movements, fn (array $a, array $b) => strcmp((string) $a['buy_date'], (string) $b['buy_date']));
            $totalValue = array_sum(array_column($movements, 'value'));
            $totalPercentage = array_sum(array_column($movements, 'portfolio_percentage'));
            $first = $movements[0];
            $last = $movements[array_key_last($movements)];

            $groups[] = [
                'kind' => 'pac',
                'key' => 'pac_'.$pacId,
                'pac_id' => $pacId,
                'name' => $first['name'],
                'symbol' => $first['symbol'],
                'value' => round($totalValue, 2),
                'portfolio_percentage' => round($totalPercentage, 2),
                'movement_count' => count($movements),
                'buy_date_from' => $first['buy_date'],
                'buy_date_to' => $last['buy_date'],
                'account' => $first['account'],
                'currency' => $first['currency'],
                'asset_class' => $first['asset_class'],
                'asset_class_label' => $first['asset_class_label'],
                'pac_status' => $first['investment_pac']['status'] ?? 'active',
                'movements' => array_map(fn (array $movement) => [
                    'id' => $movement['id'],
                    'value' => $movement['value'],
                    'portfolio_percentage' => $movement['portfolio_percentage'],
                    'buy_date' => $movement['buy_date'],
                    'account' => $movement['account'],
                    'currency' => $movement['currency'],
                ], $movements),
            ];
        }

        usort($groups, fn (array $a, array $b) => $b['value'] <=> $a['value']);

        return array_values($groups);
    }

    /**
     * @param  array<int, float>  $accountBalances
     */
    private function includesInAllocation(Investment $investment, bool $isLinked, array $accountBalances): bool
    {
        if ($isLinked) {
            return true;
        }

        if ($investment->account_id === null) {
            return true;
        }

        return ($accountBalances[$investment->account_id] ?? 0.0) <= 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @return array{index: float, label: string}
     */
    private function computeRiskIndex(array $positions, callable $include): array
    {
        $riskNumerator = 0.0;
        $riskDenominator = 0.0;

        foreach ($positions as $pos) {
            if (! $include($pos)) {
                continue;
            }
            $riskNumerator += $pos['value'] * $pos['risk'];
            $riskDenominator += $pos['value'];
        }

        $index = $riskDenominator > 0
            ? min(7, max(1, round($riskNumerator / $riskDenominator, 1)))
            : 1;

        return [
            'index' => (float) $index,
            'label' => AssetClassificationService::getRiskLabel($index),
        ];
    }
}
