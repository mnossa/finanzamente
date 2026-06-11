import React from 'react';
import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import {
    ResponsiveContainer,
    AreaChart,
    Area,
    LineChart,
    Line,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    Treemap,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
} from 'recharts';
import { ProgressBar } from '@/Components/ProgressBar';
import { formatCurrency } from '@/utils/format';
import { moneyTabular } from '@/utils/moneyGridClasses';
import {
    formatEuro,
    getChartMutedTextColor,
    getChartTooltipStyle,
    useChartDarkMode,
    useCompactChart,
} from '@/Components/Charts/chartConfig';
import type { FormulaDeltaPolarity, FormulaWidgetPayload } from '@/types/formulaWidget';

const CHART_HEIGHT_DESKTOP = 256;
const CHART_HEIGHT_MOBILE = 200;

const DEFAULT_COLORS = ['#3b82f6', '#10b981', '#f97316', '#8b5cf6', '#ef4444'];

interface CustomFormulaWidgetProps {
    payload: FormulaWidgetPayload;
    embedded?: boolean;
    className?: string;
}

function formatValue(value: number, format: 'currency' | 'percent'): string {
    if (format === 'percent') {
        return `${value.toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
    }

    return formatCurrency(value);
}

const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `€${(value / 1_000).toFixed(0)}k`;

    return `€${value.toFixed(0)}`;
};

function ChartTooltip({
    active,
    payload,
    label,
}: {
    active?: boolean;
    payload?: Array<{ value?: number; name?: string; color?: string }>;
    label?: string;
}) {
    const isDark = useChartDarkMode();
    const tooltipStyle = getChartTooltipStyle(isDark);

    if (!active || !payload?.length) return null;

    return (
        <div style={{ ...tooltipStyle, minWidth: '160px' }}>
            <p style={{ fontWeight: 600, marginBottom: '4px', color: getChartMutedTextColor(isDark) }}>
                {label ?? ''}
            </p>
            {payload.map((entry) => (
                <p key={String(entry.name)} style={{ margin: '2px 0', color: entry.color ?? '#64748b' }}>
                    {entry.name}: <strong>{formatEuro(Number(entry.value ?? 0))}</strong>
                </p>
            ))}
        </div>
    );
}

function BalanceSummaryView({
    payload,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'kpi' }> & { variant: 'balance_summary' };
}) {
    const patrimonio =
        payload.patrimonioTotal
        ?? payload.value + (payload.investedLinked ?? 0);

    return (
        <Link href={route('patrimonio.index')} className="block h-full">
            <div className="overflow-hidden rounded-2xl bg-linear-to-br from-slate-800 to-slate-900 p-4 text-white shadow-lg transition-shadow hover:shadow-xl sm:p-5">
                <h3 className="text-sm font-medium text-slate-300">Saldo conti</h3>
                <p className={clsx('mt-1.5 text-3xl font-bold sm:mt-2 sm:text-4xl', moneyTabular)}>
                    {formatCurrency(payload.value)}
                </p>
                <p className="mt-1 text-xs text-slate-500">Somma saldi conti attivi (liquidità)</p>
                <p className="mt-2 text-sm text-slate-400">
                    Investimenti aperti{' '}
                    <span className={moneyTabular}>{formatCurrency(payload.invested ?? 0)}</span>
                </p>
                <p className="mt-0.5 text-xs text-slate-500">
                    Costo di carico · non incluso nel saldo conti
                </p>
                <p className="mt-2 border-t border-slate-700/60 pt-2 text-sm text-slate-300">
                    Patrimonio netto{' '}
                    <span className={moneyTabular}>{formatCurrency(patrimonio)}</span>
                </p>
                <p className="mt-0.5 text-xs text-slate-500">
                    Saldo conti + investimenti collegati al ledger (costo di carico)
                </p>
                <p className="mt-1 text-xs text-slate-500">
                    {payload.accountsCount ?? 0}{' '}
                    {(payload.accountsCount ?? 0) === 1 ? 'conto attivo' : 'conti attivi'} · Dettaglio patrimonio
                </p>
            </div>
        </Link>
    );
}

function resolveKpiDeltaDisplay(
    delta: number,
    polarity: FormulaDeltaPolarity,
): { trend: 'up' | 'down'; colorClass: string; arrow: string } {
    const isPositiveOutcome = polarity === 'lower_is_better' ? delta <= 0 : delta >= 0;

    return {
        trend: isPositiveOutcome ? 'up' : 'down',
        colorClass: isPositiveOutcome ? 'text-emerald-500' : 'text-red-500',
        arrow: isPositiveOutcome ? '↑' : '↓',
    };
}

function KpiDeltaLine({
    delta,
    polarity = 'higher_is_better',
    comparisonLabel = 'periodo precedente',
}: {
    delta: number;
    polarity?: FormulaDeltaPolarity;
    comparisonLabel?: string;
}) {
    const { colorClass, arrow } = resolveKpiDeltaDisplay(delta, polarity);
    const percentLabel = `${delta >= 0 ? '+' : ''}${delta.toFixed(1)}% vs ${comparisonLabel}`;

    return (
        <p className={clsx('mt-1 flex items-center text-sm font-medium', colorClass)}>
            <span className="mr-1" aria-hidden="true">
                {arrow}
            </span>
            <span>{percentLabel}</span>
        </p>
    );
}

function KpiView({ payload, embedded }: { payload: Extract<FormulaWidgetPayload, { type: 'kpi' }>; embedded: boolean }) {
    if (payload.variant === 'balance_summary') {
        return <BalanceSummaryView payload={{ ...payload, variant: 'balance_summary' }} />;
    }

    if (embedded) {
        return (
            <div className="flex h-full min-h-[5.5rem] flex-col justify-center">
                <p className={clsx('text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl', moneyTabular)}>
                    {formatValue(payload.value, payload.format)}
                </p>
                {payload.delta !== null && (
                    <KpiDeltaLine
                        delta={payload.delta}
                        polarity={payload.deltaPolarity}
                        comparisonLabel={payload.deltaComparisonLabel ?? 'periodo precedente'}
                    />
                )}
            </div>
        );
    }

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
            <h3 className="font-semibold text-gray-900 dark:text-white">{payload.name}</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{payload.periodLabel}</p>
            <p className={clsx('mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl', moneyTabular)}>
                {formatValue(payload.value, payload.format)}
            </p>
            {payload.delta !== null && (
                <KpiDeltaLine
                    delta={payload.delta}
                    polarity={payload.deltaPolarity}
                    comparisonLabel={payload.deltaComparisonLabel ?? 'periodo precedente'}
                />
            )}
        </div>
    );
}

function ProgressView({
    payload,
    embedded,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'progress' }>;
    embedded: boolean;
}) {
    if (embedded) {
        return (
            <div className="flex h-full min-h-[5.5rem] flex-col justify-center">
                <p className={clsx('text-2xl font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                    {formatCurrency(payload.value)}
                    <span className="ml-2 text-base font-normal text-gray-500 dark:text-gray-400">
                        / {formatCurrency(payload.threshold)}
                    </span>
                </p>
                <div className="mt-4">
                    <ProgressBar percentage={Math.min(100, payload.percentage)} />
                </div>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.percentage.toLocaleString('it-IT', { maximumFractionDigits: 1 })}% della soglia
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
            <h3 className="font-semibold text-gray-900 dark:text-white">{payload.name}</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{payload.periodLabel}</p>
            <p className={clsx('mt-2 text-2xl font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                {formatCurrency(payload.value)}
                <span className="ml-2 text-base font-normal text-gray-500 dark:text-gray-400">
                    / {formatCurrency(payload.threshold)}
                </span>
            </p>
            <div className="mt-4">
                <ProgressBar percentage={Math.min(100, payload.percentage)} />
            </div>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {payload.percentage.toLocaleString('it-IT', { maximumFractionDigits: 1 })}% della soglia
            </p>
        </div>
    );
}

function LineAreaView({
    payload,
    embedded,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'line' | 'area' }>;
    embedded: boolean;
}) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();
    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;
    const data = payload.points.map((point) => ({ label: point.label, Valore: point.value }));
    const lastValue = data.length ? data[data.length - 1].Valore : null;
    const Chart = payload.type === 'area' ? AreaChart : LineChart;

    if (!data.length) {
        return (
            <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                Nessun dato disponibile per il periodo selezionato
            </div>
        );
    }

    return (
        <div className="w-full">
            {lastValue !== null && (
                <p className={clsx('mb-2 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                    {formatCurrency(lastValue)}
                </p>
            )}
            {!embedded && (
                <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.name} · {payload.periodLabel}
                </p>
            )}
            <ResponsiveContainer width="100%" height={chartHeight}>
                <Chart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#374151' : '#e5e7eb'} />
                    <XAxis
                        dataKey="label"
                        tick={{ fontSize: isCompact ? 10 : 12, fill: getChartMutedTextColor(isDark) }}
                        angle={isCompact ? 0 : -35}
                        textAnchor={isCompact ? 'middle' : 'end'}
                        height={isCompact ? 28 : 54}
                    />
                    <YAxis
                        tickFormatter={yAxisFormatter}
                        width={isCompact ? 48 : 68}
                        tick={{ fontSize: 11, fill: getChartMutedTextColor(isDark) }}
                    />
                    <Tooltip content={<ChartTooltip />} />
                    {payload.type === 'area' ? (
                        <Area type="monotone" dataKey="Valore" stroke="#3b82f6" fill="#3b82f6" fillOpacity={0.2} />
                    ) : (
                        <Line type="monotone" dataKey="Valore" stroke="#3b82f6" strokeWidth={2} dot={false} />
                    )}
                </Chart>
            </ResponsiveContainer>
        </div>
    );
}

type CategoryChartPayload = Extract<FormulaWidgetPayload, { type: 'bar' | 'horizontal_bar' | 'pie' | 'treemap' }>;

function CategoryEmptyState() {
    return (
        <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
            Nessun dato disponibile per il periodo selezionato
        </div>
    );
}

function BarView({
    payload,
    embedded,
    horizontal = false,
}: {
    payload: CategoryChartPayload;
    embedded: boolean;
    horizontal?: boolean;
}) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();
    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;
    const data = payload.categories.map((cat) => ({
        label: cat.label,
        value: cat.value,
        fill: cat.color ?? DEFAULT_COLORS[0],
    }));

    if (!data.length) {
        return <CategoryEmptyState />;
    }

    return (
        <div className="w-full">
            {!embedded && (
                <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.name} · {payload.periodLabel}
                </p>
            )}
            <ResponsiveContainer width="100%" height={chartHeight}>
                <BarChart
                    data={data}
                    layout={horizontal ? 'vertical' : 'horizontal'}
                    margin={{ top: 8, right: 8, left: horizontal ? 8 : 0, bottom: 0 }}
                >
                    <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#374151' : '#e5e7eb'} />
                    {horizontal ? (
                        <>
                            <XAxis type="number" tickFormatter={yAxisFormatter} tick={{ fontSize: 11, fill: getChartMutedTextColor(isDark) }} />
                            <YAxis
                                type="category"
                                dataKey="label"
                                width={isCompact ? 72 : 96}
                                tick={{ fontSize: isCompact ? 10 : 12, fill: getChartMutedTextColor(isDark) }}
                            />
                        </>
                    ) : (
                        <>
                            <XAxis
                                dataKey="label"
                                tick={{ fontSize: isCompact ? 10 : 12, fill: getChartMutedTextColor(isDark) }}
                            />
                            <YAxis
                                tickFormatter={yAxisFormatter}
                                width={isCompact ? 48 : 68}
                                tick={{ fontSize: 11, fill: getChartMutedTextColor(isDark) }}
                            />
                        </>
                    )}
                    <Tooltip content={<ChartTooltip />} />
                    <Bar dataKey="value" name="Valore" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function PieView({ payload, embedded }: { payload: CategoryChartPayload; embedded: boolean }) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();
    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;
    const data = payload.categories.map((cat, index) => ({
        name: cat.label,
        value: cat.value,
        fill: cat.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length],
    }));

    if (!data.length || data.every((entry) => entry.value <= 0)) {
        return <CategoryEmptyState />;
    }

    return (
        <div className="w-full">
            {!embedded && (
                <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.name} · {payload.periodLabel}
                </p>
            )}
            <ResponsiveContainer width="100%" height={chartHeight}>
                <PieChart>
                    <Pie
                        data={data}
                        dataKey="value"
                        nameKey="name"
                        cx="50%"
                        cy="50%"
                        outerRadius={isCompact ? 72 : 96}
                        label={({ name, percent }) =>
                            `${name ?? ''} ${((percent ?? 0) * 100).toFixed(0)}%`
                        }
                    >
                        {data.map((entry) => (
                            <Cell key={entry.name} fill={entry.fill} />
                        ))}
                    </Pie>
                    <Tooltip
                        formatter={(value) => formatEuro(Number(value ?? 0))}
                        contentStyle={getChartTooltipStyle(isDark)}
                    />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}

function FormulaTreemapContent({
    x,
    y,
    width,
    height,
    name,
    percentage,
    fill,
}: {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    name?: string;
    percentage?: number;
    fill?: string;
}) {
    const safeX = x ?? 0;
    const safeY = y ?? 0;
    const safeW = width ?? 0;
    const safeH = height ?? 0;

    if (safeW < 40 || safeH < 32) {
        return <g><rect x={safeX} y={safeY} width={safeW} height={safeH} fill={fill} rx={4} /></g>;
    }

    return (
        <g>
            <rect x={safeX} y={safeY} width={safeW} height={safeH} fill={fill} rx={4} />
            <foreignObject x={safeX + 6} y={safeY + 6} width={safeW - 12} height={safeH - 12}>
                <div className="flex h-full flex-col justify-center overflow-hidden text-white">
                    <span className="truncate text-xs font-semibold">{name}</span>
                    {safeH > 48 && percentage !== undefined && (
                        <span className="text-[10px] text-white/85">{percentage.toFixed(1)}%</span>
                    )}
                </div>
            </foreignObject>
        </g>
    );
}

function TreemapView({ payload, embedded }: { payload: CategoryChartPayload; embedded: boolean }) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();
    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;
    const data = payload.categories.map((cat, index) => ({
        name: cat.label,
        value: cat.value,
        percentage: cat.percentage ?? 0,
        fill: cat.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length],
    }));

    if (!data.length || data.every((entry) => entry.value <= 0)) {
        return <CategoryEmptyState />;
    }

    return (
        <div className="w-full">
            {!embedded && (
                <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.name} · {payload.periodLabel}
                </p>
            )}
            <ResponsiveContainer width="100%" height={chartHeight}>
                <Treemap
                    data={data}
                    dataKey="value"
                    nameKey="name"
                    stroke={isDark ? '#1f2937' : '#fff'}
                    content={<FormulaTreemapContent />}
                >
                    <Tooltip
                        content={({ active, payload: tooltipPayload }) => {
                            if (!active || !tooltipPayload?.length) return null;
                            const item = tooltipPayload[0].payload as (typeof data)[number];

                            return (
                                <div style={getChartTooltipStyle(isDark)}>
                                    <p className="font-semibold">{item.name}</p>
                                    <p>{formatEuro(item.value)}</p>
                                    <p className="text-xs text-gray-500">{item.percentage.toFixed(1)}% del totale</p>
                                </div>
                            );
                        }}
                    />
                </Treemap>
            </ResponsiveContainer>
        </div>
    );
}

function StackedBarView({
    payload,
    embedded,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'stacked_bar' }>;
    embedded: boolean;
}) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();
    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;

    const seriesMeta = payload.series ?? [];
    const data = payload.points.map((point) => ({
        label: point.label,
        ...point.series,
    }));

    if (!data.length) {
        return (
            <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                Nessun dato disponibile per il periodo selezionato
            </div>
        );
    }

    return (
        <div className="w-full">
            {!embedded && (
                <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.name} · {payload.periodLabel}
                </p>
            )}
            <div className="mb-2 flex flex-wrap gap-3">
                {seriesMeta.map((entry, index) => (
                    <div key={entry.code} className="flex items-center gap-1.5">
                        <span
                            className="h-2 w-2 rounded-full"
                            style={{ backgroundColor: entry.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length] }}
                        />
                        <span className="text-xs text-gray-600 dark:text-gray-400">{entry.label ?? entry.code}</span>
                    </div>
                ))}
            </div>
            <ResponsiveContainer width="100%" height={chartHeight}>
                <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#374151' : '#e5e7eb'} />
                    <XAxis
                        dataKey="label"
                        tick={{ fontSize: isCompact ? 10 : 12, fill: getChartMutedTextColor(isDark) }}
                        angle={isCompact ? 0 : -35}
                        textAnchor={isCompact ? 'middle' : 'end'}
                        height={isCompact ? 28 : 54}
                    />
                    <YAxis
                        tickFormatter={yAxisFormatter}
                        width={isCompact ? 48 : 68}
                        tick={{ fontSize: 11, fill: getChartMutedTextColor(isDark) }}
                    />
                    <Tooltip content={<ChartTooltip />} />
                    {seriesMeta.map((entry, index) => (
                        <Bar
                            key={entry.code}
                            dataKey={entry.code}
                            name={entry.label ?? entry.code}
                            stackId="stack"
                            fill={entry.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length]}
                        />
                    ))}
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

export default function CustomFormulaWidget({ payload, embedded = true, className }: CustomFormulaWidgetProps) {
    return (
        <div className={clsx('h-full w-full', className)}>
            {payload.type === 'kpi' && <KpiView payload={payload} embedded={embedded} />}
            {payload.type === 'progress' && <ProgressView payload={payload} embedded={embedded} />}
            {(payload.type === 'line' || payload.type === 'area') && (
                <LineAreaView payload={payload} embedded={embedded} />
            )}
            {payload.type === 'bar' && <BarView payload={payload} embedded={embedded} />}
            {payload.type === 'horizontal_bar' && <BarView payload={payload} embedded={embedded} horizontal />}
            {payload.type === 'pie' && <PieView payload={payload} embedded={embedded} />}
            {payload.type === 'treemap' && <TreemapView payload={payload} embedded={embedded} />}
            {payload.type === 'stacked_bar' && <StackedBarView payload={payload} embedded={embedded} />}
        </div>
    );
}
