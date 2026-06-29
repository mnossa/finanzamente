import MetricFilterRow from '@/Components/FormulaWidgets/MetricFilterRow';
import InputLabel from '@/Components/InputLabel';
import type { MetricQueryDefinition } from '@/types/formulaWidget';
import {
    MEASURE_LABELS,
    createEmptyMetricQuery,
    removeMetricFilter,
    upsertMetricFilter,
    type MetricQueryConfig,
} from '@/utils/metricQueryForm';

interface MetricQueryBuilderProps {
    value?: MetricQueryDefinition;
    config: MetricQueryConfig;
    onChange: (value: MetricQueryDefinition | undefined) => void;
}

export default function MetricQueryBuilder({ value, config, onChange }: MetricQueryBuilderProps) {
    const metricQuery = value ?? createEmptyMetricQuery();
    const datasourceMeta = config.datasources[metricQuery.datasource];

    const setMetricQuery = (patch: Partial<MetricQueryDefinition>) => {
        onChange({ ...metricQuery, ...patch });
    };

    const addFilter = () => {
        const field = datasourceMeta?.filter_fields[0] ?? 'tag';
        setMetricQuery({
            filters: upsertMetricFilter(metricQuery.filters ?? [], {
                field,
                operator: 'in',
                runtime_key: `${field}_selected`,
            }),
        });
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel value="Sorgente dati" />
                    <select
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                        value={metricQuery.datasource}
                        onChange={(e) =>
                            onChange(createEmptyMetricQuery(e.target.value as 'transactions' | 'debts_credits'))
                        }
                    >
                        {Object.entries(config.datasources).map(([key, meta]) => (
                            <option key={key} value={key}>
                                {meta.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <InputLabel value="Misura" />
                    <select
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                        value={metricQuery.measure}
                        onChange={(e) => setMetricQuery({ measure: e.target.value })}
                    >
                        {(datasourceMeta?.measures ?? []).map((measure) => (
                            <option key={measure} value={measure}>
                                {MEASURE_LABELS[measure] ?? measure}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {metricQuery.datasource === 'transactions' && (
                <div>
                    <InputLabel value="Campo importo" />
                    <select
                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                        value={metricQuery.amount_field ?? config.default_amount_field}
                        onChange={(e) =>
                            setMetricQuery({ amount_field: e.target.value as 'amount_base' | 'amount' })
                        }
                    >
                        {config.amount_fields.map((field) => (
                            <option key={field} value={field}>
                                {field === 'amount_base'
                                    ? 'EUR normalizzato (consigliato)'
                                    : 'Valuta del conto'}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Usa EUR normalizzato per confronti cross-conto; la valuta del conto solo con filtri coerenti.
                    </p>
                </div>
            )}

            <div className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <InputLabel value="Filtri" />
                    <button
                        type="button"
                        className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                        onClick={addFilter}
                    >
                        + Aggiungi filtro
                    </button>
                </div>

                {(metricQuery.filters ?? []).map((filter, index) => (
                    <MetricFilterRow
                        key={`${filter.field}-${filter.operator}-${index}`}
                        filter={filter}
                        availableFields={datasourceMeta?.filter_fields ?? []}
                        availableOperators={config.operators}
                        onChange={(nextFilter) =>
                            setMetricQuery({
                                filters: (metricQuery.filters ?? []).map((entry, entryIndex) =>
                                    entryIndex === index ? nextFilter : entry,
                                ),
                            })
                        }
                        onRemove={() =>
                            setMetricQuery({
                                filters: removeMetricFilter(metricQuery.filters ?? [], filter.field, filter.operator),
                            })
                        }
                    />
                ))}
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={value !== undefined}
                    onChange={(e) => onChange(e.target.checked ? metricQuery : undefined)}
                />
                Usa query dinamica (al posto della sola formula collegata)
            </label>
        </div>
    );
}
