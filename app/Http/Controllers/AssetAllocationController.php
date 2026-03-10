<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Investment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AssetAllocationController
 *
 * Fornisce la vista aggregata del patrimonio dell'utente suddivisa per asset
 * class. Calcola anche l'indice di rischio sintetico (scala 1-7 stile KIID)
 * basato sulla composizione del portafoglio.
 *
 * Asset class supportate:
 *   - equities   (Azionario):     etf, stock, index
 *   - bonds      (Obbligazionario): insurance
 *   - commodities (Commodities):   commodity
 *   - crypto     (Crypto):         crypto, conti crypto
 *   - liquidity  (Liquidità):      bank, cash, card, altri conti
 */
class AssetAllocationController extends Controller
{
    /** Rischio KIID (1-7) per tipo di asset InvestmentAsset */
    private const ASSET_TYPE_RISK = [
        'etf'       => 4,
        'stock'     => 6,
        'index'     => 4,
        'commodity' => 5,
        'insurance' => 2,
        'crypto'    => 7,
        'other'     => 3,
    ];

    /** Asset class per tipo di asset InvestmentAsset */
    private const ASSET_TYPE_CLASS = [
        'etf'       => 'equities',
        'stock'     => 'equities',
        'index'     => 'equities',
        'commodity' => 'commodities',
        'insurance' => 'bonds',
        'crypto'    => 'crypto',
        'other'     => 'other',
    ];

    /** Rischio KIID per tipo di conto */
    private const ACCOUNT_TYPE_RISK = [
        'bank'   => 1,
        'cash'   => 1,
        'card'   => 1,
        'crypto' => 7,
        'other'  => 1,
    ];

    /** Asset class per tipo di conto */
    private const ACCOUNT_TYPE_CLASS = [
        'bank'   => 'liquidity',
        'cash'   => 'liquidity',
        'card'   => 'liquidity',
        'crypto' => 'crypto',
        'other'  => 'liquidity',
    ];

    /** Etichette in italiano per asset class */
    private const CLASS_LABELS = [
        'equities'    => 'Azionario',
        'bonds'       => 'Obbligazionario',
        'commodities' => 'Commodities',
        'crypto'      => 'Crypto',
        'liquidity'   => 'Liquidità',
        'other'       => 'Altro',
    ];

    /** Colori per asset class (usati nel Donut chart) */
    private const CLASS_COLORS = [
        'equities'    => '#3b82f6', // blue
        'bonds'       => '#10b981', // emerald
        'commodities' => '#f59e0b', // amber
        'crypto'      => '#8b5cf6', // violet
        'liquidity'   => '#06b6d4', // cyan
        'other'       => '#94a3b8', // slate
    ];

    /**
     * Mostra la pagina completa di Asset Allocation.
     */
    public function index(): Response
    {
        $data = $this->buildAllocationData();

        return Inertia::render('AssetAllocation/Index', $data);
    }

    /**
     * Restituisce i dati sintetici per il widget dashboard (JSON).
     */
    public function widget(): JsonResponse
    {
        $data = $this->buildAllocationData();

        return response()->json([
            'total_value'     => $data['totalValue'],
            'risk_index'      => $data['riskIndex'],
            'risk_label'      => $data['riskLabel'],
            'allocation'      => $data['allocation'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Logica di calcolo
    // -------------------------------------------------------------------------

    private function buildAllocationData(): array
    {
        $user        = Auth::user();
        $householdId = $user->active_household_id;

        // ── 1. Posizioni di investimento aperte ───────────────────────────────
        $investments = Investment::with(['asset.currency:code,symbol', 'account:id,name'])
            ->where('household_id', $householdId)
            ->whereNull('sell_date')
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)->orWhere('user_id', $user->id);
            })
            ->get();

        $positions = [];
        foreach ($investments as $inv) {
            $assetType  = $inv->asset->type ?? 'other';
            $assetClass = self::ASSET_TYPE_CLASS[$assetType] ?? 'other';
            $risk       = self::ASSET_TYPE_RISK[$assetType] ?? 3;
            $value      = $inv->total_buy_value;

            $positions[] = [
                'id'         => $inv->id,
                'type'       => 'investment',
                'name'       => $inv->asset->name,
                'symbol'     => $inv->asset->symbol,
                'asset_type' => $assetType,
                'asset_type_label' => $inv->asset->type_label,
                'asset_class' => $assetClass,
                'asset_class_label' => self::CLASS_LABELS[$assetClass] ?? $assetClass,
                'risk'       => $risk,
                'value'      => $value,
                'quantity'   => (float) $inv->quantity,
                'buy_price'  => (float) $inv->buy_price,
                'buy_date'   => $inv->buy_date->format('Y-m-d'),
                'account'    => $inv->account ? ['id' => $inv->account->id, 'name' => $inv->account->name] : null,
                'currency'   => [
                    'code'   => $inv->asset->currency->code ?? $inv->asset->currency_code,
                    'symbol' => $inv->asset->currency->symbol ?? '€',
                ],
                'notes'      => $inv->notes,
            ];
        }

        // ── 2. Liquidità dai conti (escluding broker per evitare doppi conteggi) ─
        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->whereNotIn('type', ['broker']) // il broker è già conteggiato negli investimenti
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)->orWhere('owner_user_id', $user->id);
            })
            ->get();

        foreach ($accounts as $account) {
            $transactionsSum = Transaction::where('account_id', $account->id)
                ->where(function ($q) use ($user) {
                    $q->where('is_private', false)->orWhere('user_id', $user->id);
                })
                ->sum('amount');

            $balance    = (float) $account->initial_balance + (float) $transactionsSum;
            if ($balance <= 0) {
                continue; // ignora conti con saldo zero o negativo per l'allocazione
            }

            $accountType = $account->type ?? 'other';
            $assetClass  = self::ACCOUNT_TYPE_CLASS[$accountType] ?? 'liquidity';
            $risk        = self::ACCOUNT_TYPE_RISK[$accountType] ?? 1;

            $positions[] = [
                'id'         => 'account_' . $account->id,
                'type'       => 'account',
                'name'       => $account->name,
                'symbol'     => null,
                'asset_type' => $accountType,
                'asset_type_label' => Account::TYPES[$accountType] ?? $accountType,
                'asset_class' => $assetClass,
                'asset_class_label' => self::CLASS_LABELS[$assetClass] ?? $assetClass,
                'risk'       => $risk,
                'value'      => $balance,
                'quantity'   => null,
                'buy_price'  => null,
                'buy_date'   => null,
                'account'    => ['id' => $account->id, 'name' => $account->name],
                'currency'   => ['code' => $account->currency_code, 'symbol' => '€'],
                'notes'      => null,
            ];
        }

        // ── 3. Calcolo totali e allocazione per asset class ───────────────────
        $totalValue = array_sum(array_column($positions, 'value'));

        $classMap = [];
        foreach ($positions as $pos) {
            $cls = $pos['asset_class'];
            if (!isset($classMap[$cls])) {
                $classMap[$cls] = [
                    'asset_class'  => $cls,
                    'label'        => self::CLASS_LABELS[$cls] ?? $cls,
                    'color'        => self::CLASS_COLORS[$cls] ?? '#94a3b8',
                    'value'        => 0.0,
                    'percentage'   => 0.0,
                    'risk'         => 0,
                    'risk_weight'  => 0.0,
                ];
            }
            $classMap[$cls]['value']       += $pos['value'];
            $classMap[$cls]['risk_weight'] += $pos['value'] * $pos['risk'];
        }

        $allocation = [];
        foreach ($classMap as $cls => $data) {
            $pct = $totalValue > 0 ? round(($data['value'] / $totalValue) * 100, 1) : 0;
            $classRisk = $data['value'] > 0 ? round($data['risk_weight'] / $data['value'], 1) : 0;
            $allocation[] = [
                'asset_class' => $cls,
                'label'       => $data['label'],
                'color'       => $data['color'],
                'value'       => round($data['value'], 2),
                'percentage'  => $pct,
                'risk'        => $classRisk,
            ];
        }

        // Ordina per valore decrescente
        usort($allocation, fn($a, $b) => $b['value'] <=> $a['value']);

        // ── 4. Indice di rischio complessivo (media ponderata) ─────────────────
        $riskNumerator = 0.0;
        foreach ($positions as $pos) {
            $riskNumerator += $pos['value'] * $pos['risk'];
        }
        $riskIndex = $totalValue > 0
            ? min(7, max(1, round($riskNumerator / $totalValue, 1)))
            : 1;

        $riskLabel = $this->getRiskLabel($riskIndex);

        // Aggiunge la percentuale sul patrimonio totale a ogni posizione
        foreach ($positions as &$pos) {
            $pos['portfolio_percentage'] = $totalValue > 0
                ? round(($pos['value'] / $totalValue) * 100, 2)
                : 0;
        }
        unset($pos);

        return [
            'positions'  => array_values($positions),
            'allocation' => $allocation,
            'totalValue' => round($totalValue, 2),
            'riskIndex'  => $riskIndex,
            'riskLabel'  => $riskLabel,
            'classColors' => self::CLASS_COLORS,
            'classLabels' => self::CLASS_LABELS,
        ];
    }

    /**
     * Converte l'indice numerico in etichetta testuale (stile KIID).
     */
    private function getRiskLabel(float $index): string
    {
        return match (true) {
            $index <= 1.5 => 'Molto Basso',
            $index <= 2.5 => 'Basso',
            $index <= 3.5 => 'Moderato-Basso',
            $index <= 4.5 => 'Moderato',
            $index <= 5.5 => 'Moderato-Alto',
            $index <= 6.5 => 'Alto',
            default       => 'Molto Alto',
        };
    }
}
