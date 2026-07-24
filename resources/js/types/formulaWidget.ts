export type FormulaWidgetDisplayType =
    | 'kpi'
    | 'line'
    | 'area'
    | 'bar'
    | 'horizontal_bar'
    | 'stacked_bar'
    | 'pie'
    | 'treemap'
    | 'progress';

export interface FormulaWidgetCategorySlice {
    label: string;
    value: number;
    color?: string | null;
    percentage?: number;
}

export interface FormulaChartSeriesEntry {
    code: string;
    label?: string;
    color?: string;
}

export type FormulaWidgetParameterType =
    | 'account'
    | 'period_nav'
    | 'tag'
    | 'category'
    | 'currency'
    | 'debt_credit'
    | 'transaction_type'
    | 'number';

export interface FormulaWidgetParameterOption {
    value: string;
    label: string;
}

export interface FormulaWidgetParameterDefinition {
    key: string;
    type: FormulaWidgetParameterType;
    label: string;
    default?: string;
}

export interface FormulaWidgetRuntimeParameter extends FormulaWidgetParameterDefinition {
    value: string;
    options: FormulaWidgetParameterOption[];
    /** period_nav: etichetta del periodo correntemente selezionato (es. "Maggio 2026"). */
    display_label?: string;
    /** period_nav: offset minimo selezionabile (verso il passato, valore negativo). */
    min?: number;
    /** period_nav: offset massimo selezionabile (0 = periodo corrente). */
    max?: number;
}

export interface MetricQueryFilter {
    field: string;
    operator: string;
    value?: string | number | boolean | null;
    runtime_key?: string | null;
}

export interface MetricQueryDefinition {
    datasource: 'transactions' | 'debts_credits';
    measure: string;
    amount_field?: 'amount_base' | 'amount';
    filters?: MetricQueryFilter[];
}

export interface FormulaWidgetChartConfig {
    show_delta?: boolean;
    format?: string;
    value_code?: string;
    threshold_code?: string;
    /** Soglia numerica fissa (es. alert spese); override da parametro runtime `threshold`. */
    threshold_amount?: number;
    variant?: string;
    bands?: {
        warn?: number;
        danger?: number;
    };
    parameters?: FormulaWidgetParameterDefinition[];
    series?: FormulaChartSeriesEntry[];
    metric_query?: MetricQueryDefinition;
}

export type FormulaDeltaPolarity = 'higher_is_better' | 'lower_is_better';

export type FormulaTrafficLightStatus = 'ok' | 'warn' | 'danger';

export interface FormulaWidgetKpiPayload {
    type: 'kpi';
    name: string;
    value: number;
    periodLabel: string;
    delta: number | null;
    deltaPolarity?: FormulaDeltaPolarity;
    deltaComparisonLabel?: string | null;
    format: 'currency' | 'percent' | 'number';
    variant?: 'balance_summary';
    invested?: number;
    investedLinked?: number;
    patrimonioTotal?: number;
    accountsCount?: number;
    parameters?: FormulaWidgetRuntimeParameter[];
}

export interface FormulaWidgetProgressPayload {
    type: 'progress';
    name: string;
    value: number;
    threshold: number;
    percentage: number;
    periodLabel: string;
    variant?: 'traffic_light';
    status?: FormulaTrafficLightStatus;
    bands?: {
        warn: number;
        danger: number;
    };
    parameters?: FormulaWidgetRuntimeParameter[];
}

export interface FormulaWidgetLinePayload {
    type: 'line' | 'area';
    name: string;
    variant: 'line' | 'area';
    points: Array<{ label: string; value: number }>;
    series: FormulaChartSeriesEntry[];
    periodLabel: string;
    parameters?: FormulaWidgetRuntimeParameter[];
}

export interface FormulaWidgetBarPayload {
    type: 'bar' | 'horizontal_bar' | 'pie' | 'treemap';
    name: string;
    categories: FormulaWidgetCategorySlice[];
    periodLabel: string;
    parameters?: FormulaWidgetRuntimeParameter[];
}

export interface FormulaWidgetStackedBarPayload {
    type: 'stacked_bar';
    name: string;
    points: Array<{ label: string; series: Record<string, number> }>;
    series: FormulaChartSeriesEntry[];
    periodLabel: string;
    parameters?: FormulaWidgetRuntimeParameter[];
}

export type FormulaWidgetPayload =
    | FormulaWidgetKpiPayload
    | FormulaWidgetProgressPayload
    | FormulaWidgetLinePayload
    | FormulaWidgetBarPayload
    | FormulaWidgetStackedBarPayload;

export interface FinancialVariableSummary {
    id: number;
    code: string;
    name: string;
    type: string;
    static_value?: number | null;
    formula_string?: string | null;
    is_public?: boolean;
    share_token?: string | null;
    downloads_count?: number;
    source_id?: number | null;
    is_official_origin?: boolean;
}

export type SystemVariableCategory = 'financial' | 'context';

export interface SystemVariableMeta {
    code: string;
    label: string;
    requires_period: boolean;
    category?: SystemVariableCategory;
    example?: string | null;
}

export interface FormulaWidgetSummary {
    id: number;
    name: string;
    display_type: string;
    description?: string;
    period_preset: string | null;
    chart_config: Record<string, unknown> | null;
    default_size: string;
    is_public: boolean;
    share_token: string | null;
    downloads_count: number;
    financial_variable: FinancialVariableSummary | null;
    template_slug?: string | null;
    is_official_template?: boolean;
    is_official_origin?: boolean;
    can_delete?: boolean;
    clones_count?: number;
    installed?: boolean;
    installed_widget_id?: number | null;
    source_id?: number | null;
}

export function parseFormulaWidgetNumericId(widgetId: string): string | null {
    const match = widgetId.match(/^formula_widget_(\d+)$/);
    return match ? match[1] : null;
}

export function isFormulaWidgetId(widgetId: string): boolean {
    return /^formula_widget_\d+$/.test(widgetId);
}

export interface FormulaWidgetMeta {
    name: string;
    display_type: string;
    variant?: string | null;
    can_delete?: boolean;
}
