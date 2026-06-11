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

export type FormulaDeltaPolarity = 'higher_is_better' | 'lower_is_better';

export interface FormulaWidgetKpiPayload {
    type: 'kpi';
    name: string;
    value: number;
    periodLabel: string;
    delta: number | null;
    deltaPolarity?: FormulaDeltaPolarity;
    deltaComparisonLabel?: string | null;
    format: 'currency' | 'percent';
    variant?: 'balance_summary';
    invested?: number;
    investedLinked?: number;
    patrimonioTotal?: number;
    accountsCount?: number;
}

export interface FormulaWidgetProgressPayload {
    type: 'progress';
    name: string;
    value: number;
    threshold: number;
    percentage: number;
    periodLabel: string;
}

export interface FormulaWidgetLinePayload {
    type: 'line' | 'area';
    name: string;
    variant: 'line' | 'area';
    points: Array<{ label: string; value: number }>;
    series: FormulaChartSeriesEntry[];
    periodLabel: string;
}

export interface FormulaWidgetBarPayload {
    type: 'bar' | 'horizontal_bar' | 'pie' | 'treemap';
    name: string;
    categories: FormulaWidgetCategorySlice[];
    periodLabel: string;
}

export interface FormulaWidgetStackedBarPayload {
    type: 'stacked_bar';
    name: string;
    points: Array<{ label: string; series: Record<string, number> }>;
    series: FormulaChartSeriesEntry[];
    periodLabel: string;
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
    period_preset: string | null;
    chart_config: Record<string, unknown> | null;
    default_size: string;
    is_public: boolean;
    share_token: string | null;
    downloads_count: number;
    financial_variable: FinancialVariableSummary | null;
    template_slug?: string | null;
    is_official_template?: boolean;
    installed?: boolean;
    installed_widget_id?: number | null;
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
}
