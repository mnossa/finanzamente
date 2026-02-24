import React from 'react';
import { BarChart, Card } from '@tremor/react';
import type { CustomTooltipProps } from '@tremor/react';
import { formatEuro } from './chartConfig';

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

function CashFlowTooltip({ payload, active, label }: CustomTooltipProps) {
    if (!active || !payload?.length) return null;
    return (
        <div style={{
            backgroundColor: '#ffffff',
            border: '1px solid #e2e8f0',
            borderRadius: '8px',
            boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)',
            padding: '8px 12px',
            fontSize: '13px',
            color: '#1e293b',
            minWidth: '160px',
        }}>
            <p style={{ fontWeight: 600, marginBottom: '4px', color: '#475569' }}>{label}</p>
            {payload.map((entry) => (
                <p key={String(entry.dataKey)} style={{ margin: '2px 0', color: CATEGORY_COLORS[String(entry.dataKey)] ?? '#64748b' }}>
                    {entry.name}: <strong>{formatEuro(Number(entry.value))}</strong>
                </p>
            ))}
        </div>
    );
}

export default function CashFlowChart({ data, className }: CashFlowChartProps) {
    if (!data.length) {
        return (
            <Card className={className}>
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                    📊 Panoramica Cash Flow
                </h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Entrate vs Uscite mensili
                </p>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile per il periodo selezionato
                </div>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                📊 Panoramica Cash Flow
            </h3>
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
            <BarChart
                className="mt-2 h-72"
                data={data}
                index="month"
                categories={['Entrate', 'Uscite']}
                colors={['emerald', 'orange']}
                valueFormatter={yAxisFormatter}
                showLegend={false}
                showGridLines
                showAnimation
                stack={false}
                yAxisWidth={65}
                rotateLabelX={{ angle: -45, verticalShift: 20, xAxisHeight: 60 }}
                customTooltip={CashFlowTooltip}
            />
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
        </Card>
    );
}
