import React, { useState } from 'react';
import clsx from 'clsx';
import { Link, router, useForm } from '@inertiajs/react';
import CardBox from '@/Components/CardBox';
import DashboardWidgetShell from '@/Components/Dashboard/DashboardWidgetShell';
import { formatCurrency } from '@/utils/format';
import { moneyTabular } from '@/utils/moneyGridClasses';

export interface ExpenseDistributionBucket {
    amount: number;
    percentage: number;
    threshold: number;
    exceeded: boolean;
    categories: Array<{
        id: number | null;
        name: string;
        icon: string;
        color: string;
        amount: number;
        percentage: number;
    }>;
}

export interface ExpenseDistributionData {
    needs: ExpenseDistributionBucket;
    wants: ExpenseDistributionBucket;
    investments: ExpenseDistributionBucket;
    unclassified: {
        amount: number;
        percentage: number;
        categories: Array<{
            id: number | null;
            name: string;
            icon: string;
            color: string;
            amount: number;
            percentage: number;
        }>;
    };
    total_expenses: number;
    thresholds: { needs: number; wants: number; investments: number };
    has_custom_thresholds: boolean;
    current_month: string;
}

interface Props {
    data: ExpenseDistributionData;
    className?: string;
    /** Dashboard: shell condiviso con gli altri widget. */
    embedded?: boolean;
}

const BUCKETS: Array<{
    key: 'needs' | 'wants' | 'investments';
    label: string;
    icon: string;
    color: string;
    bgColor: string;
    darkBgColor: string;
    barColor: string;
}> = [
    {
        key: 'needs',
        label: 'Necessità',
        icon: '🏠',
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-900/20',
        darkBgColor: 'dark:bg-blue-900/20',
        barColor: '#3b82f6',
    },
    {
        key: 'wants',
        label: 'Extra',
        icon: '🎯',
        color: 'text-violet-600 dark:text-violet-400',
        bgColor: 'bg-violet-50 dark:bg-violet-900/20',
        darkBgColor: 'dark:bg-violet-900/20',
        barColor: '#8b5cf6',
    },
    {
        key: 'investments',
        label: 'Investimenti',
        icon: '📈',
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-900/20',
        darkBgColor: 'dark:bg-emerald-900/20',
        barColor: '#10b981',
    },
];

function ThresholdSettingsButton({
    hasCustomThresholds,
    isOpen,
    onToggle,
}: {
    hasCustomThresholds: boolean;
    isOpen: boolean;
    onToggle: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onToggle}
            className={clsx(
                'flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors',
                isOpen
                    ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                    : 'text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300',
            )}
            title="Personalizza soglie"
            aria-label="Personalizza soglie di distribuzione spese"
            aria-expanded={isOpen}
        >
            <span aria-hidden>⚙️</span>
            <span className="hidden sm:inline">
                {hasCustomThresholds ? 'Soglie personalizzate' : 'Soglie'}
            </span>
        </button>
    );
}

function ThresholdForm({
    thresholds,
    onClose,
}: {
    thresholds: { needs: number; wants: number; investments: number };
    onClose: () => void;
}) {
    const { data, setData, put, delete: destroy, processing } = useForm({
        needs: thresholds.needs.toString(),
        wants: thresholds.wants.toString(),
        investments: thresholds.investments.toString(),
    });

    const total = Number(data.needs) + Number(data.wants) + Number(data.investments);
    const totalExceeds = total > 100;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (totalExceeds) return;
        put(route('expense-distribution.thresholds.update'), {
            onSuccess: () => onClose(),
        });
    }

    function handleReset() {
        destroy(route('expense-distribution.thresholds.reset'), {
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
            <p className="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-300">
                Imposta le tue soglie personalizzate
            </p>
            <p className="mb-3 text-xs text-gray-500 dark:text-gray-400">
                Non è un obbligo — sono solo riferimenti per capire se una voce sta crescendo.
            </p>
            <form onSubmit={handleSubmit} className="space-y-3">
                {BUCKETS.map((b) => (
                    <div key={b.key} className="flex items-center gap-3">
                        <span className="w-28 text-sm text-gray-600 dark:text-gray-400">
                            {b.icon} {b.label}
                        </span>
                        <div className="flex items-center gap-1">
                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                value={data[b.key]}
                                onChange={(e) => setData(b.key, e.target.value)}
                                className="w-16 rounded-lg border border-gray-300 bg-white px-2 py-1 text-center text-sm text-gray-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            />
                            <span className="text-sm text-gray-500">%</span>
                        </div>
                    </div>
                ))}
                <div className={clsx(
                    'flex items-center justify-between rounded-lg px-2 py-1 text-xs font-medium',
                    totalExceeds
                        ? 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'
                        : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                )}>
                    <span>Totale</span>
                    <span>{total}%{totalExceeds && ' — supera il 100%'}</span>
                </div>
                <div className="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="submit"
                        disabled={processing || totalExceeds}
                        className="rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-600 disabled:opacity-60"
                    >
                        Salva
                    </button>
                    <button
                        type="button"
                        onClick={handleReset}
                        disabled={processing}
                        className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Ripristina default
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    >
                        Annulla
                    </button>
                </div>
            </form>
        </div>
    );
}

/**
 * Widget Distribuzione Spese per la Dashboard.
 *
 * Mostra la ripartizione delle spese mensili in tre macro-voci
 * (Necessità, Extra, Investimenti) rispetto alle soglie opzionali dell'utente.
 * Il widget è puramente informativo: le soglie non sono obblighi.
 * Le categorie non classificate vengono segnalate per guidare l'utente.
 */
export default function ExpenseDistributionWidget({ data, className, embedded = false }: Props) {
    const [showThresholdForm, setShowThresholdForm] = useState(false);

    const hasData = data.total_expenses > 0;
    const anyExceeded = BUCKETS.some((b) => data[b.key].exceeded);
    const subtitle = anyExceeded
        ? `${data.current_month} · Soglia superata`
        : data.current_month;

    const settingsButton = (
        <ThresholdSettingsButton
            hasCustomThresholds={data.has_custom_thresholds}
            isOpen={showThresholdForm}
            onToggle={() => setShowThresholdForm((value) => !value)}
        />
    );

    const body = (
        <>
            {!embedded && (
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h3 className="font-semibold text-gray-900 dark:text-white">Distribuzione spese</h3>
                        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{subtitle}</p>
                    </div>
                    {settingsButton}
                </div>
            )}

            {!hasData ? (
                <div className="flex flex-col items-center justify-center py-8 text-center">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Nessuna spesa registrata questo mese.
                    </p>
                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Aggiungi transazioni per vedere la distribuzione.
                    </p>
                </div>
            ) : (
                <>
                    {/* Barra riassuntiva colorata */}
                    <div className="mb-4 flex h-3 overflow-hidden rounded-full">
                        {BUCKETS.map((b) => {
                            const pct = data[b.key].percentage;
                            if (pct <= 0) return null;
                            return (
                                <div
                                    key={b.key}
                                    style={{ width: `${pct}%`, backgroundColor: b.barColor }}
                                    title={`${b.label}: ${pct.toFixed(1)}%`}
                                />
                            );
                        })}
                        {data.unclassified.percentage > 0 && (
                            <div
                                style={{ width: `${data.unclassified.percentage}%`, backgroundColor: '#94a3b8' }}
                                title={`Non classificate: ${data.unclassified.percentage.toFixed(1)}%`}
                            />
                        )}
                    </div>

                    {/* Voci principali */}
                    <div className="space-y-3">
                        {BUCKETS.map((b) => {
                            const bucket = data[b.key];
                            const threshold = bucket.threshold;
                            const pct = bucket.percentage;
                            const exceeded = bucket.exceeded;

                            return (
                                <div key={b.key}>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="flex items-center gap-1.5 font-medium text-gray-700 dark:text-gray-300">
                                            <span>{b.icon}</span>
                                            <span>{b.label}</span>
                                            {exceeded && (
                                                <>
                                                    <span
                                                        className="inline-block h-2 w-2 shrink-0 rounded-full bg-amber-500 sm:hidden"
                                                        title={`Supera la soglia del ${threshold}%`}
                                                        aria-label={`Attenzione: supera la soglia del ${threshold}%`}
                                                    />
                                                    <span
                                                        className="hidden rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-700 sm:inline dark:bg-amber-900/40 dark:text-amber-400"
                                                        title={`Supera la soglia del ${threshold}%`}
                                                    >
                                                        Attenzione
                                                    </span>
                                                </>
                                            )}
                                        </span>
                                        <div className="flex items-center gap-2 text-right">
                                            <span className={clsx('font-semibold', b.color)}>
                                                {pct.toFixed(1)}%
                                            </span>
                                            <span className={clsx('text-xs text-gray-400 dark:text-gray-500', moneyTabular)}>
                                                {formatCurrency(bucket.amount)}
                                            </span>
                                        </div>
                                    </div>
                                    {/* Barra progresso con soglia */}
                                    <div className="relative mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                        <div
                                            className="h-full rounded-full transition-all duration-500"
                                            style={{
                                                width: `${Math.min(pct, 100)}%`,
                                                backgroundColor: exceeded ? '#f59e0b' : b.barColor,
                                            }}
                                        />
                                        {/* Marker soglia */}
                                        {threshold > 0 && threshold <= 100 && (
                                            <div
                                                className="absolute top-0 h-full w-0.5 bg-gray-400/60 dark:bg-gray-500/60"
                                                style={{ left: `${threshold}%` }}
                                                title={`Soglia: ${threshold}%`}
                                            />
                                        )}
                                    </div>
                                    {threshold > 0 && (
                                        <p className="mt-0.5 text-right text-xs text-gray-400 dark:text-gray-500">
                                            Soglia: {threshold}%
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {/* Spese non classificate */}
                    {data.unclassified.amount > 0 && (
                        <div className="mt-4 rounded-lg border border-dashed border-gray-200 p-3 dark:border-gray-700">
                            <p className="flex items-center justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">
                                    ❓ Non classificate
                                </span>
                                <span className={clsx('font-medium text-gray-600 dark:text-gray-400', moneyTabular)}>
                                    {data.unclassified.percentage.toFixed(1)}% · {formatCurrency(data.unclassified.amount)}
                                </span>
                            </p>
                            {data.unclassified.categories.length > 0 && (
                                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Classifica le categorie per una visione completa.{' '}
                                    <Link
                                        href={route('categories.index')}
                                        className="text-emerald-500 underline hover:text-emerald-600"
                                    >
                                        Gestisci categorie
                                    </Link>
                                </p>
                            )}
                        </div>
                    )}
                </>
            )}

            {/* Form soglie personalizzate */}
            {showThresholdForm && (
                <ThresholdForm
                    thresholds={data.thresholds}
                    onClose={() => setShowThresholdForm(false)}
                />
            )}

            <p className="mt-4 text-xs text-gray-400 dark:text-gray-500">
                Le soglie sono indicative — utili per capire se una voce cresce troppo.
            </p>
        </>
    );

    if (embedded) {
        return (
            <DashboardWidgetShell
                title="Distribuzione spese"
                subtitle={subtitle}
                detailHref={route('categories.index')}
                detailLabel="Categorie"
                headerActions={settingsButton}
                className={className}
            >
                {body}
            </DashboardWidgetShell>
        );
    }

    return (
        <CardBox className={clsx('h-full', className)}>
            {body}
        </CardBox>
    );
}
