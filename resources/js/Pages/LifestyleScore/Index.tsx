import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import { Head, Link, router } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import clsx from 'clsx';
import {
    PieChart,
    Pie,
    Cell,
    Tooltip,
    ResponsiveContainer,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
} from 'recharts';
import { categoryPalette } from '@/Components/Charts/chartConfig';
import { moneyMetricGrid3 } from '@/utils/moneyGridClasses';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface CategoryRow {
    category_id: number | null;
    name: string;
    icon: string | null;
    color: string | null;
    amount: number;
    percentage: number;
    excluded: boolean;
}

interface LifestyleBarDatum {
    name: string;
    fullName: string;
    value: number;
    color: string;
    excluded: boolean;
}

interface Metrics {
    gross_income: number;
    estimated_taxes: number;
    inps_amount: number;
    flat_tax_amount: number;
    net_income: number;
    total_expenses: number;
    excluded_expenses: number;
    effective_expenses: number;
    lifestyle_score: number | null;
    tax_rate: number;
    inps_rate: number;
    is_partita_iva: boolean;
    category_breakdown: CategoryRow[];
}

interface Trend {
    last30_score: number | null;
    prev30_score: number | null;
    delta: number | null;
    direction: 'up' | 'down' | 'stable' | 'new' | 'unknown';
}

interface IndexProps {
    metrics: Metrics;
    trend: Trend;
    dateRangeLabel: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function formatEur(amount: number): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

/** Massimo numero di fette distinte nel donut (oltre → «Altri»). */
const MAX_PIE_SLICES_BEFORE_OTHER = 9;

type PieDatum = {
    name: string;
    value: number;
    color: string;
    /** Elenco categorie aggregate in «Altri» (solo per la fetta Altri). */
    otherDetail?: string;
};

/**
 * Riduce il numero di fette nel pie chart: le categorie oltre le prime N-1
 * vengono aggregate in «Altri (k)» per leggibilità su schermi piccoli.
 */
function aggregatePieData(raw: Array<{ name: string; value: number; color: string }>): PieDatum[] {
    if (raw.length === 0) return [];
    const sorted = [...raw].sort((a, b) => b.value - a.value);
    if (sorted.length <= MAX_PIE_SLICES_BEFORE_OTHER) {
        return sorted.map((r) => ({ name: r.name, value: r.value, color: r.color }));
    }
    const headCount = MAX_PIE_SLICES_BEFORE_OTHER - 1;
    const head = sorted.slice(0, headCount);
    const tail = sorted.slice(headCount);
    const otherValue = tail.reduce((s, r) => s + r.value, 0);
    const otherDetail = tail.map((r) => r.name).join(', ');

    return [
        ...head.map((r) => ({ name: r.name, value: r.value, color: r.color })),
        {
            name: `Altri (${tail.length})`,
            value: otherValue,
            color: '#64748b',
            otherDetail,
        },
    ];
}

type PieTooltipProps = {
    active?: boolean;
    payload?: ReadonlyArray<{ payload?: PieDatum }>;
    total: number;
    formatEurFn: (amount: number) => string;
};

function LifestylePieTooltip({ active, payload, total, formatEurFn }: PieTooltipProps) {
    const p = payload?.[0]?.payload;
    if (!active || !p) return null;
    const pct = total > 0 ? (p.value / total) * 100 : null;

    return (
        <div className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs shadow-md dark:border-gray-600 dark:bg-gray-800">
            <p className="font-semibold text-gray-900 dark:text-white">{p.name}</p>
            <p className="tabular-nums text-gray-800 dark:text-gray-200">{formatEurFn(p.value)}</p>
            {pct !== null && (
                <p className="tabular-nums text-gray-500 dark:text-gray-400">{pct.toFixed(1)}% del totale</p>
            )}
            {p.otherDetail && (
                <p className="mt-1 max-w-[min(280px,85vw)] text-[11px] leading-snug wrap-break-word text-gray-500 dark:text-gray-400">
                    Include: {p.otherDetail}
                </p>
            )}
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

function getScoreColor(score: number | null): string {
    if (score === null) return '#94a3b8';
    if (score >= 30) return '#10b981';
    if (score >= 10) return '#f59e0b';
    return '#ef4444';
}

function getScoreLabel(score: number | null): { label: string; description: string } {
    if (score === null) return { label: 'Dati insufficienti', description: 'Inserisci entrate e uscite per calcolare il tuo score.' };
    if (score >= 30) return { label: 'Ottimo 🎉', description: 'Stai risparmiando in modo sano. Continua così!' };
    if (score >= 10) return { label: 'Attenzione ⚠️', description: 'Le spese stanno erodendo buona parte del tuo reddito.' };
    return { label: 'Critico 🚨', description: 'Le spese superano quasi il reddito netto. Rivedi il budget.' };
}

function ScoreGauge({ score }: { score: number | null }) {
    const scoreColor  = getScoreColor(score);
    const { label, description } = getScoreLabel(score);
    const size        = 200;
    const strokeWidth = 18;
    const radius      = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const pct         = score !== null ? Math.min(Math.max(score, 0), 100) : 0;
    const offset      = circumference - (pct / 100) * circumference;

    return (
        <div className="flex flex-col items-center">
            <div className="relative" style={{ width: size, height: size }}>
                <svg width={size} height={size} className="-rotate-90 transform">
                    <circle
                        cx={size / 2} cy={size / 2} r={radius}
                        stroke="currentColor" strokeWidth={strokeWidth} fill="none"
                        className="text-gray-200 dark:text-gray-700"
                    />
                    <circle
                        cx={size / 2} cy={size / 2} r={radius}
                        stroke={scoreColor} strokeWidth={strokeWidth} fill="none"
                        strokeDasharray={circumference} strokeDashoffset={offset}
                        strokeLinecap="round"
                        className="transition-all duration-700 ease-in-out"
                    />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-4xl font-bold" style={{ color: scoreColor }}>
                        {score !== null ? `${score.toFixed(1)}%` : '—'}
                    </span>
                    <span className="mt-1 text-sm text-gray-500 dark:text-gray-400">Lifestyle Score</span>
                </div>
            </div>
            <div className="mt-3 text-center">
                <p className="text-lg font-semibold" style={{ color: scoreColor }}>{label}</p>
                <p className="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">{description}</p>
            </div>
        </div>
    );
}

function MetricCard({ label, value, sub, color }: { label: string; value: string; sub?: string; color?: string }) {
    return (
        <div className="min-w-0 rounded-xl bg-gray-50 p-2.5 min-[380px]:p-3 sm:p-4 dark:bg-gray-700/50">
            <p className="hyphens-auto wrap-break-word text-[10px] font-medium leading-snug text-gray-500 dark:text-gray-400 sm:text-xs">
                {label}
            </p>
            <p
                className={clsx(
                    'mt-0.5 min-w-0 wrap-break-word text-sm font-bold tabular-nums leading-tight tracking-tight',
                    'min-[380px]:text-base sm:text-lg lg:text-xl',
                    color ?? 'text-gray-900 dark:text-white',
                )}
            >
                {value}
            </p>
            {sub && (
                <p className="mt-0.5 text-[10px] leading-snug text-gray-400 sm:text-xs">{sub}</p>
            )}
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Page
// ─────────────────────────────────────────────────────────────────────────────

export default function Index({ metrics, trend, dateRangeLabel }: IndexProps) {
    function toggleExclusion(categoryId: number) {
        router.post(
            route('categories.toggle-lifestyle-score', categoryId),
            {},
            { preserveScroll: true }
        );
    }

    // Dati per il donut chart (solo categorie non escluse), aggregati se molte fette
    const pieDataRaw = metrics.category_breakdown
        .filter(c => !c.excluded)
        .map((cat, i) => ({
            name:  cat.name,
            value: cat.amount,
            color: cat.color ?? categoryPalette[i % categoryPalette.length],
        }));
    const pieData = aggregatePieData(pieDataRaw);
    const pieTotal = pieData.reduce((s, d) => s + d.value, 0);

    // Dati per il bar chart (tutte le categorie) — nome completo (niente troncamento nel dato)
    const barData = metrics.category_breakdown.map((cat, i) => ({
        name:     cat.name,
        fullName: cat.name,
        value:    cat.amount,
        color:    cat.excluded ? '#cbd5e1' : (cat.color ?? categoryPalette[i % categoryPalette.length]),
        excluded: cat.excluded,
    }));
    const maxBarValue = Math.max(...barData.map((d) => d.value), 1);
    const barChartHeight = Math.min(560, Math.max(240, barData.length * 30 + 64));
    const yAxisWidth = Math.min(
        240,
        88 + Math.min(36, Math.max(...barData.map((d) => d.name.length), 8)) * 5.8,
    );

    const exportXlsUrl = route('lifestyle-score.export-xls');
    const exportPdfUrl = route('lifestyle-score.export-pdf');

    return (
        <AuthenticatedLayout header={
            <PageHeader
                title="Lifestyle Inflation Score"
                subtitle={`Storico: ${dateRangeLabel}`}
                backLink={route('dashboard')}
            />
        }>
            <Head title="Lifestyle Inflation Score" />

            <PageContent>

                    {/* ── Score + metriche ────────────────────────────────────────────────── */}
                    <div className="grid gap-6 lg:grid-cols-3">
                        {/* Gauge */}
                        <CardBox className="flex items-center justify-center p-3! sm:p-6!">
                            <div className="max-[359px]:origin-top max-[359px]:scale-[0.88] sm:scale-100">
                                <ScoreGauge score={metrics.lifestyle_score} />
                            </div>
                        </CardBox>

                        {/* Metriche di dettaglio */}
                        <CardBox className="p-3! sm:p-6! lg:col-span-2">
                            <h3 className="mb-3 font-semibold text-gray-900 dark:text-white sm:mb-4">
                                Riepilogo storico completo
                            </h3>
                            <div className={moneyMetricGrid3}>
                                <MetricCard
                                    label="Reddito Lordo"
                                    value={formatEur(metrics.gross_income)}
                                />
                                <MetricCard
                                    label="Reddito Netto"
                                    value={formatEur(metrics.net_income)}
                                    color="text-emerald-600"
                                />
                                <MetricCard
                                    label="Spese Totali"
                                    value={formatEur(metrics.total_expenses)}
                                    color="text-red-500"
                                />
                                <MetricCard
                                    label="Investimenti / Esclusi"
                                    value={formatEur(metrics.excluded_expenses)}
                                    color="text-blue-500"
                                />
                                <MetricCard
                                    label="Spese Effettive"
                                    value={formatEur(metrics.effective_expenses)}
                                    color="text-orange-500"
                                    sub="categorie escluse sottratte"
                                />
                            </div>

                            {/* Formula visuale */}
                            <div className="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-gray-700/50 dark:text-gray-400 space-y-1">
                                <p className="font-mono">
                                    Score = (Reddito Netto − Spese Effettive) ÷ Reddito Netto × 100
                                </p>
                            </div>
                        </CardBox>
                    </div>

                    {/* ── Trend ultimi 30 giorni ─────────────────────────────────────────── */}
                    <CardBox>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Tendenza — ultimi 30 giorni
                        </h3>
                        <div className="flex flex-wrap items-center gap-4 min-[380px]:gap-6">
                            {/* Score ultimi 30 gg */}
                            <div className="text-center">
                                <p className="text-xs text-gray-500 dark:text-gray-400">Ultimi 30 gg</p>
                                <p className="text-xl font-bold tabular-nums min-[380px]:text-2xl"
                                    style={{ color: trend.last30_score !== null ? (trend.last30_score >= 30 ? '#10b981' : trend.last30_score >= 10 ? '#f59e0b' : '#ef4444') : '#94a3b8' }}
                                >
                                    {trend.last30_score !== null ? `${trend.last30_score.toFixed(1)}%` : '—'}
                                </p>
                            </div>

                            {/* Freccia + delta */}
                            <div className="flex flex-col items-center">
                                {trend.direction === 'up' && (
                                    <span className="text-3xl text-emerald-500">↑</span>
                                )}
                                {trend.direction === 'down' && (
                                    <span className="text-3xl text-red-500">↓</span>
                                )}
                                {(trend.direction === 'stable') && (
                                    <span className="text-3xl text-gray-400">→</span>
                                )}
                                {trend.delta !== null && (
                                    <span className={clsx(
                                        'text-sm font-semibold',
                                        trend.direction === 'up'   && 'text-emerald-600 dark:text-emerald-400',
                                        trend.direction === 'down' && 'text-red-500',
                                        trend.direction === 'stable' && 'text-gray-400',
                                    )}>
                                        {trend.delta > 0 ? '+' : ''}{trend.delta.toFixed(1)}%
                                    </span>
                                )}
                            </div>

                            {/* Score 30 gg precedenti */}
                            <div className="text-center">
                                <p className="text-xs text-gray-500 dark:text-gray-400">30 gg precedenti</p>
                                <p className="text-xl font-bold tabular-nums text-gray-400 dark:text-gray-500 min-[380px]:text-2xl">
                                    {trend.prev30_score !== null ? `${trend.prev30_score.toFixed(1)}%` : '—'}
                                </p>
                            </div>

                            {/* Messaggio testuale */}
                            <div className="w-full text-sm min-[480px]:ml-auto min-[480px]:w-auto">
                                {trend.direction === 'up' && (
                                    <p className="text-emerald-600 dark:text-emerald-400">🎉 Stai migliorando il tuo stile di vita!</p>
                                )}
                                {trend.direction === 'down' && (
                                    <p className="text-red-500">⚠️ Attenzione: lo score è peggiorato rispetto al mese scorso.</p>
                                )}
                                {trend.direction === 'stable' && (
                                    <p className="text-gray-500">→ Stile di vita stabile rispetto ai 30 giorni precedenti.</p>
                                )}
                                {trend.direction === 'new' && (
                                    <p className="text-gray-500">Primo periodo registrato, torna tra 30 giorni per vedere il trend.</p>
                                )}
                                {trend.direction === 'unknown' && (
                                    <p className="text-gray-400">Dati insufficienti per calcolare il trend.</p>
                                )}
                            </div>
                        </div>
                    </CardBox>

                    {/* ── Grafici ───────────────────────────────────────────────────────── */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Donut chart — suddivisione spese effettive (legenda scrollabile, fette aggregate) */}
                        <CardBox>
                            <h3 className="mb-3 font-semibold text-gray-900 dark:text-white">
                                Distribuzione Spese Effettive
                            </h3>
                            {pieData.length > 0 ? (
                                <div className="flex flex-col">
                                    <div className="h-52 shrink-0 sm:h-60">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={pieData}
                                                    cx="50%"
                                                    cy="50%"
                                                    innerRadius={56}
                                                    outerRadius={88}
                                                    dataKey="value"
                                                    nameKey="name"
                                                    paddingAngle={1}
                                                    strokeWidth={0}
                                                    labelLine={false}
                                                    label={({ percent }: { percent?: number }) =>
                                                        (percent ?? 0) >= 0.06 ? `${((percent ?? 0) * 100).toFixed(0)}%` : ''
                                                    }
                                                >
                                                    {pieData.map((entry, index) => (
                                                        <Cell key={index} fill={entry.color} />
                                                    ))}
                                                </Pie>
                                                <Tooltip
                                                    content={(props) => (
                                                        <LifestylePieTooltip
                                                            active={props.active}
                                                            payload={props.payload}
                                                            total={pieTotal}
                                                            formatEurFn={formatEur}
                                                        />
                                                    )}
                                                />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                    <ul
                                        className="mt-3 max-h-40 space-y-1 overflow-y-auto overscroll-contain border-t border-gray-100 pt-3 dark:border-gray-700 sm:max-h-44"
                                        aria-label="Legenda distribuzione spese"
                                    >
                                        {pieData.map((d, i) => (
                                            <li
                                                key={`${d.name}-${i}`}
                                                className="flex items-start justify-between gap-2 text-xs"
                                            >
                                                <span className="flex min-w-0 items-start gap-2">
                                                    <span
                                                        className="mt-1 h-2 w-2 shrink-0 rounded-full"
                                                        style={{ backgroundColor: d.color }}
                                                        aria-hidden
                                                    />
                                                    <span
                                                        className="wrap-break-word text-gray-700 dark:text-gray-300"
                                                        title={d.name}
                                                    >
                                                        {d.name}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">
                                                    {pieTotal > 0
                                                        ? `${((d.value / pieTotal) * 100).toFixed(1)}%`
                                                        : '—'}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : (
                                <div className="flex h-40 items-center justify-center text-gray-400">
                                    Nessuna spesa registrata
                                </div>
                            )}
                        </CardBox>

                        {/* Bar chart — lista su mobile, grafico scrollabile su desktop */}
                        <CardBox>
                            <h3 className="mb-3 font-semibold text-gray-900 dark:text-white">
                                Spesa per Categoria
                            </h3>
                            {barData.length > 0 ? (
                                <>
                                    <div className="space-y-3 lg:hidden">
                                        {barData.map((row, i) => (
                                            <div
                                                key={`${row.fullName}-${i}`}
                                                className="rounded-lg border border-gray-100 bg-gray-50/90 p-3 dark:border-gray-600 dark:bg-gray-900/40"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex min-w-0 items-start gap-2">
                                                        <span
                                                            className="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full"
                                                            style={{ backgroundColor: row.color }}
                                                            aria-hidden
                                                        />
                                                        <span className="min-w-0 flex-1 wrap-break-word text-sm font-medium leading-snug text-gray-900 dark:text-white">
                                                            {row.fullName}
                                                        </span>
                                                    </div>
                                                    <span
                                                        className={clsx(
                                                            'shrink-0 text-xs font-semibold tabular-nums',
                                                            row.excluded
                                                                ? 'text-gray-400 line-through dark:text-gray-500'
                                                                : 'text-gray-800 dark:text-gray-200',
                                                        )}
                                                    >
                                                        {formatEur(row.value)}
                                                    </span>
                                                </div>
                                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div
                                                        className="h-full rounded-full transition-all"
                                                        style={{
                                                            width: `${Math.min(100, (row.value / maxBarValue) * 100)}%`,
                                                            backgroundColor: row.color,
                                                            opacity: row.excluded ? 0.45 : 1,
                                                        }}
                                                    />
                                                </div>
                                                {row.excluded && (
                                                    <p className="mt-1 text-[10px] text-gray-400">
                                                        Escluso dal calcolo dello score
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>

                                    <div className="hidden max-h-[min(560px,72vh)] overflow-y-auto overflow-x-hidden lg:block">
                                        <div style={{ height: barChartHeight }} className="min-h-[240px]">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={barData}
                                                    layout="vertical"
                                                    margin={{ left: 4, right: 16, top: 8, bottom: 8 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                                    <XAxis
                                                        type="number"
                                                        tickFormatter={(v) => `€${(v / 1000).toFixed(0)}k`}
                                                        tick={{ fontSize: 11 }}
                                                    />
                                                    <YAxis
                                                        dataKey="name"
                                                        type="category"
                                                        width={yAxisWidth}
                                                        tick={{ fontSize: 11 }}
                                                        interval={0}
                                                    />
                                                    <Tooltip
                                                        formatter={(value, _name, item) => {
                                                            const row = item?.payload as LifestyleBarDatum | undefined;
                                                            const label =
                                                                (row?.fullName ?? '') +
                                                                (row?.excluded ? ' (escluso)' : '');
                                                            return [formatEur(Number(value ?? 0)), label];
                                                        }}
                                                        contentStyle={{
                                                            fontSize: 12,
                                                            borderRadius: 8,
                                                            border: '1px solid #e2e8f0',
                                                        }}
                                                    />
                                                    <Bar dataKey="value" radius={[0, 4, 4, 0]} maxBarSize={22}>
                                                        {barData.map((entry, index) => (
                                                            <Cell
                                                                key={index}
                                                                fill={entry.color}
                                                                opacity={entry.excluded ? 0.45 : 1}
                                                            />
                                                        ))}
                                                    </Bar>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div className="flex h-40 items-center justify-center text-gray-400">
                                    Nessuna spesa registrata
                                </div>
                            )}
                        </CardBox>
                    </div>

                    {/* ── Tabella dettaglio categorie ─────────────────────────────────────── */}
                    <CardBox>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Dettaglio per Categoria
                        </h3>
                        {metrics.category_breakdown.length > 0 ? (
                            <div className="overflow-x-auto -mx-1 px-1 sm:mx-0 sm:px-0">
                                <table className="w-full min-w-0 text-xs sm:text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 dark:border-gray-700">
                                            <th className="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Categoria</th>
                                            <th className="pb-3 text-right font-medium text-gray-500 dark:text-gray-400">Importo</th>
                                            <th className="pb-3 text-right font-medium text-gray-500 dark:text-gray-400">% sul totale</th>
                                            <th className="pb-3 text-center font-medium text-gray-500 dark:text-gray-400">Nel Score</th>
                                            <th className="pb-3 text-center font-medium text-gray-500 dark:text-gray-400">Azione</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {metrics.category_breakdown.map((cat, i) => (
                                            <tr
                                                key={cat.category_id ?? i}
                                                className={clsx(
                                                    'border-b border-gray-50 last:border-0 dark:border-gray-700/50',
                                                    cat.excluded && 'opacity-50'
                                                )}
                                            >
                                                <td className="min-w-0 py-3">
                                                    <div className="flex min-w-0 items-center gap-2">
                                                        <span
                                                            className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                                            style={{ backgroundColor: cat.color ?? categoryPalette[i % categoryPalette.length] }}
                                                        />
                                                        <span className="shrink-0">{cat.icon}</span>
                                                        <span className="min-w-0 wrap-break-word font-medium text-gray-900 dark:text-white">{cat.name}</span>
                                                    </div>
                                                </td>
                                                <td className="max-w-[42vw] py-3 text-right tabular-nums text-gray-700 wrap-break-word dark:text-gray-300 sm:max-w-none">
                                                    {formatEur(cat.amount)}
                                                </td>
                                                <td className="py-3 text-right text-gray-500 dark:text-gray-400">
                                                    {cat.percentage.toFixed(1)}%
                                                </td>
                                                <td className="py-3 text-center">
                                                    {cat.excluded ? (
                                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                            Escluso
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                            Incluso
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 text-center">
                                                    {cat.category_id !== null && (
                                                        <button
                                                            onClick={() => toggleExclusion(cat.category_id!)}
                                                            title={cat.excluded ? 'Includi nel calcolo' : 'Escludi dal calcolo'}
                                                            className={clsx(
                                                                'rounded-md px-2 py-1 text-xs font-medium transition',
                                                                cat.excluded
                                                                    ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'
                                                            )}
                                                        >
                                                            {cat.excluded ? '+ Includi' : '− Escludi'}
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t-2 border-gray-200 dark:border-gray-600">
                                            <td className="pt-3 font-semibold text-gray-900 dark:text-white">Totale</td>
                                            <td className="pt-3 text-right font-semibold tabular-nums text-red-500 wrap-break-word">
                                                {formatEur(metrics.total_expenses)}
                                            </td>
                                            <td className="pt-3 text-right font-medium text-gray-500">100%</td>
                                            <td />
                                            <td />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        ) : (
                            <div className="flex h-24 items-center justify-center text-gray-400">
                                Nessuna transazione registrata
                            </div>
                        )}
                    </CardBox>

                    {/* ── Esporta dati ────────────────────────────────────────────────────── */}
                    <CardBox>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Esporta i tuoi dati
                        </h3>
                        <div className="flex flex-wrap gap-3">
                            <a
                                href={exportXlsUrl}
                                className="flex items-center gap-2 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50"
                            >
                                <span>📊</span>
                                <span>Esporta XLS</span>
                                <span className="text-xs text-emerald-500 dark:text-emerald-500">— dati completi con dettaglio categorie</span>
                            </a>
                            <a
                                href={exportPdfUrl}
                                className="flex items-center gap-2 rounded-lg bg-blue-50 px-4 py-2.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50"
                            >
                                <span>📄</span>
                                <span>Esporta PDF</span>
                                <span className="text-xs text-blue-500 dark:text-blue-500">— report sintetico stampabile</span>
                            </a>
                        </div>
                    </CardBox>

                    {/* ── Tip configurazione ──────────────────────────────────────────────── */}
                    <div className="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                        <strong>💡 Suggerimento:</strong> Puoi escludere categorie come "Investimenti" dal calcolo
                        dello score nelle{' '}
                        <Link href={route('categories.index')} className="underline hover:no-underline">
                            impostazioni categorie
                        </Link>
                        .
                    </div>

                    {/* ── FAQ ─────────────────────────────────────────────────────────────── */}
                    <CardBox>
                        <h3 className="mb-5 font-semibold text-gray-900 dark:text-white text-lg">
                            ❓ Domande frequenti sul Lifestyle Inflation Score
                        </h3>
                        <div className="space-y-4">
                            <FaqItem
                                question="Cos'è il Lifestyle Inflation Score?"
                                answer="È un indicatore che misura la percentuale di reddito netto rimasta dopo aver coperto le spese di stile di vita. Un valore alto significa che stai mantenendo buone abitudini finanziarie; un valore basso indica che le spese si avvicinano pericolosamente al reddito disponibile."
                            />
                            <FaqItem
                                question="Come viene calcolato lo score?"
                                answer="Score = (Reddito Netto − Spese Effettive) ÷ Reddito Netto × 100. Le «Spese Effettive» escludono investimenti e categorie che hai scelto di non conteggiare, così da misurare soltanto la spesa «di stile di vita»."
                            />
                            <FaqItem
                                question="Come si interpretano i valori?"
                                answer={
                                    <ul className="list-disc pl-5 space-y-1">
                                        <li><span className="font-semibold text-emerald-600">≥ 30% — Ottimo:</span> stai risparmiando in modo sano e mantenendo un margine finanziario solido.</li>
                                        <li><span className="font-semibold text-amber-500">10%–29% — Attenzione:</span> le spese stanno erodendo buona parte del reddito. Monitora le categorie più pesanti.</li>
                                        <li><span className="font-semibold text-red-500">&lt; 10% — Critico:</span> le spese coprono quasi tutto il reddito netto. Rivedi il budget il prima possibile.</li>
                                    </ul>
                                }
                            />
                            <FaqItem
                                question="Cosa si intende per «spese escluse»?"
                                answer="Sono le transazioni appartenenti a categorie marcate come «escludi dal Lifestyle Score», ad esempio Investimenti, Fondi pensione o Risparmio programmato. Questi importi vengono sottratti dalle spese totali prima del calcolo: non influenzano negativamente lo score perché non rappresentano «consumo» ma accumulo di ricchezza."
                            />
                            <FaqItem
                                question="Lo score considera lo storico completo o solo il mese in corso?"
                                answer="La pagina di dettaglio mostra lo score calcolato sull'intero storico disponibile (dalla prima transazione registrata ad oggi). Il widget in dashboard e la sezione «Tendenza» mostrano invece gli ultimi 30 giorni e il confronto con i 30 giorni precedenti."
                            />
                            <FaqItem
                                question="I trasferimenti tra conti influenzano lo score?"
                                answer="No. I trasferimenti interni tra conti dello stesso nucleo familiare (household) vengono esclusi automaticamente sia dal calcolo del reddito che da quello delle spese, per evitare doppi conteggi."
                            />
                        </div>
                    </CardBox>

            </PageContent>
        </AuthenticatedLayout>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// FaqItem sub-component
// ─────────────────────────────────────────────────────────────────────────────

function FaqItem({ question, answer }: { question: string; answer: React.ReactNode }) {
    const [open, setOpen] = React.useState(false);

    return (
        <div className="rounded-lg border border-gray-100 dark:border-gray-700">
            <button
                type="button"
                onClick={() => setOpen(o => !o)}
                className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50 transition-colors rounded-lg"
                aria-expanded={open}
            >
                <span>{question}</span>
                <span
                    className={clsx(
                        'ml-4 shrink-0 text-gray-400 transition-transform duration-200',
                        open && 'rotate-180'
                    )}
                    aria-hidden
                >
                    ▾
                </span>
            </button>
            {open && (
                <div className="border-t border-gray-100 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400 leading-relaxed">
                    {answer}
                </div>
            )}
        </div>
    );
}
