import type { MetricQueryFilter } from '@/types/formulaWidget';
import InputLabel from '@/Components/InputLabel';
import { FILTER_FIELD_LABELS } from '@/utils/metricQueryForm';

interface MetricFilterRowProps {
    filter: MetricQueryFilter;
    onChange: (filter: MetricQueryFilter) => void;
    onRemove: () => void;
    availableFields: string[];
    availableOperators: string[];
    transactionTypes?: Record<string, string>;
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

const BOOLEAN_FIELDS = ['tax_deductible', 'is_private', 'is_split', 'has_tag'];
const NUMBER_FIELDS = ['amount_min', 'amount_max'];

const VALUELESS_OPERATORS = ['is_null', 'is_not_null'];

const SELECT_CLASS =
    'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800';

function FixedValueControl({
    filter,
    onChange,
    transactionTypes,
}: {
    filter: MetricQueryFilter;
    onChange: (filter: MetricQueryFilter) => void;
    transactionTypes: Record<string, string>;
}) {
    if (VALUELESS_OPERATORS.includes(filter.operator)) {
        return null;
    }

    const stringValue = filter.value === undefined || filter.value === null ? '' : String(filter.value);

    if (filter.field === 'transaction_type') {
        return (
            <div>
                <InputLabel value="Valore" />
                <select
                    className={SELECT_CLASS}
                    value={stringValue}
                    onChange={(e) => onChange({ ...filter, value: e.target.value })}
                >
                    <option value="" disabled>
                        — seleziona —
                    </option>
                    {Object.entries(transactionTypes).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
        );
    }

    if (BOOLEAN_FIELDS.includes(filter.field)) {
        return (
            <div>
                <InputLabel value="Valore" />
                <select
                    className={SELECT_CLASS}
                    value={stringValue}
                    onChange={(e) => onChange({ ...filter, value: e.target.value === 'true' })}
                >
                    <option value="" disabled>
                        — seleziona —
                    </option>
                    <option value="true">Sì</option>
                    <option value="false">No</option>
                </select>
            </div>
        );
    }

    return (
        <div>
            <InputLabel value="Valore" />
            <input
                type={NUMBER_FIELDS.includes(filter.field) ? 'number' : 'text'}
                className={SELECT_CLASS}
                value={stringValue}
                onChange={(e) => onChange({ ...filter, value: e.target.value })}
            />
        </div>
    );
}

export default function MetricFilterRow({
    filter,
    onChange,
    onRemove,
    availableFields,
    availableOperators,
    transactionTypes = {},
}: MetricFilterRowProps) {
    const isRuntime = Boolean(filter.runtime_key);

    return (
        <div
            data-testid="metric-filter-row"
            className="grid gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:grid-cols-[1fr_1fr_1fr_auto_auto]"
        >
            <div>
                <InputLabel value="Campo" />
                <select
                    className={SELECT_CLASS}
                    value={filter.field}
                    onChange={(e) => onChange({ ...filter, field: e.target.value, value: undefined })}
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
                    className={SELECT_CLASS}
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
            {isRuntime ? (
                <div className="flex items-end pb-1 text-xs text-gray-500 dark:text-gray-400">
                    Valore scelto in dashboard
                </div>
            ) : (
                <FixedValueControl filter={filter} onChange={onChange} transactionTypes={transactionTypes} />
            )}
            <label className="flex items-end gap-2 pb-1 text-sm">
                <input
                    type="checkbox"
                    checked={isRuntime}
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
