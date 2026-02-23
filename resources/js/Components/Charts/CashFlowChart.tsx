import React from 'react';
import { BarChart, Card } from '@tremor/react';
import { chartColors, formatEuro } from './chartConfig';

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

const valueFormatter = (value: number) => formatEuro(value);

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
            <BarChart
                className="mt-4 h-72"
                data={data}
                index="month"
                categories={['Entrate', 'Uscite']}
                colors={['emerald', 'orange']}
                valueFormatter={valueFormatter}
                showLegend
                showGridLines
                showAnimation
                stack={false}
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
