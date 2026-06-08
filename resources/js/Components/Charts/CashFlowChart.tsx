import React from 'react';
import {
    ResponsiveContainer,
    BarChart as ReBarChart,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    Bar,
} from 'recharts';
import clsx from 'clsx';
import { formatEuro, getChartMutedTextColor, getChartTooltipStyle, useChartDarkMode, useCompactChart } from './chartConfig';

const CHART_HEIGHT_DESKTOP = 256;
const CHART_HEIGHT_MOBILE = 200;

export interface CashFlowDataPoint {
    month: string;
    Entrate: number;
    Uscite: number;
    Risparmio: number;
}

interface CashFlowChartProps {
    data: CashFlowDataPoint[];
    className?: string;
    /** Dashboard widget: niente titolo/legenda duplicati. */
    embedded?: boolean;
}

/** Formatter compatto per i tick dell'asse Y (evita troncamenti) */
const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `€${(value / 1_000).toFixed(0)}k`;

    return `€${value.toFixed(0)}`;
};

const CATEGORY_COLORS: Record<string, string> = {
    Entrate: '#10b981',
    Uscite: '#f97316',
};

function CashFlowTooltip({ payload, active, label }: { payload?: Array<{ dataKey?: string; name?: string; value?: number }>; active?: boolean; label?: string }) {
    const isDark = useChartDarkMode();
    const tooltipStyle = getChartTooltipStyle(isDark);

    if (!active || !payload?.length) return null;

    return (
        <div style={{
            ...tooltipStyle,
            minWidth: '160px',
        }}>
            <p style={{ fontWeight: 600, marginBottom: '4px', color: getChartMutedTextColor(isDark) }}>{label ?? ''}</p>
            {payload.map((entry) => (
                <p key={String(entry.dataKey)} style={{ margin: '2px 0', color: CATEGORY_COLORS[String(entry.dataKey)] ?? '#64748b' }}>
                    {entry.name}: <strong>{formatEuro(Number(entry.value))}</strong>
                </p>
            ))}
        </div>
    );
}

function ChartLegend() {
    return (
        <div className="flex items-center gap-4">
            {[{ label: 'Entrate', color: '#10b981' }, { label: 'Uscite', color: '#f97316' }].map(({ label, color }) => (
                <div key={label} className="flex items-center gap-1.5">
                    <span className="h-2 w-2 flex-none rounded-full" style={{ backgroundColor: color }} />
                    <span className="text-xs text-gray-600 sm:text-sm dark:text-gray-400">{label}</span>
                </div>
            ))}
        </div>
    );
}

export default function CashFlowChart({ data, className, embedded = false }: CashFlowChartProps) {
    const isDark = useChartDarkMode();
    const isCompact = useCompactChart();

    const chartHeight = isCompact ? CHART_HEIGHT_MOBILE : CHART_HEIGHT_DESKTOP;
    const yAxisWidth = isCompact ? 48 : 68;
    const xAxisAngle = isCompact ? 0 : -35;
    const xAxisHeight = isCompact ? 28 : 54;
    const xAxisFontSize = isCompact ? 10 : 12;

    const recentSavings = data.slice(-3);

    if (!data.length) {
        return (
            <div className={clsx('w-full', className)}>
                {!embedded && (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Entrate vs Uscite mensili
                    </p>
                )}
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile per il periodo selezionato
                </div>
            </div>
        );
    }

    return (
        <div className={clsx('w-full', className)}>
            {!embedded && (
                <>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Entrate vs Uscite mensili
                    </p>
                    <div className="mt-3">
                        <ChartLegend />
                    </div>
                </>
            )}
            {embedded ? (
                <div className="mb-2 sm:mb-3">
                    <ChartLegend />
                </div>
            ) : null}
            <div className={embedded ? 'mt-1' : 'mt-2'}>
                <ResponsiveContainer width="100%" height={chartHeight}>
                    <ReBarChart
                        data={data}
                        margin={{
                            top: 8,
                            right: isCompact ? 4 : 8,
                            left: isCompact ? -12 : 0,
                            bottom: isCompact ? 4 : 28,
                        }}
                    >
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
                        <Tooltip content={<CashFlowTooltip />} />
                        <Bar dataKey="Entrate" fill="#10b981" radius={[4, 4, 0, 0]} maxBarSize={isCompact ? 28 : 34} />
                        <Bar dataKey="Uscite" fill="#f97316" radius={[4, 4, 0, 0]} maxBarSize={isCompact ? 28 : 34} />
                    </ReBarChart>
                </ResponsiveContainer>
            </div>
            <div className={clsx(
                'border-t border-gray-100 pt-3 dark:border-gray-700',
                embedded ? 'mt-2' : 'mt-3',
            )}
            >
                <p className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Risparmio mensile</p>
                <div className={clsx(
                    'grid gap-2',
                    isCompact ? 'grid-cols-3' : 'flex flex-wrap gap-4',
                )}
                >
                    {recentSavings.map((d) => (
                        <div
                            key={d.month}
                            className={clsx(
                                'rounded-lg bg-gray-50 px-2 py-1.5 text-center dark:bg-gray-700/40',
                                !isCompact && 'min-w-[4.5rem]',
                            )}
                        >
                            <span className="block text-[11px] text-gray-500 dark:text-gray-400">{d.month}</span>
                            <span
                                className={clsx(
                                    'block text-xs font-semibold tabular-nums sm:text-sm',
                                    d.Risparmio >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-red-500',
                                )}
                            >
                                {d.Risparmio >= 0 ? '+' : ''}{formatEuro(d.Risparmio)}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
