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
    Legend,
    ResponsiveContainer,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
} from 'recharts';
import { categoryPalette } from '@/Components/Charts/chartConfig';

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
        <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-700/50">
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400">{label}</p>
            <p className={clsx('mt-1 text-xl font-bold', color ?? 'text-gray-900 dark:text-white')}>{value}</p>
            {sub && <p className="mt-0.5 text-xs text-gray-400">{sub}</p>}
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

    // Dati per il donut chart (solo categorie non escluse)
    const pieData = metrics.category_breakdown
        .filter(c => !c.excluded)
        .map((cat, i) => ({
            name:  cat.name,
            value: cat.amount,
            color: cat.color ?? categoryPalette[i % categoryPalette.length],
        }));

    // Dati per il bar chart (tutte le categorie)
    const barData = metrics.category_breakdown.map((cat, i) => ({
        name:     cat.name.length > 12 ? cat.name.slice(0, 12) + '…' : cat.name,
        fullName: cat.name,
        value:    cat.amount,
        color:    cat.excluded ? '#e2e8f0' : (cat.color ?? categoryPalette[i % categoryPalette.length]),
        excluded: cat.excluded,
    }));

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
                        <CardBox className="flex items-center justify-center">
                            <ScoreGauge score={metrics.lifestyle_score} />
                        </CardBox>

                        {/* Metriche di dettaglio */}
                        <CardBox className="lg:col-span-2">
                            <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                                Riepilogo storico completo
                            </h3>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <MetricCard
                                    label="Reddito Lordo"
                                    value={formatEur(metrics.gross_income)}
                                />
                                {metrics.is_partita_iva && (
                                    <>
                                        <MetricCard
                                            label={`INPS (${metrics.inps_rate}%)`}
                                            value={formatEur(metrics.inps_amount)}
                                            color="text-orange-400"
                                        />
                                        <MetricCard
                                            label={`Flat Tax (${metrics.tax_rate}% su lordo−INPS)`}
                                            value={formatEur(metrics.flat_tax_amount)}
                                            color="text-orange-500"
                                            sub="base imponibile = lordo − INPS"
                                        />
                                    </>
                                )}
                                <MetricCard
                                    label="Reddito Netto"
                                    value={formatEur(metrics.net_income)}
                                    color="text-emerald-600"
                                    sub={metrics.is_partita_iva ? 'al netto delle tasse' : undefined}
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
                                    sub="tasse + investimenti esclusi"
                                />
                            </div>

                            {/* Formula visuale */}
                            <div className="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-gray-700/50 dark:text-gray-400 space-y-1">
                                {metrics.is_partita_iva && (
                                    <p className="font-mono">
                                        INPS = Lordo × {metrics.inps_rate}% &nbsp;|&nbsp;
                                        Flat Tax = (Lordo − INPS) × {metrics.tax_rate}%
                                    </p>
                                )}
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
                        <div className="flex flex-wrap items-center gap-6">
                            {/* Score ultimi 30 gg */}
                            <div className="text-center">
                                <p className="text-xs text-gray-500 dark:text-gray-400">Ultimi 30 gg</p>
                                <p className="text-2xl font-bold"
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
                                <p className="text-2xl font-bold text-gray-400 dark:text-gray-500">
                                    {trend.prev30_score !== null ? `${trend.prev30_score.toFixed(1)}%` : '—'}
                                </p>
                            </div>

                            {/* Messaggio testuale */}
                            <div className="ml-auto text-sm">
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
                        {/* Donut chart — suddivisione spese effettive */}
                        <CardBox>
                            <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                                Distribuzione Spese Effettive
                            </h3>
                            {pieData.length > 0 ? (
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={pieData}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={60}
                                                outerRadius={100}
                                                dataKey="value"
                                                strokeWidth={0}
                                                label={({ name, percent }: { name?: string; percent?: number }) =>
                                                    (percent ?? 0) > 0.05 ? `${((percent ?? 0) * 100).toFixed(0)}%` : ''
                                                }
                                            >
                                                {pieData.map((entry, index) => (
                                                    <Cell key={index} fill={entry.color} />
                                                ))}
                                            </Pie>
                                            <Tooltip
                                                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                                formatter={(value: any, name: any) => [formatEur(value ?? 0), name ?? '']}
                                                contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #e2e8f0' }}
                                            />
                                            <Legend
                                                formatter={(value) => (
                                                    <span className="text-xs text-gray-700 dark:text-gray-300">{value}</span>
                                                )}
                                            />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <div className="flex h-40 items-center justify-center text-gray-400">
                                    Nessuna spesa registrata
                                </div>
                            )}
                        </CardBox>

                        {/* Bar chart — tutte le categorie */}
                        <CardBox>
                            <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                                Spesa per Categoria
                            </h3>
                            {barData.length > 0 ? (
                                <div className="h-72">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={barData} layout="vertical" margin={{ left: 0, right: 16 }}>
                                            <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                                            <XAxis
                                                type="number"
                                                tickFormatter={v => `€${(v / 1000).toFixed(0)}k`}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <YAxis
                                                dataKey="name"
                                                type="category"
                                                width={90}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip
                                                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                                                formatter={(value: any, _name: any, props: any) => [
                                                    formatEur(value ?? 0),
                                                    props.payload.fullName + (props.payload.excluded ? ' (escluso)' : ''),
                                                ]}
                                                contentStyle={{ fontSize: 12, borderRadius: 8, border: '1px solid #e2e8f0' }}
                                            />
                                            <Bar dataKey="value" radius={[0, 4, 4, 0]}>
                                                {barData.map((entry, index) => (
                                                    <Cell key={index} fill={entry.color} opacity={entry.excluded ? 0.4 : 1} />
                                                ))}
                                            </Bar>
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
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
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
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
                                                <td className="py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className="inline-block h-2.5 w-2.5 rounded-full"
                                                            style={{ backgroundColor: cat.color ?? categoryPalette[i % categoryPalette.length] }}
                                                        />
                                                        <span>{cat.icon}</span>
                                                        <span className="font-medium text-gray-900 dark:text-white">{cat.name}</span>
                                                    </div>
                                                </td>
                                                <td className="py-3 text-right text-gray-700 dark:text-gray-300">
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
                                            <td className="pt-3 text-right font-semibold text-red-500">
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
                                question="Perché per la Partita IVA il calcolo è diverso?"
                                answer="I lavoratori autonomi in regime forfettario ricevono compensi lordi: devono versare INPS e flat tax prima di poter spendere. Lo score usa quindi il reddito netto stimato (dopo le tasse) per rispecchiare il reale potere d'acquisto. I dipendenti invece ricevono già lo stipendio netto in busta paga, quindi nessun calcolo fiscale viene applicato."
                            />
                            <FaqItem
                                question="Come vengono calcolate le tasse per la Partita IVA?"
                                answer={
                                    <ol className="list-decimal pl-5 space-y-1">
                                        <li><strong>INPS</strong> = Reddito Lordo × aliquota INPS (default 26,23%)</li>
                                        <li><strong>Flat Tax</strong> = (Reddito Lordo − INPS) × aliquota flat tax (default 15%)<br /><span className="text-gray-500 text-xs">I contributi INPS sono deducibili dalla base imponibile fiscale (art. 1 c. 64 L. 190/2014).</span></li>
                                        <li><strong>Tasse totali</strong> = INPS + Flat Tax</li>
                                        <li><strong>Reddito Netto</strong> = Reddito Lordo − Tasse Totali</li>
                                    </ol>
                                }
                            />
                            <FaqItem
                                question="Posso personalizzare le aliquote?"
                                answer="Sì. Nelle impostazioni del tuo profilo puoi modificare l'aliquota INPS e la flat tax per adattarle alla tua situazione specifica (es. regime ordinario, aliquota INPS ridotta al 5% per nuove aperture, ecc.)."
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
