import React from 'react';
import { AreaChart, Card } from '@tremor/react';
import { formatEuro } from './chartConfig';

export interface NetWorthDataPoint {
    month: string;
    'Patrimonio Netto': number;
}

interface NetWorthChartProps {
    data: NetWorthDataPoint[];
    className?: string;
}

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
            />
        </Card>
    );
}
