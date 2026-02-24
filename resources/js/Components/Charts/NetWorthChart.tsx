import React from 'react';
import { AreaChart, Card } from '@tremor/react';
import type { CustomTooltipProps } from '@tremor/react';
import { formatEuro } from './chartConfig';

function NetWorthTooltip({ payload, active, label }: CustomTooltipProps) {
    if (!active || !payload?.length) return null;
    const value = Number(payload[0]?.value ?? 0);
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
    if (abs >= 1_000)     return `€${(value / 1_000).toFixed(0)}k`;
    return `€${value.toFixed(0)}`;
};

const valueFormatter = (value: number) => formatEuro(value);

export default function NetWorthChart({ data, className }: NetWorthChartProps) {
    const lastValue = data.length ? data[data.length - 1]['Patrimonio Netto'] : null;
    const firstValue = data.length ? data[0]['Patrimonio Netto'] : null;
    const growth =
        firstValue !== null && lastValue !== null && firstValue !== 0
            ? (((lastValue - firstValue) / Math.abs(firstValue)) * 100).toFixed(1)
            : null;

    if (!data.length) {
        return (
            <Card className={className}>
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                    📈 Patrimonio Netto
                </h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Andamento nel tempo
                </p>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun dato disponibile per il periodo selezionato
                </div>
            </Card>
        );
    }

    return (
        <Card className={className}>
            <div className="flex items-start justify-between">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                        📈 Patrimonio Netto
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
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
            <AreaChart
                className="mt-4 h-72"
                data={data}
                index="month"
                categories={['Patrimonio Netto']}
                colors={['blue']}
                valueFormatter={valueFormatter}
                showLegend={false}
                showGridLines
                showAnimation
                curveType="monotone"
                yAxisWidth={65}
                rotateLabelX={{ angle: -45, verticalShift: 20, xAxisHeight: 60 }}
                valueFormatter={yAxisFormatter}
                customTooltip={NetWorthTooltip}
            />
        </Card>
    );
}
