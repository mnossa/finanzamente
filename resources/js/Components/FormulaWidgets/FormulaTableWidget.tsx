import clsx from 'clsx';
import { dashboardWidgetListBodyClass } from '@/Components/Dashboard/DashboardWidgetShell';
import type { FormulaWidgetTablePayload } from '@/types/formulaWidget';

interface FormulaTableWidgetProps {
    payload: FormulaWidgetTablePayload;
    embedded?: boolean;
    className?: string;
}

function formatCell(key: string, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'number' && (key === 'amount' || key === 'remaining' || key === 'value')) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(value);
    }

    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
        const [y, m, d] = value.slice(0, 10).split('-');
        return `${d}/${m}/${y}`;
    }

    return String(value);
}

export default function FormulaTableWidget({
    payload,
    embedded = true,
    className,
}: FormulaTableWidgetProps) {
    const isAggregate = payload.mode === 'aggregate';
    const rows: Array<Record<string, unknown>> = isAggregate
        ? payload.groups.map((group) => ({
            id: group.key,
            label: group.label,
            value: group.value,
        }))
        : payload.rows;

    const columns = payload.columns;

    return (
        <div className={clsx('flex h-full min-h-0 flex-col', className)}>
            {!embedded ? (
                <div className="mb-2 flex items-baseline justify-between gap-2">
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-white">{payload.name}</h3>
                    <span className="text-xs text-gray-500 dark:text-gray-400">{payload.periodLabel}</span>
                </div>
            ) : null}

            <div className={clsx(dashboardWidgetListBodyClass, 'min-h-0 flex-1 overflow-auto')}>
                {rows.length === 0 ? (
                    <p className="px-1 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Nessun dato per i filtri selezionati.
                    </p>
                ) : (
                    <table className="w-full min-w-0 border-collapse text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        className={clsx(
                                            'px-1 py-2 font-medium',
                                            (column.key === 'amount' || column.key === 'value' || column.key === 'remaining')
                                                && 'text-right',
                                        )}
                                    >
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr
                                    key={String(row.id ?? index)}
                                    className="border-b border-gray-100 last:border-0 dark:border-gray-800"
                                >
                                    {columns.map((column) => {
                                        const raw = row[column.key];
                                        const isMoney = column.key === 'amount' || column.key === 'value' || column.key === 'remaining';
                                        const negative = typeof raw === 'number' && raw < 0;

                                        return (
                                            <td
                                                key={column.key}
                                                className={clsx(
                                                    'max-w-[10rem] truncate px-1 py-2 text-gray-800 dark:text-gray-100',
                                                    isMoney && 'text-right tabular-nums',
                                                    isMoney && negative && 'text-rose-600 dark:text-rose-400',
                                                    isMoney && !negative && typeof raw === 'number' && raw > 0
                                                        && 'text-emerald-700 dark:text-emerald-400',
                                                )}
                                            >
                                                {formatCell(column.key, raw)}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
