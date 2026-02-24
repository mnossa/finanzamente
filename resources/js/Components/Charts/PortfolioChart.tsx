import React from 'react';
import {
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
    Tooltip,
    Legend,
} from 'recharts';
import { categoryPalette, formatEuro, tooltipStyle } from './chartConfig';

export interface PortfolioItem {
    name: string;
    value: number;
    percentage: number;
}

interface PortfolioChartProps {
    data: PortfolioItem[];
    className?: string;
}

interface TooltipPayload {
    name: string;
    value: number;
    payload: PortfolioItem;
}

function PortfolioTooltip({ active, payload }: { active?: boolean; payload?: TooltipPayload[] }) {
    if (!active || !payload?.length) return null;
    const d = payload[0];
    return (
        <div style={tooltipStyle} className="dark:bg-slate-900 dark:border-slate-700 dark:text-white">
            <p className="font-semibold">{d.name}</p>
            <p className="text-blue-500 font-bold">{formatEuro(d.value)}</p>
            <p className="text-gray-500 dark:text-gray-400 text-xs">{d.payload.percentage.toFixed(1)}% del portafoglio</p>
        </div>
    );
}

function renderCustomLabel({ cx, cy, midAngle, innerRadius, outerRadius, percent }: {
    cx?: number; cy?: number; midAngle?: number;
    innerRadius?: number; outerRadius?: number; percent?: number;
}) {
    if (!percent || percent < 0.05) return null;
    const RADIAN = Math.PI / 180;
    const safeInner = innerRadius ?? 0;
    const safeOuter = outerRadius ?? 0;
    const safeMid = midAngle ?? 0;
    const safeCx = cx ?? 0;
    const safeCy = cy ?? 0;
    const radius = safeInner + (safeOuter - safeInner) * 0.5;
    const x = safeCx + radius * Math.cos(-safeMid * RADIAN);
    const y = safeCy + radius * Math.sin(-safeMid * RADIAN);
    return (
        <text x={x} y={y} fill="white" textAnchor="middle" dominantBaseline="central" fontSize={12} fontWeight={600}>
            {`${(percent * 100).toFixed(0)}%`}
        </text>
    );
}

export default function PortfolioChart({ data, className }: PortfolioChartProps) {
    const totalValue = data.reduce((sum, d) => sum + d.value, 0);

    if (!data.length) {
        return (
            <div className={`overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800 ${className ?? ''}`}>
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                    💼 Allocazione Portafoglio
                </h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Diversificazione degli investimenti
                </p>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessun investimento attivo trovato
                </div>
            </div>
        );
    }

    return (
        <div className={`overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800 ${className ?? ''}`}>
            <div className="flex items-start justify-between">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                        💼 Allocazione Portafoglio
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Diversificazione degli investimenti
                    </p>
                </div>
                <p className="text-xl font-bold text-gray-900 dark:text-white">
                    {formatEuro(totalValue)}
                </p>
            </div>

            <div className="mt-4 h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={data}
                            cx="50%"
                            cy="50%"
                            outerRadius={100}
                            dataKey="value"
                            labelLine={false}
                            label={renderCustomLabel}
                        >
                            {data.map((_, index) => (
                                <Cell
                                    key={`cell-${index}`}
                                    fill={categoryPalette[index % categoryPalette.length]}
                                />
                            ))}
                        </Pie>
                        <Tooltip content={<PortfolioTooltip />} wrapperStyle={{ zIndex: 1000, outline: 'none' }} />
                        <Legend
                            formatter={(value) => (
                                <span className="text-sm text-gray-700 dark:text-gray-300">{value}</span>
                            )}
                        />
                    </PieChart>
                </ResponsiveContainer>
            </div>

            {/* Lista allocazioni */}
            <div className="mt-4 space-y-2">
                {data.map((d, i) => (
                    <div key={d.name} className="flex items-center gap-3">
                        <span
                            className="h-3 w-3 flex-shrink-0 rounded-full"
                            style={{ backgroundColor: categoryPalette[i % categoryPalette.length] }}
                        />
                        <span className="flex-1 text-sm text-gray-700 dark:text-gray-300">{d.name}</span>
                        <span className="text-sm font-semibold text-gray-900 dark:text-white">{formatEuro(d.value)}</span>
                        <span className="w-12 text-right text-xs text-gray-400">{d.percentage.toFixed(1)}%</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
