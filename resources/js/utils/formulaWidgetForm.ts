export interface FormulaChartConfigLike {
    show_delta?: boolean;
    parameters?: Array<{ key: string; type: string; label: string; default?: string }>;
}

export function extractRuntimeParamDefaultsFromChartConfig(
    chartConfig: FormulaChartConfigLike | Record<string, unknown>,
): Record<string, string> {
    const parameters = chartConfig?.parameters;

    if (!Array.isArray(parameters)) {
        return {};
    }

    const defaults: Record<string, string> = {};

    for (const parameter of parameters) {
        if (!parameter || typeof parameter !== 'object' || typeof parameter.key !== 'string') {
            continue;
        }

        defaults[parameter.key] = parameter.default !== undefined ? String(parameter.default) : '';
    }

    return defaults;
}

export function chartConfigHasRuntimeParameters(chartConfig: FormulaChartConfigLike | Record<string, unknown>): boolean {
    return Object.keys(extractRuntimeParamDefaultsFromChartConfig(chartConfig)).length > 0;
}

const SERIES_DISPLAY_TYPES = new Set([
    'bar',
    'horizontal_bar',
    'stacked_bar',
    'pie',
    'treemap',
]);

const LINKED_VARIABLE_CHART_TYPES = new Set(['line', 'area']);

export function formulaWidgetRequiresPeriod(displayType: string, chartConfig: FormulaChartConfigLike): boolean {
    return (
        ['line', 'area', 'stacked_bar', 'progress'].includes(displayType) ||
        (displayType === 'kpi' && Boolean(chartConfig.show_delta))
    );
}

export function formulaWidgetUsesSeries(displayType: string): boolean {
    return SERIES_DISPLAY_TYPES.has(displayType);
}

export function formulaWidgetUsesLinkedVariableSeries(displayType: string): boolean {
    return LINKED_VARIABLE_CHART_TYPES.has(displayType);
}

/** Consente solo caratteri ammessi nelle formule backend (no eval, no stringhe arbitrarie). */
export function sanitizeFormulaString(value: string): string {
    return value.replace(/[^0-9+\-*/().\s\[\]a-z_]/gi, '');
}
