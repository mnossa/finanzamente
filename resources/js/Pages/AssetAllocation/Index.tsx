import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InvestmentHubNav from '@/Components/InvestmentHubNav';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import EmptyState from '@/Components/EmptyState';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import {
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
    Tooltip,
} from 'recharts';
import { getChartTooltipStyle, useChartDarkMode } from '@/Components/Charts/chartConfig';
import React, { useState } from 'react';
import { moneySummaryGrid2, moneyTabular } from '@/utils/moneyGridClasses';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface AllocationEntry {
    asset_class: string;
    label: string;
    color: string;
    value: number;
    percentage: number;
    risk?: number;
}

interface Position {
    id: string | number;
    type: 'investment' | 'account';
    name: string;
    symbol: string | null;
    asset_type: string;
    asset_type_label: string;
    asset_class: string;
    asset_class_label: string;
    risk: number;
    value: number;
    quantity: number | null;
    buy_price: number | null;
    buy_date: string | null;
    account: { id: number; name: string } | null;
    currency: { code: string; symbol: string };
    notes: string | null;
    portfolio_percentage: number;
}

interface Props {
    positions: Position[];
    allocation: AllocationEntry[];
    totalValue: number;
    riskIndex: number;
    riskLabel: string;
    classColors: Record<string, string>;
    classLabels: Record<string, string>;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getRiskColor(index: number): string {
    if (index <= 2) return 'text-emerald-600 dark:text-emerald-400';
    if (index <= 4) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}

function getRiskBgColor(index: number): string {
    if (index <= 2) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';
    if (index <= 4) return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
    return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
}

// ---------------------------------------------------------------------------
// Donut Tooltip
// ---------------------------------------------------------------------------

interface TooltipPayload {
    name: string;
    value: number;
    payload: AllocationEntry;
}

function AllocationTooltip({ active, payload }: { active?: boolean; payload?: TooltipPayload[] }) {
    const isDark = useChartDarkMode();
    if (!active || !payload?.length) return null;
    const d = payload[0];
    return (
        <div style={getChartTooltipStyle(isDark)}>
            <p className="font-semibold">{d.name}</p>
            <p className={clsx('text-blue-500 font-bold', moneyTabular)}>{formatCurrency(d.value)}</p>
            <p className="text-gray-500 dark:text-gray-400 text-xs">
                {d.payload.percentage.toFixed(1)}% del patrimonio
            </p>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Risk Gauge (simple visual bar)
// ---------------------------------------------------------------------------

function RiskGauge({ index, label }: { index: number; label: string }) {
    const pct = ((index - 1) / 6) * 100;
    return (
        <div>
            <div className="flex items-center justify-between gap-2 mb-1">
                <span className="text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                    <span className="sm:hidden">1</span>
                    <span className="hidden sm:inline">1 — Molto Basso</span>
                </span>
                <span className="text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                    <span className="sm:hidden">7</span>
                    <span className="hidden sm:inline">7 — Molto Alto</span>
                </span>
            </div>
            <div className="relative h-3 rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    className="h-3 rounded-full transition-all duration-700"
                    style={{
                        width: `${pct}%`,
                        background: 'linear-gradient(to right, #10b981, #f59e0b, #ef4444)',
                    }}
                />
                <div
                    className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 h-5 w-5 rounded-full border-2 border-white dark:border-gray-800 bg-gray-800 dark:bg-white shadow"
                    style={{ left: `${pct}%` }}
                />
            </div>
            <p className={clsx('mt-2 text-center text-sm font-semibold', moneyTabular, getRiskColor(index))}>
                {label} — {index.toFixed(1)}/7
            </p>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Position Detail Modal
// ---------------------------------------------------------------------------

function PositionModal({ position, onClose }: { position: Position; onClose: () => void }) {

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Dettaglio posizione"
            onClick={e => { if (e.target === e.currentTarget) onClose(); }}
        >
            <div className="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 shadow-xl p-6 space-y-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                            {position.name}
                            {position.symbol && (
                                <span className="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                                    ({position.symbol})
                                </span>
                            )}
                        </h2>
                        <div className="flex flex-wrap gap-2 mt-1">
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                {position.asset_class_label}
                            </span>
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {position.asset_type_label}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                        aria-label="Chiudi"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="rounded-xl bg-gray-50 dark:bg-gray-800 p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Controvalore</p>
                        <p className={clsx('text-lg font-bold text-gray-900 dark:text-white', moneyTabular)}>
                            {formatCurrency(position.value)}
                        </p>
                    </div>
                    <div className="rounded-xl bg-gray-50 dark:bg-gray-800 p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Peso portafoglio</p>
                        <p className={clsx('text-lg font-bold text-gray-900 dark:text-white', moneyTabular)}>
                            {position.portfolio_percentage.toFixed(2)}%
                        </p>
                    </div>
                    {position.buy_date && (
                        <div className="rounded-xl bg-gray-50 dark:bg-gray-800 p-3">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Data acquisto</p>
                            <p className="text-sm font-semibold text-gray-900 dark:text-white">
                                {formatDate(position.buy_date)}
                            </p>
                        </div>
                    )}
                    {position.quantity != null && (
                        <div className="rounded-xl bg-gray-50 dark:bg-gray-800 p-3">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Quantità</p>
                            <p className="text-sm font-semibold text-gray-900 dark:text-white">
                                {position.quantity}
                            </p>
                        </div>
                    )}
                    {position.buy_price != null && (
                        <div className="rounded-xl bg-gray-50 dark:bg-gray-800 p-3">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Prezzo di carico</p>
                            <p className={clsx('text-sm font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                {formatCurrency(position.buy_price)} {position.currency.code}
                            </p>
                        </div>
                    )}
                    <div className="rounded-xl bg-gray-50 dark:bg-gray-800 p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Indice rischio</p>
                        <p className={clsx('text-sm font-semibold', getRiskColor(position.risk))}>
                            {position.risk}/7
                        </p>
                    </div>
                </div>

                {position.account && position.type === 'investment' && (
                    <div className="text-sm text-gray-500 dark:text-gray-400">
                        Conto: <span className="font-medium text-gray-700 dark:text-gray-300">{position.account.name}</span>
                    </div>
                )}

                {position.notes && (
                    <div className="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3 text-sm text-amber-800 dark:text-amber-200">
                        📝 {position.notes}
                    </div>
                )}

                {position.type === 'investment' && (
                    <div className="pt-2">
                        <Link
                            href={route('investments.index')}
                            className="text-sm text-emerald-600 dark:text-emerald-400 hover:underline"
                        >
                            Vedi tutti gli investimenti →
                        </Link>
                    </div>
                )}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Mini Allocation Bar (used also in the dashboard widget)
// ---------------------------------------------------------------------------

export function MiniAllocationBar({ allocation, className }: { allocation: AllocationEntry[]; className?: string }) {
    const total = allocation.reduce((s, a) => s + a.value, 0);
    if (total === 0 || !allocation.length) return null;

    return (
        <div className={clsx('flex h-2 rounded-full overflow-hidden gap-px', className)}>
            {allocation.map((a) => (
                <div
                    key={a.asset_class}
                    style={{ width: `${a.percentage}%`, backgroundColor: a.color }}
                    title={`${a.label}: ${a.percentage.toFixed(1)}%`}
                />
            ))}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function AssetAllocationIndex({
    positions,
    allocation,
    totalValue,
    riskIndex,
    riskLabel,
}: Props) {
    const [selectedPosition, setSelectedPosition] = useState<Position | null>(null);

    const isEmpty = positions.length === 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Asset Allocation"
                    mobileTitle="Allocazione"
                    subtitle="Panoramica del tuo patrimonio per asset class"
                    hideSubtitleOnMobile
                    backLink={route('investments.index')}
                />
            }
        >
            <Head title="Asset Allocation" />

            {selectedPosition && (
                <PositionModal position={selectedPosition} onClose={() => setSelectedPosition(null)} />
            )}

            <PageContent>
                <InvestmentHubNav active="allocation" />

                {isEmpty ? (
                    <CardBox>
                        <EmptyState
                            icon="📊"
                            title="Nessuna posizione trovata"
                            description="Aggiungi investimenti o verifica i tuoi conti per visualizzare l'allocazione del patrimonio."
                            showCreateButton={false}
                        >
                            <Link
                                href={route('investments.create')}
                                className="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors"
                            >
                                + Nuovo Investimento
                            </Link>
                        </EmptyState>
                    </CardBox>
                ) : (
                    <>
                        {/* Totale + Rischio */}
                        <div className={moneySummaryGrid2}>
                            <CardBox className="min-w-0 p-4 shadow-sm sm:p-5">
                                <p className="text-sm text-gray-500 dark:text-gray-400">Base allocazione</p>
                                <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl', moneyTabular)}>
                                    {formatCurrency(totalValue)}
                                </p>
                                <p className="mt-2 text-xs leading-relaxed text-gray-400">
                                    Conti + investimenti inclusi nel calcolo · {positions.length} posizioni
                                </p>
                                <MiniAllocationBar allocation={allocation} className="mt-3" />
                            </CardBox>

                            <CardBox className="min-w-0 p-4 shadow-sm sm:p-5">
                                <p className="mb-3 text-sm text-gray-500 dark:text-gray-400">Indice di Rischio</p>
                                <RiskGauge index={riskIndex} label={riskLabel} />
                                <p className="mt-3 text-center text-xs text-gray-400 dark:text-gray-500">
                                    Scala KIID 1–7 · Media ponderata per valore
                                </p>
                            </CardBox>
                        </div>

                        {/* Donut Chart + Allocation List */}
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                            <CardBox className="min-w-0 p-4 shadow-sm sm:p-5">
                                <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                                    Breakdown per Asset Class
                                </h2>
                                <div className="mx-auto h-48 w-full max-w-xs sm:h-56 sm:max-w-none">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart margin={{ top: 4, right: 4, bottom: 4, left: 4 }}>
                                            <Pie
                                                data={allocation}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius="52%"
                                                outerRadius="78%"
                                                dataKey="value"
                                                nameKey="label"
                                                paddingAngle={2}
                                            >
                                                {allocation.map((entry) => (
                                                    <Cell
                                                        key={entry.asset_class}
                                                        fill={entry.color}
                                                    />
                                                ))}
                                            </Pie>
                                            <Tooltip
                                                content={<AllocationTooltip />}
                                                wrapperStyle={{ zIndex: 1000, outline: 'none' }}
                                            />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>

                                <div className="mt-4 space-y-2">
                                    {allocation.map((entry) => (
                                        <div key={entry.asset_class} className="flex min-w-0 items-center gap-2 sm:gap-3">
                                            <span
                                                className="h-3 w-3 shrink-0 rounded-full"
                                                style={{ backgroundColor: entry.color }}
                                            />
                                            <span className="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300">
                                                {entry.label}
                                            </span>
                                            <span className={clsx('shrink-0 text-sm font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                                {formatCurrency(entry.value)}
                                            </span>
                                            <span className={clsx('w-10 shrink-0 text-right text-xs text-gray-400 sm:w-12', moneyTabular)}>
                                                {entry.percentage.toFixed(1)}%
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </CardBox>

                            {/* Positions List */}
                            <CardBox className="min-w-0 overflow-hidden shadow-sm">
                                <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700 sm:p-5">
                                    <h2 className="text-base font-semibold text-gray-900 dark:text-white">
                                        Posizioni Aperte
                                    </h2>
                                    <span className="text-xs text-gray-400">{positions.length} voci</span>
                                </div>
                                <div className="divide-y divide-gray-100 dark:divide-gray-700 overflow-auto max-h-96">
                                    {positions.map((pos) => (
                                        <button
                                            key={pos.id}
                                            type="button"
                                            onClick={() => setSelectedPosition(pos)}
                                            className="flex w-full items-center gap-2 px-4 py-3 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/60 sm:gap-3 sm:px-5"
                                        >
                                            <span
                                                className="h-2.5 w-2.5 flex-shrink-0 rounded-full"
                                                style={{ backgroundColor: pos.type === 'investment' ? '#3b82f6' : '#06b6d4' }}
                                            />
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                    {pos.name}
                                                    {pos.symbol && (
                                                        <span className="ml-1 text-xs text-gray-400">({pos.symbol})</span>
                                                    )}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {pos.asset_class_label}
                                                    {pos.buy_date && ` · acquisto ${formatDate(pos.buy_date)}`}
                                                </p>
                                            </div>
                                            <div className="text-right flex-shrink-0">
                                                <p className={clsx('text-sm font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                                    {formatCurrency(pos.value)}
                                                </p>
                                                <p className={clsx('text-xs text-gray-400', moneyTabular)}>
                                                    {pos.portfolio_percentage.toFixed(1)}%
                                                </p>
                                            </div>
                                            <span className={clsx('flex-shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium', getRiskBgColor(pos.risk))}>
                                                R{pos.risk}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            </CardBox>
                        </div>
                    </>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
