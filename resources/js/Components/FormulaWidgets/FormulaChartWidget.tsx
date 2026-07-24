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
import clsx from 'clsx';
import { formatCurrency } from '@/utils/format';
import { moneyTabular } from '@/utils/moneyGridClasses';
import {
    formatEuro,
    getChartMutedTextColor,
    getChartTooltipStyle,
    useChartDarkMode,
    useCompactChart,
} from '@/Components/Charts/chartConfig';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';

const CHART_HEIGHT_DESKTOP = 256;
const CHART_HEIGHT_MOBILE = 200;

const DEFAULT_COLORS = ['#3b82f6', '#10b981', '#f97316', '#8b5cf6', '#ef4444'];

type ChartPayload = Exclude<FormulaWidgetPayload, { type: 'kpi' } | { type: 'progress' } | { type: 'table' }>;

interface FormulaChartWidgetProps {
    payload: ChartPayload;
    embedded?: boolean;
    className?: string;
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
    const data = payload.categories.map((cat, index) => ({
        label: cat.label,
        value: cat.value,
        fill: cat.color ?? DEFAULT_COLORS[index % DEFAULT_COLORS.length],
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
                    <Tooltip content={<ChartTooltip />} cursor={{ fill: isDark ? 'rgba(148,163,184,0.12)' : 'rgba(148,163,184,0.15)' }} />
                    <Bar
                        dataKey="value"
                        name="Valore"
                        radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
                    >
                        {data.map((entry) => (
                            <Cell key={entry.label} fill={entry.fill} />
                        ))}
                    </Bar>
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

export default function FormulaChartWidget({ payload, embedded = true, className }: FormulaChartWidgetProps) {
    return (
        <div className={clsx('h-full w-full', className)}>
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
