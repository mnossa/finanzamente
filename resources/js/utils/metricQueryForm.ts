import type { MetricQueryDefinition, MetricQueryFilter, FormulaWidgetParameterDefinition } from '@/types/formulaWidget';

export interface MetricQueryConfig {
    datasources: Record<string, {
        label: string;
        measures: string[];
        filter_fields: string[];
        list_columns?: string[];
        group_by_fields?: string[];
        sort_fields?: string[];
        requires_period?: boolean;
    }>;
    operators: string[];
    amount_fields: string[];
    default_amount_field: string;
    default_row_limit?: number;
    max_row_limit?: number;
    transaction_types: Record<string, string>;
    runtime_parameter_types: Record<string, { label: string; all_value?: string }>;
    formula_functions: Record<string, string>;
}

export const FILTER_FIELD_LABELS: Record<string, string> = {
    tag: 'Tag',
    category: 'Categoria',
    currency: 'Valuta',
    account: 'Conto',
    debt_credit: 'Debito/Credito',
    transaction_type: 'Tipo transazione',
    tax_deductible: 'Detraibile',
    amount_min: 'Importo minimo',
    amount_max: 'Importo massimo',
    has_tag: 'Ha un tag',
    is_private: 'Privata',
    is_split: 'Suddivisa',
    type: 'Tipo',
    status: 'Stato',
    counterparty: 'Controparte',
    frequency: 'Frequenza',
    asset: 'Asset',
};

export const GROUP_BY_LABELS: Record<string, string> = {
    category: 'Categoria',
    account: 'Conto',
    tag: 'Tag',
    currency: 'Valuta',
    transaction_type: 'Tipo movimento',
    type: 'Tipo',
    status: 'Stato',
    frequency: 'Frequenza',
    asset: 'Asset',
};

export const MEASURE_LABELS: Record<string, string> = {
    count: 'Conteggio',
    sum: 'Somma',
    sum_abs: 'Somma (valore assoluto)',
    avg: 'Media',
    min: 'Minimo',
    max: 'Massimo',
    net: 'Saldo netto',
    sum_remaining: 'Residuo totale',
    sum_initial: 'Importo iniziale',
    sum_paid: 'Importo pagato',
};

export function createEmptyMetricQuery(
    datasource: 'transactions' | 'debts_credits' | 'investment_pacs' = 'transactions',
): MetricQueryDefinition {
    return {
        datasource,
        measure: datasource === 'transactions' ? 'sum_abs' : 'count',
        amount_field: 'amount_base',
        filters: [],
    };
}

export function upsertMetricFilter(
    filters: MetricQueryFilter[],
    filter: MetricQueryFilter,
): MetricQueryFilter[] {
    const index = filters.findIndex(
        (entry) => entry.field === filter.field && entry.operator === filter.operator,
    );

    if (index === -1) {
        return [...filters, filter];
    }

    return filters.map((entry, entryIndex) => (entryIndex === index ? filter : entry));
}

export function removeMetricFilter(
    filters: MetricQueryFilter[],
    field: string,
    operator?: string,
): MetricQueryFilter[] {
    return filters.filter((entry) => {
        if (entry.field !== field) {
            return true;
        }

        return operator !== undefined && entry.operator !== operator;
    });
}

/** Preset 1-tap sopra il builder (non sostituiscono METRIC_QUERY_PRESETS). */
export const QUICK_FILTER_PRESETS: Array<{
    id: string;
    title: string;
    filter: MetricQueryFilter;
}> = [
    {
        id: 'this_account',
        title: 'Questo conto',
        filter: { field: 'account', operator: 'in', runtime_key: 'account_selected' },
    },
    {
        id: 'hide_private',
        title: 'Nascondi privati',
        filter: { field: 'is_private', operator: 'eq', value: false },
    },
];

export function metricQueryHasFilter(
    metricQuery: MetricQueryDefinition | undefined,
    field: string,
    operator: string,
): boolean {
    return (metricQuery?.filters ?? []).some(
        (entry) => entry.field === field && entry.operator === operator,
    );
}

export function runtimeParamKeyForFilter(field: string, operator: string): string {
    const suffix = operator === 'not_in' || operator === 'neq' ? 'excluded' : 'selected';

    return `${field}_${suffix}`;
}

export function defaultRuntimeParameterForFilter(
    field: string,
    operator: string,
): FormulaWidgetParameterDefinition | null {
    const typeMap: Record<string, FormulaWidgetParameterDefinition['type']> = {
        tag: 'tag',
        category: 'category',
        currency: 'currency',
        account: 'account',
        debt_credit: 'debt_credit',
        transaction_type: 'transaction_type',
    };

    const type = typeMap[field];

    if (!type) {
        return null;
    }

    const key = runtimeParamKeyForFilter(field, operator);
    const isExclude = operator === 'not_in' || operator === 'neq';

    return {
        key,
        type,
        label: isExclude ? `Escludi ${FILTER_FIELD_LABELS[field] ?? field}` : (FILTER_FIELD_LABELS[field] ?? field),
        default: type === 'category' && isExclude ? 'none' : 'all',
    };
}

export function syncRuntimeParametersFromMetricQuery(
    metricQuery: MetricQueryDefinition | undefined,
    existing: FormulaWidgetParameterDefinition[] | undefined,
): FormulaWidgetParameterDefinition[] | undefined {
    if (!metricQuery?.filters?.length) {
        return existing;
    }

    let parameters = [...(existing ?? [])];

    for (const filter of metricQuery.filters) {
        if (!filter.runtime_key) {
            continue;
        }

        const param = defaultRuntimeParameterForFilter(filter.field, filter.operator);

        if (!param) {
            continue;
        }

        const nextParam = { ...param, key: filter.runtime_key };
        const index = parameters.findIndex((entry) => entry.key === nextParam.key);

        if (index === -1) {
            parameters.push(nextParam);
        } else {
            parameters[index] = { ...parameters[index], ...nextParam };
        }
    }

    return parameters.length > 0 ? parameters : undefined;
}

export const METRIC_QUERY_PRESETS: Array<{
    id: string;
    title: string;
    description: string;
    metricQuery: MetricQueryDefinition;
    displayType: string;
    format: string;
    suggestedName: string;
}> = [
    {
        id: 'tag_count',
        title: 'Conteggio per tag',
        description: 'Quante transazioni hanno un tag selezionabile in dashboard.',
        suggestedName: 'Transazioni per tag',
        displayType: 'kpi',
        format: 'number',
        metricQuery: {
            datasource: 'transactions',
            measure: 'count',
            amount_field: 'amount_base',
            filters: [{ field: 'tag', operator: 'in', runtime_key: 'tag_selected' }],
        },
    },
    {
        id: 'tag_sum_exclude_category',
        title: 'Spese per tag (escludi categoria)',
        description: 'Somma spese con tag, escludendo una categoria modificabile in dashboard.',
        suggestedName: 'Spese per tag',
        displayType: 'kpi',
        format: 'currency',
        metricQuery: {
            datasource: 'transactions',
            measure: 'sum_abs',
            amount_field: 'amount_base',
            filters: [
                { field: 'tag', operator: 'in', runtime_key: 'tag_selected' },
                { field: 'category', operator: 'not_in', runtime_key: 'category_excluded' },
                { field: 'transaction_type', operator: 'eq', value: 'expense' },
            ],
        },
    },
    {
        id: 'tax_deductible',
        title: 'Spese detraibili',
        description: 'Somma delle transazioni marcate come detraibili nel periodo.',
        suggestedName: 'Spese detraibili',
        displayType: 'kpi',
        format: 'currency',
        metricQuery: {
            datasource: 'transactions',
            measure: 'sum_abs',
            amount_field: 'amount_base',
            filters: [
                { field: 'tax_deductible', operator: 'eq', value: true },
                { field: 'transaction_type', operator: 'eq', value: 'expense' },
            ],
        },
    },
];
