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
import { formatEuro, getChartMutedTextColor, getChartTooltipStyle, useChartDarkMode } from './chartConfig';

const CHART_HEIGHT = 256;

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
    'Patrimonio Netto': number;
}

interface NetWorthChartProps {
    data: NetWorthDataPoint[];
    className?: string;
}

/** Formatter compatto per i tick dell'asse Y */
const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `€${(value / 1_000).toFixed(0)}k`;

    return `€${value.toFixed(0)}`;
};

export default function NetWorthChart({ data, className }: NetWorthChartProps) {
    const isDark = useChartDarkMode();

    const lastValue = data.length ? data[data.length - 1]['Patrimonio Netto'] : null;
    const firstValue = data.length ? data[0]['Patrimonio Netto'] : null;
    const growth =
        firstValue !== null && lastValue !== null && firstValue !== 0
            ? (((lastValue - firstValue) / Math.abs(firstValue)) * 100).toFixed(1)
            : null;

    if (!data.length) {
        return (
            <div className={clsx('w-full', className)}>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Andamento nel tempo
                </p>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile per il periodo selezionato
                </div>
            </div>
        );
    }

    return (
        <div className={clsx('w-full', className)}>
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Andamento nel tempo
                    </p>
                </div>
                {lastValue !== null && (
                    <div className="text-right">
                        <p className="text-xl font-bold text-gray-900 dark:text-white">
                            {formatEuro(lastValue)}
                        </p>
                        {growth !== null && (
                            <p
                                className={
                                    parseFloat(growth) >= 0
                                        ? 'text-sm text-emerald-500'
                                        : 'text-sm text-red-500'
                                }
                            >
                                {parseFloat(growth) >= 0 ? '↑' : '↓'} {Math.abs(parseFloat(growth))}%
                            </p>
                        )}
                    </div>
                )}
            </div>
            <div className="mt-3 sm:mt-4">
                <ResponsiveContainer width="99%" height={CHART_HEIGHT}>
                    <ReAreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 28 }}>
                        <defs>
                            <linearGradient id="netWorthGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#60a5fa" stopOpacity={isDark ? 0.45 : 0.30} />
                                <stop offset="95%" stopColor="#60a5fa" stopOpacity={isDark ? 0.06 : 0.02} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#334155' : '#e2e8f0'} />
                        <XAxis
                            dataKey="month"
                            angle={-35}
                            textAnchor="end"
                            height={54}
                            tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 12 }}
                        />
                        <YAxis
                            width={68}
                            tickFormatter={yAxisFormatter}
                            tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 12 }}
                        />
                        <Tooltip content={<NetWorthTooltip />} />
                        <Area
                            type="monotone"
                            dataKey="Patrimonio Netto"
                            stroke="#60a5fa"
                            strokeWidth={3}
                            fill="url(#netWorthGradient)"
                            dot={false}
                            activeDot={{ r: 5, fill: '#60a5fa', stroke: isDark ? '#0f172a' : '#ffffff', strokeWidth: 2 }}
                        />
                    </ReAreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
