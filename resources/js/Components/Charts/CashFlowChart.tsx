import React from 'react';
import {
    ResponsiveContainer,
    BarChart as ReBarChart,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    Bar,
    Legend,
} from 'recharts';
import CardBox from '@/Components/CardBox';
import { formatEuro, getChartMutedTextColor, getChartTooltipStyle, useChartDarkMode } from './chartConfig';

export interface CashFlowDataPoint {
    month: string;
    Entrate: number;
    Uscite: number;
    Risparmio: number;
}

interface CashFlowChartProps {
    data: CashFlowDataPoint[];
    className?: string;
}

/** Formatter compatto per i tick dell'asse Y (evita troncamenti) */
const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000)     return `€${(value / 1_000).toFixed(0)}k`;
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

export default function CashFlowChart({ data, className }: CashFlowChartProps) {
    const isDark = useChartDarkMode();

    if (!data.length) {
        return (
            <CardBox className={className ?? ''}>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Entrate vs Uscite mensili
                </p>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile per il periodo selezionato
                </div>
            </CardBox>
        );
    }

    return (
        <CardBox className={className ?? ''}>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Entrate vs Uscite mensili
            </p>
            {/* Legenda personalizzata con spaziatura adeguata */}
            <div className="mt-3 flex items-center gap-6">
                {[{ label: 'Entrate', color: '#10b981' }, { label: 'Uscite', color: '#f97316' }].map(({ label, color }) => (
                    <div key={label} className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 flex-none rounded-full" style={{ backgroundColor: color }} />
                        <span className="text-sm text-gray-600 dark:text-gray-400">{label}</span>
                    </div>
                ))}
            </div>
            <div className="mt-2">
                <ResponsiveContainer width="99%" height={288}>
                    <ReBarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 28 }}>
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
                        <Tooltip content={<CashFlowTooltip />} />
                        <Legend
                            wrapperStyle={{ color: isDark ? '#cbd5e1' : '#334155' }}
                            formatter={(value) => <span style={{ color: isDark ? '#cbd5e1' : '#334155' }}>{value}</span>}
                        />
                        <Bar dataKey="Entrate" fill="#10b981" radius={[4, 4, 0, 0]} maxBarSize={34} />
                        <Bar dataKey="Uscite" fill="#f97316" radius={[4, 4, 0, 0]} maxBarSize={34} />
                    </ReBarChart>
                </ResponsiveContainer>
            </div>
            {/* Linea risparmio netto sotto il grafico */}
            <div className="mt-3 flex flex-wrap gap-4 border-t border-gray-100 pt-3 dark:border-gray-700">
                {data.slice(-6).map((d) => (
                    <div key={d.month} className="flex flex-col items-center">
                        <span className="text-xs text-gray-500 dark:text-gray-400">{d.month}</span>
                        <span
                            className={
                                d.Risparmio >= 0
                                    ? 'text-xs font-semibold text-emerald-600 dark:text-emerald-400'
                                    : 'text-xs font-semibold text-red-500'
                            }
                        >
                            {d.Risparmio >= 0 ? '+' : ''}{formatEuro(d.Risparmio)}
                        </span>
                    </div>
                ))}
            </div>
        </CardBox>
    );
}
