import React from 'react';
import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency } from '@/utils/format';

export interface LifestyleWidgetData {
    unlocked: boolean;
    months_with_data: number;
    months_needed: number;
    lifestyle_score: number | null;
    net_income: number;
    effective_expenses: number;
    top_categories: Array<{
        category_id: number | null;
        name: string;
        icon: string | null;
        color: string | null;
        amount: number;
        percentage: number;
        excluded: boolean;
    }>;
    trend: {
        last30_score: number | null;
        prev30_score: number | null;
        delta: number | null;
        direction: 'up' | 'down' | 'stable' | 'new' | 'unknown';
    };
}

interface LifestyleWidgetProps {
    data: LifestyleWidgetData;
    className?: string;
}

function getScoreBadgeClass(score: number | null): string {
    if (score === null) {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
    }
    if (score >= 30) {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
    }
    if (score >= 10) {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
    }

    return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200';
}

function getScoreLabel(score: number | null): string {
    if (score === null) return 'Dati insufficienti';
    if (score >= 30) return 'Ottimo';
    if (score >= 10) return 'Attenzione';
    return 'Critico';
}

/**
 * Widget Lifestyle Inflation Score per la Dashboard.
 *
 * Mostra il punteggio percentuale del mese corrente e un mini
 * donut chart con la suddivisione per macro-categorie di spesa.
 * È cliccabile e rimanda alla pagina di dettaglio /lifestyle-score.
 *
 * Prima di aver registrato transazioni in almeno 2 mesi di calendario
 * distinti il widget mostra uno stato "locked" con la challenge di sblocco.
 */
export default function LifestyleWidget({ data, className }: LifestyleWidgetProps) {
    // ── Stato locked: challenge di sblocco ───────────────────────────────────
    if (!data.unlocked) {
        const progress = Math.min(data.months_with_data, data.months_needed);
        const pct      = Math.round((progress / data.months_needed) * 100);

        return (
            <CardBox className={clsx('relative overflow-hidden h-full', className)}>
                {/* Widget sfumato in background */}
                <div className="pointer-events-none select-none opacity-20 blur-[2px]" aria-hidden>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h3 className="flex items-center font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2">📈</span>
                                Lifestyle Score
                            </h3>
                        </div>
                        <span className="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-500">
                            —
                        </span>
                    </div>
                    <div className="flex items-center gap-6">
                        <div className="relative flex-shrink-0" style={{ width: 100, height: 100 }}>
                            <svg width={100} height={100} className="-rotate-90 transform">
                                <circle cx={50} cy={50} r={45} stroke="currentColor" strokeWidth={10} fill="none" className="text-gray-200 dark:text-gray-700" />
                            </svg>
                            <div className="absolute inset-0 flex flex-col items-center justify-center">
                                <span className="text-2xl font-bold text-gray-300">—</span>
                            </div>
                        </div>
                        <div className="min-w-0 flex-1 space-y-2">
                            <div className="h-3 w-3/4 rounded bg-gray-200 dark:bg-gray-700" />
                            <div className="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700" />
                        </div>
                    </div>
                </div>

                {/* Overlay challenge */}
                <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-xl bg-white/80 px-5 text-center backdrop-blur-[1px] dark:bg-gray-800/80">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-2xl dark:bg-indigo-900/40">
                        🔒
                    </div>
                    <div>
                        <p className="font-semibold text-gray-900 dark:text-white">
                            Sblocca il Lifestyle Score
                        </p>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Registra transazioni in almeno{' '}
                            <strong>{data.months_needed} mesi</strong> per attivare questo widget.
                        </p>
                    </div>

                    {/* Barra progresso */}
                    <div className="w-full">
                        <div className="mb-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Progresso</span>
                            <span>{progress}/{data.months_needed} mesi</span>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                className="h-full rounded-full bg-indigo-500 transition-all duration-700"
                                style={{ width: `${pct}%` }}
                            />
                        </div>
                    </div>

                    {progress === 0 ? (
                        <p className="text-xs text-gray-400">
                            💡 Inizia aggiungendo le tue prime transazioni!
                        </p>
                    ) : (
                        <p className="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                            🎯 Ottimo! Ancora {data.months_needed - progress}{' '}
                            {data.months_needed - progress === 1 ? 'mese' : 'mesi'} e sblocchi lo score.
                        </p>
                    )}
                </div>
            </CardBox>
        );
    }

    // ── Stato attivo ─────────────────────────────────────────────────────────
    const score      = data.lifestyle_score;
    const scoreColor = score !== null
        ? (score >= 30 ? '#059669' : score >= 10 ? '#b45309' : '#dc2626')
        : '#64748b';
    const scoreLabel = getScoreLabel(score);

    // Dimensioni gauge circolare SVG
    const size        = 100;
    const strokeWidth = 10;
    const radius      = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const pct     = score !== null ? Math.min(Math.max(score, 0), 100) : 0;
    const offset  = circumference - (pct / 100) * circumference;

    return (
        <Link href={route('lifestyle-score.index')} className="block h-full">
            <CardBox
                className={clsx(
                    'cursor-pointer transition-shadow duration-200 hover:shadow-md h-full',
                    className
                )}
            >
                {/* Header */}
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h3 className="flex items-center font-semibold text-gray-900 dark:text-white">
                            <span className="mr-2">📈</span>
                            Lifestyle Score
                        </h3>
                        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Tocca per dettagli
                        </p>
                    </div>
                    <span className={clsx('rounded-full px-2 py-0.5 text-xs font-semibold', getScoreBadgeClass(score))}>
                        {scoreLabel}
                    </span>
                </div>

                <div className="flex items-center gap-6">
                    {/* Gauge circolare */}
                    <div className="relative flex-shrink-0" style={{ width: size, height: size }}>
                        <svg width={size} height={size} className="-rotate-90 transform">
                            <circle
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                stroke="currentColor"
                                strokeWidth={strokeWidth}
                                fill="none"
                                className="text-gray-200 dark:text-gray-700"
                            />
                            <circle
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                stroke={scoreColor}
                                strokeWidth={strokeWidth}
                                fill="none"
                                strokeDasharray={circumference}
                                strokeDashoffset={offset}
                                strokeLinecap="round"
                                className="transition-all duration-500 ease-in-out"
                            />
                        </svg>
                        <div className="absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-2xl font-bold" style={{ color: scoreColor }}>
                                {score !== null ? `${score.toFixed(0)}%` : '—'}
                            </span>
                            <span className="text-xs text-gray-500 dark:text-gray-400">Score</span>
                        </div>
                    </div>

                    {/* Colonna destra: importi + trend */}
                    <div className="min-w-0 flex-1">
                        <div className="space-y-1.5 text-sm">
                            <div className="flex justify-between">
                                <span className="text-gray-500 dark:text-gray-400">Reddito netto</span>
                                <span className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                    {formatCurrency(data.net_income)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500 dark:text-gray-400">Spese effettive</span>
                                <span className={clsx('font-medium text-red-700 dark:text-red-300', moneyTabular)}>
                                    {formatCurrency(data.effective_expenses)}
                                </span>
                            </div>
                        </div>

                        {/* Indicatore trend ultimi 30 gg */}
                        {data.trend.direction !== 'unknown' && (
                            <div className="mt-3 flex items-center gap-1.5 text-xs">
                                {data.trend.direction === 'up' && (
                                    <span className="font-semibold text-emerald-700 dark:text-emerald-300">
                                        ↑ +{data.trend.delta?.toFixed(1)}% vs 30 gg fa
                                    </span>
                                )}
                                {data.trend.direction === 'down' && (
                                    <span className="font-semibold text-red-500">
                                        ↓ {data.trend.delta?.toFixed(1)}% vs 30 gg fa
                                    </span>
                                )}
                                {data.trend.direction === 'stable' && (
                                    <span className="text-gray-400">
                                        → Stabile vs 30 gg fa
                                    </span>
                                )}
                                {data.trend.direction === 'new' && (
                                    <span className="text-gray-400">
                                        Primo mese registrato
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Footer: top-3 categorie */}
                {data.top_categories.length > 0 && (
                    <div className="mt-4 space-y-1 border-t border-gray-100 pt-3 dark:border-gray-700">
                        {data.top_categories.slice(0, 3).map((cat, i) => (
                            <div key={cat.category_id ?? i} className="flex items-center justify-between text-xs">
                                <div className="flex min-w-0 items-center gap-1 truncate text-gray-600 dark:text-gray-400">
                                    <span>{cat.icon ?? '📁'}</span>
                                    <span className="truncate">{cat.name}</span>
                                </div>
                                <span className="ml-2 flex-shrink-0 text-gray-700 dark:text-gray-300">
                                    {cat.percentage.toFixed(0)}%
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </CardBox>
        </Link>
    );
}
