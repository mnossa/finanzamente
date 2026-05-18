import React, { useState } from 'react';
import {
    ResponsiveContainer,
    LineChart,
    Line,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    Legend,
} from 'recharts';
import clsx from 'clsx';
import { CashFlowDataPoint } from './CashFlowChart';
import { formatEuro, getChartMutedTextColor, getChartTooltipStyle, useChartDarkMode } from './chartConfig';

const CHART_HEIGHT = 320;

type SeriesKey = 'Risparmio' | 'Entrate' | 'Uscite';

const SERIES_CONFIG: Record<SeriesKey, { color: string; label: string }> = {
    Risparmio: { color: '#3b82f6', label: 'Risparmio netto' },
    Entrate: { color: '#10b981', label: 'Entrate' },
    Uscite: { color: '#f97316', label: 'Uscite' },
};

function LineTooltip({ payload, active, label }: { payload?: Array<{ dataKey?: string; value?: number }>; active?: boolean; label?: string }) {
    const isDark = useChartDarkMode();
    const tooltipStyle = getChartTooltipStyle(isDark);

    if (!active || !payload?.length) return null;

    return (
        <div style={{ ...tooltipStyle, minWidth: '160px' }}>
            <p style={{ fontWeight: 600, marginBottom: '4px', color: getChartMutedTextColor(isDark) }}>{label ?? ''}</p>
            {payload.map((entry) => {
                const key = String(entry.dataKey) as SeriesKey;
                const cfg = SERIES_CONFIG[key];

                return (
                    <p key={key} style={{ margin: '2px 0', color: cfg?.color ?? '#64748b' }}>
                        {cfg?.label ?? key}: <strong>{formatEuro(Number(entry.value))}</strong>
                    </p>
                );
            })}
        </div>
    );
}

interface CashFlowLineChartProps {
    data: CashFlowDataPoint[];
    className?: string;
}

const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `€${(value / 1_000).toFixed(0)}k`;

    return `€${value.toFixed(0)}`;
};

export default function CashFlowLineChart({ data, className }: CashFlowLineChartProps) {
    const isDark = useChartDarkMode();
    const [activeSeries, setActiveSeries] = useState<SeriesKey>('Risparmio');

    if (!data.length) {
        return (
            <div className={clsx('w-full', className)}>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile
                </div>
            </div>
        );
    }

    return (
        <div className={clsx('w-full', className)}>
            <p className="text-sm text-gray-500 dark:text-gray-400">
                Andamento mensile — seleziona la serie da visualizzare
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                {(Object.keys(SERIES_CONFIG) as SeriesKey[]).map((key) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setActiveSeries(key)}
                        className={clsx(
                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                            activeSeries === key
                                ? 'bg-emerald-600 text-white'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        )}
                    >
                        {SERIES_CONFIG[key].label}
                    </button>
                ))}
            </div>
            <div className="mt-4">
                <ResponsiveContainer width="99%" height={CHART_HEIGHT}>
                    <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 28 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#334155' : '#e2e8f0'} />
                        <XAxis
                            dataKey="month"
                            angle={-35}
                            textAnchor="end"
                            height={54}
                            tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }}
                        />
                        <YAxis
                            width={68}
                            tickFormatter={yAxisFormatter}
                            tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 12 }}
                        />
                        <Tooltip content={<LineTooltip />} />
                        <Legend />
                        <Line
                            type="monotone"
                            dataKey={activeSeries}
                            stroke={SERIES_CONFIG[activeSeries].color}
                            strokeWidth={2.5}
                            dot={false}
                            activeDot={{ r: 4 }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
