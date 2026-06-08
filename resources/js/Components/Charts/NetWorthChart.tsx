import React from 'react';
import {
    ResponsiveContainer,
    AreaChart as ReAreaChart,
    Area,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
} from 'recharts';
import clsx from 'clsx';
import { formatEuro, getChartMutedTextColor, getChartTooltipStyle, useChartDarkMode, useCompactChart } from './chartConfig';

const CHART_HEIGHT_DESKTOP = 256;
const CHART_HEIGHT_MOBILE = 200;

function NetWorthTooltip({ payload, active, label }: { payload?: Array<{ value?: number }>; active?: boolean; label?: string }) {
    const isDark = useChartDarkMode();
    const tooltipStyle = getChartTooltipStyle(isDark);

    if (!active || !payload?.length) return null;
    const value = Number(payload[0]?.value ?? 0);
    return (
        <div style={{
            ...tooltipStyle,
            minWidth: '160px',
        }}>
            <p style={{ fontWeight: 600, marginBottom: '4px', color: getChartMutedTextColor(isDark) }}>{label ?? ''}</p>
            <p style={{ color: '#3b82f6', fontWeight: 700 }}>{formatEuro(value)}</p>
        </div>
    );
}

export interface NetWorthDataPoint {
    month: string;
    Patrimonio: number;
}

interface NetWorthChartProps {
    data: NetWorthDataPoint[];
    className?: string;
    title?: string;
    subtitle?: string;
    /** Dashboard widget: niente titolo duplicato, KPI compatto sopra al grafico. */
    embedded?: boolean;
}

/** Formatter compatto per i tick dell'asse Y */
const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `€${(value / 1_000).toFixed(0)}k`;

    return `€${value.toFixed(0)}`;
};

export default function NetWorthChart({
    data,
    className,
    title = 'Andamento nel tempo',
    subtitle,
    embedded = false,
}: NetWorthChartProps) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();

    const lastValue = data.length ? data[data.length - 1].Patrimonio : null;
    const firstValue = data.length ? data[0].Patrimonio : null;
    const growth =
        firstValue !== null && lastValue !== null && firstValue !== 0
            ? (((lastValue - firstValue) / Math.abs(firstValue)) * 100).toFixed(1)
            : null;

    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;
    const yAxisWidth = isCompact ? 48 : 68;
    const xAxisAngle = isCompact ? 0 : -35;
    const xAxisHeight = isCompact ? 28 : 54;
    const xAxisFontSize = isCompact ? 10 : 12;

    if (!data.length) {
        return (
            <div className={clsx('w-full', className)}>
                {!embedded && (
                    <p className="text-sm text-gray-500 dark:text-gray-400">{title}</p>
                )}
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile per il periodo selezionato
                </div>
            </div>
        );
    }

    const growthClassName =
        growth !== null && parseFloat(growth) >= 0
            ? 'text-sm font-medium text-emerald-500'
            : 'text-sm font-medium text-red-500';

    return (
        <div className={clsx('w-full', className)}>
            {lastValue !== null && (
                embedded ? (
                    <div className="mb-2 flex items-end justify-between gap-3 sm:mb-3">
                        <p className="text-xl font-bold tabular-nums tracking-tight text-gray-900 sm:text-2xl dark:text-white">
                            {formatEuro(lastValue)}
                        </p>
                        {growth !== null && (
                            <p className={clsx('shrink-0', growthClassName)}>
                                {parseFloat(growth) >= 0 ? '↑' : '↓'} {Math.abs(parseFloat(growth))}%
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-sm text-gray-500 dark:text-gray-400">{title}</p>
                            {subtitle && (
                                <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{subtitle}</p>
                            )}
                        </div>
                        <div className="shrink-0 text-right">
                            <p className="text-xl font-bold tabular-nums text-gray-900 dark:text-white">
                                {formatEuro(lastValue)}
                            </p>
                            {growth !== null && (
                                <p className={growthClassName}>
                                    {parseFloat(growth) >= 0 ? '↑' : '↓'} {Math.abs(parseFloat(growth))}%
                                </p>
                            )}
                        </div>
                    </div>
                )
            )}
            <div className={embedded ? 'mt-1 sm:mt-2' : 'mt-3 sm:mt-4'}>
                <ResponsiveContainer width="100%" height={chartHeight}>
                    <ReAreaChart
                        data={data}
                        margin={{
                            top: 8,
                            right: isCompact ? 4 : 8,
                            left: isCompact ? -12 : 0,
                            bottom: isCompact ? 4 : 28,
                        }}
                    >
                        <defs>
                            <linearGradient id="netWorthGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#60a5fa" stopOpacity={isDark ? 0.45 : 0.30} />
                                <stop offset="95%" stopColor="#60a5fa" stopOpacity={isDark ? 0.06 : 0.02} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#334155' : '#e2e8f0'} />
                        <XAxis
                            dataKey="month"
                            angle={xAxisAngle}
                            textAnchor={isCompact ? 'middle' : 'end'}
                            height={xAxisHeight}
                            interval={isCompact ? 'preserveStartEnd' : undefined}
                            tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: xAxisFontSize }}
                        />
                        <YAxis
                            width={yAxisWidth}
                            tickFormatter={yAxisFormatter}
                            tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: xAxisFontSize }}
                        />
                        <Tooltip content={<NetWorthTooltip />} />
                        <Area
                            type="monotone"
                            dataKey="Patrimonio"
                            stroke="#60a5fa"
                            strokeWidth={isCompact ? 2 : 3}
                            fill="url(#netWorthGradient)"
                            dot={false}
                            activeDot={{ r: isCompact ? 4 : 5, fill: '#60a5fa', stroke: isDark ? '#0f172a' : '#ffffff', strokeWidth: 2 }}
                        />
                    </ReAreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
