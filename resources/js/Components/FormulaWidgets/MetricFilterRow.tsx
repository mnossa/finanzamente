import type { MetricQueryFilter } from '@/types/formulaWidget';
import InputLabel from '@/Components/InputLabel';
import { FILTER_FIELD_LABELS } from '@/utils/metricQueryForm';

interface MetricFilterRowProps {
    filter: MetricQueryFilter;
    onChange: (filter: MetricQueryFilter) => void;
    onRemove: () => void;
    availableFields: string[];
    availableOperators: string[];
}

const OPERATOR_LABELS: Record<string, string> = {
    in: 'include',
    not_in: 'escludi',
    eq: 'uguale a',
    neq: 'diverso da',
    gt: 'maggiore di',
    gte: 'maggiore o uguale',
    lt: 'minore di',
    lte: 'minore o uguale',
};

export default function MetricFilterRow({
    filter,
    onChange,
    onRemove,
    availableFields,
    availableOperators,
}: MetricFilterRowProps) {
    return (
        <div className="grid gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:grid-cols-[1fr_1fr_auto_auto]">
            <div>
                <InputLabel value="Campo" />
                <select
                    className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    value={filter.field}
                    onChange={(e) => onChange({ ...filter, field: e.target.value })}
                >
                    {availableFields.map((field) => (
                        <option key={field} value={field}>
                            {FILTER_FIELD_LABELS[field] ?? field}
                        </option>
                    ))}
                </select>
            </div>
            <div>
                <InputLabel value="Operatore" />
                <select
                    className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    value={filter.operator}
                    onChange={(e) => onChange({ ...filter, operator: e.target.value })}
                >
                    {availableOperators.map((operator) => (
                        <option key={operator} value={operator}>
                            {OPERATOR_LABELS[operator] ?? operator}
                        </option>
                    ))}
                </select>
            </div>
            <label className="flex items-end gap-2 pb-1 text-sm">
                <input
                    type="checkbox"
                    checked={Boolean(filter.runtime_key)}
                    onChange={(e) =>
                        onChange({
                            ...filter,
                            runtime_key: e.target.checked ? `${filter.field}_selected` : undefined,
                            value: e.target.checked ? undefined : filter.value,
                        })
                    }
                />
                Modificabile in dashboard
            </label>
            <div className="flex items-end justify-end">
                <button
                    type="button"
                    className="rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                    onClick={onRemove}
                >
                    Rimuovi
                </button>
            </div>
        </div>
    );
}
