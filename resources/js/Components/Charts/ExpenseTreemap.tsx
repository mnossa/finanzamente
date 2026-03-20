import React, { useState, useMemo } from 'react';
import {
    ResponsiveContainer,
    Treemap,
    Tooltip,
} from 'recharts';
import { categoryPalette, formatEuro, getChartTooltipStyle, useChartDarkMode } from './chartConfig';
import { Link } from '@inertiajs/react';
import CardBox from '@/Components/CardBox';

export interface ExpenseCategory {
    name: string;
    value: number;
    percentage: number;
    color: string | null;
    icon: string | null;
    category_id: number | null;
}

interface ExpenseTreemapProps {
    data: ExpenseCategory[];
    className?: string;
}

interface TooltipPayload {
    name: string;
    value: number;
    payload: ExpenseCategory & { fill: string };
}

function CustomTreemapContent({
    x, y, width, height, name, value, percentage, fill, icon,
}: {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    name?: string;
    value?: number;
    percentage?: number;
    fill?: string;
    icon?: string;
}) {
    const safeX = x ?? 0;
    const safeY = y ?? 0;
    const safeW = width ?? 0;
    const safeH = height ?? 0;

    if (safeW < 50 || safeH < 40) {
        return <g><rect x={safeX} y={safeY} width={safeW} height={safeH} fill={fill} rx={4} /></g>;
    }

    return (
        <g>
            <rect x={safeX} y={safeY} width={safeW} height={safeH} fill={fill} rx={4} />
            <foreignObject x={safeX + 6} y={safeY + 6} width={safeW - 12} height={safeH - 12}>
                <div
                    style={{ width: '100%', height: '100%', overflow: 'hidden', display: 'flex', flexDirection: 'column', justifyContent: 'center' }}
                >
                    {safeH > 60 && (
                        <span style={{ fontSize: '18px', lineHeight: 1 }}>{icon ?? '📁'}</span>
                    )}
                    <span style={{ fontSize: '11px', fontWeight: 600, color: '#fff', marginTop: '2px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {name}
                    </span>
                    {safeH > 55 && (
                        <span style={{ fontSize: '10px', color: 'rgba(255,255,255,0.85)' }}>
                            {percentage?.toFixed(1)}%
                        </span>
                    )}
                </div>
            </foreignObject>
        </g>
    );
}

function TreemapTooltip({ active, payload }: { active?: boolean; payload?: TooltipPayload[] }) {
    const isDark = useChartDarkMode();

    if (!active || !payload?.length) return null;
    const d = payload[0].payload;
    return (
        <div style={getChartTooltipStyle(isDark)}>
            <p className="font-semibold">
                {d.icon ?? '📁'} {d.name}
            </p>
            <p className="text-blue-500 font-bold">{formatEuro(d.value)}</p>
            <p className="text-gray-500 dark:text-gray-400 text-xs">{d.percentage?.toFixed(1)}% del totale</p>
        </div>
    );
}

export default function ExpenseTreemap({ data, className }: ExpenseTreemapProps) {
    const [selected, setSelected] = useState<ExpenseCategory | null>(null);

    const chartData = useMemo(
        () =>
            data.map((d, i) => ({
                ...d,
                fill: d.color ?? categoryPalette[i % categoryPalette.length],
            })),
        [data],
    );

    if (!data.length) {
        return (
            <CardBox className={className ?? ''}>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Distribuzione spese del periodo
                </p>
                <div className="flex h-48 items-center justify-center text-gray-400 dark:text-gray-600">
                    Nessuna spesa registrata nel periodo selezionato
                </div>
            </CardBox>
        );
    }

    return (
        <CardBox className={className ?? ''}>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Distribuzione spese del periodo · clicca su una categoria per i dettagli
            </p>

            <div className="mt-4">
                <ResponsiveContainer width="99%" height={288}>
                    <Treemap
                        data={chartData}
                        dataKey="value"
                        aspectRatio={4 / 3}
                        stroke="#fff"
                        content={
                            <CustomTreemapContent />
                        }
                        onClick={(node) => {
                            if (node && 'category_id' in node) {
                                setSelected(node as unknown as ExpenseCategory);
                            }
                        }}
                    >
                        <Tooltip content={<TreemapTooltip />} wrapperStyle={{ zIndex: 1000, outline: 'none' }} />
                    </Treemap>
                </ResponsiveContainer>
            </div>

            {/* Drill-down: dettaglio categoria selezionata */}
            {selected && (
                <div className="mt-4 rounded-lg border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700/50">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="font-semibold text-gray-900 dark:text-white">
                            {selected.icon ?? '📁'} {selected.name}
                        </p>
                        <button
                            onClick={() => setSelected(null)}
                            className="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            aria-label="Chiudi dettaglio"
                        >
                            ✕
                        </button>
                    </div>
                    <div className="flex items-center gap-4 text-sm">
                        <span className="font-bold text-gray-900 dark:text-white">{formatEuro(selected.value)}</span>
                        <span className="text-gray-500 dark:text-gray-400">({selected.percentage.toFixed(1)}% del totale)</span>
                        {selected.category_id && (
                            <Link
                                href={route('transactions.index', { category_id: selected.category_id })}
                                className="ml-auto text-emerald-500 hover:text-emerald-600"
                            >
                                Vedi transazioni →
                            </Link>
                        )}
                    </div>
                </div>
            )}

            {/* Lista categorie */}
            <div className="mt-4 space-y-2">
                {data.slice(0, 5).map((d, i) => (
                    <div
                        key={d.name}
                        className="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                        onClick={() => setSelected(d)}
                        role="button"
                        tabIndex={0}
                        onKeyDown={(e) => e.key === 'Enter' && setSelected(d)}
                        aria-label={`Seleziona categoria ${d.name}`}
                    >
                        <span
                            className="h-3 w-3 flex-shrink-0 rounded-full"
                            style={{ backgroundColor: d.color ?? categoryPalette[i % categoryPalette.length] }}
                        />
                        <span className="flex-1 truncate text-sm text-gray-700 dark:text-gray-300">{d.icon ?? '📁'} {d.name}</span>
                        <span className="text-sm font-semibold text-gray-900 dark:text-white">{formatEuro(d.value)}</span>
                        <span className="w-10 text-right text-xs text-gray-400">{d.percentage.toFixed(1)}%</span>
                    </div>
                ))}
            </div>
        </CardBox>
    );
}
