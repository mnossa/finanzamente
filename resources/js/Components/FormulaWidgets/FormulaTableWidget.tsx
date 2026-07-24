import clsx from 'clsx';
import type { FormulaWidgetTablePayload } from '@/types/formulaWidget';

interface FormulaTableWidgetProps {
    payload: FormulaWidgetTablePayload;
    embedded?: boolean;
    className?: string;
}

/** Colonne accessorie: su viewport stretti restano nascoste così date/desc/importo restano leggibili. */
const SECONDARY_COLUMN_KEYS = new Set([
    'category',
    'account',
    'status',
    'type',
    'currency',
    'frequency',
    'due_date',
    'start_date',
]);

function isMoneyColumn(key: string): boolean {
    return key === 'amount' || key === 'value' || key === 'remaining';
}

function columnWidthClass(key: string): string {
    if (key === 'date' || key === 'due_date' || key === 'start_date') {
        return 'w-[5.25rem]';
    }

    if (isMoneyColumn(key)) {
        return 'w-[5.5rem] sm:w-[6.5rem]';
    }

    if (SECONDARY_COLUMN_KEYS.has(key)) {
        return 'w-[5.5rem]';
    }

    // description / label / asset / counterparty: restano flessibili con table-fixed
    return '';
}

function columnVisibilityClass(key: string): string {
    return SECONDARY_COLUMN_KEYS.has(key) ? 'hidden sm:table-cell' : '';
}

function formatCell(key: string, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'number' && isMoneyColumn(key)) {
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
        <div className={clsx('flex h-full min-h-0 w-full min-w-0 flex-col', className)}>
            {!embedded ? (
                <div className="mb-2 flex min-w-0 items-baseline justify-between gap-2">
                    <h3 className="truncate text-sm font-semibold text-gray-900 dark:text-white">{payload.name}</h3>
                    <span className="shrink-0 text-xs text-gray-500 dark:text-gray-400">{payload.periodLabel}</span>
                </div>
            ) : null}

            <div className="min-h-0 min-w-0 flex-1 overflow-x-auto overflow-y-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                {rows.length === 0 ? (
                    <p className="px-1 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Nessun dato per i filtri selezionati.
                    </p>
                ) : (
                    <table className="w-full min-w-0 table-fixed border-collapse text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        className={clsx(
                                            'truncate px-1 py-2 font-medium',
                                            columnWidthClass(column.key),
                                            columnVisibilityClass(column.key),
                                            isMoneyColumn(column.key) && 'text-right',
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
                                        const money = isMoneyColumn(column.key);
                                        const negative = typeof raw === 'number' && raw < 0;

                                        return (
                                            <td
                                                key={column.key}
                                                className={clsx(
                                                    'truncate px-1 py-2 text-gray-800 dark:text-gray-100',
                                                    columnWidthClass(column.key),
                                                    columnVisibilityClass(column.key),
                                                    money && 'text-right tabular-nums',
                                                    money && negative && 'text-rose-600 dark:text-rose-400',
                                                    money && !negative && typeof raw === 'number' && raw > 0
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
