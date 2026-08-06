import type { FormulaWidgetChartConfig } from '@/types/formulaWidget';
import type { FinancialVariableScenario } from '@/utils/financialVariableScenarios';
import type { WidgetRecipeId } from '@/utils/formulaWidgetRuntimeControls';

/** Tipi di visualizzazione ammessi per ricetta (step 1 → step 3). */
const RECIPE_DISPLAY_TYPES: Record<WidgetRecipeId, readonly string[]> = {
    single_value: ['kpi'],
    trend: ['line', 'area'],
    comparison: ['bar', 'horizontal_bar', 'stacked_bar'],
    goal: ['progress'],
    tabular: ['table'],
};

const PERIOD_COMPARISON_SERIES: NonNullable<FormulaWidgetChartConfig['series']> = [
    { code: 'period_income', label: 'Incassato', color: '#10b981' },
    { code: 'period_expenses', label: 'Speso', color: '#f97316' },
    { code: 'period_net', label: 'Risparmiato', color: '#3b82f6' },
];

const SERIES_LABELS: Record<string, string> = {
    period_income: 'Incassato',
    period_expenses: 'Speso',
    period_net: 'Risparmiato',
    household_balance: 'Liquidità',
    total_investments: 'Investimenti',
    patrimonio_total: 'Patrimonio netto',
    net_income: 'Reddito netto',
    investment_purchases: 'Versamenti investimenti',
    pac_monthly_total: 'PAC mensili',
};

/** Estrae i codici sistema `[code]` da una formula. */
export function extractSystemCodesFromFormula(formula: string | null | undefined): string[] {
    if (!formula) {
        return [];
    }

    const matches = formula.matchAll(/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/g);
    const codes: string[] = [];
    const seen = new Set<string>();

    for (const match of matches) {
        const code = match[1];
        if (!seen.has(code)) {
            seen.add(code);
            codes.push(code);
        }
    }

    return codes;
}

export function displayTypesForRecipe(recipeId: WidgetRecipeId): readonly string[] {
    return RECIPE_DISPLAY_TYPES[recipeId] ?? ['kpi'];
}

export function recipeAllowsDisplayType(recipeId: WidgetRecipeId, displayType: string): boolean {
    return displayTypesForRecipe(recipeId).includes(displayType);
}

export function isSeriesDisplayType(displayType: string): boolean {
    return ['bar', 'horizontal_bar', 'stacked_bar', 'pie', 'treemap'].includes(displayType);
}

export function isProgressDisplayType(displayType: string): boolean {
    return displayType === 'progress';
}

/**
 * Allinea chart_config alla metrica scelta e al display_type corrente,
 * così anteprima e salvataggio usano gli stessi codici.
 */
export function syncChartConfigToMetric(input: {
    displayType: string;
    recipeId: WidgetRecipeId;
    formulaString?: string | null;
    scenario?: FinancialVariableScenario | null;
    chartConfig: FormulaWidgetChartConfig;
}): FormulaWidgetChartConfig {
    const { displayType, recipeId, formulaString, scenario, chartConfig } = input;
    const codes = extractSystemCodesFromFormula(formulaString ?? scenario?.formula);
    const primaryCode = codes[0];
    let next: FormulaWidgetChartConfig = { ...chartConfig };

    if (scenario?.widgetDefaults && recipeId === 'goal') {
        next = {
            ...next,
            ...scenario.widgetDefaults.chartConfig,
            parameters: scenario.widgetDefaults.chartConfig.parameters ?? next.parameters,
        };
    }

    if (isProgressDisplayType(displayType) && primaryCode) {
        // Alert spese: widgetDefaults già ha value_code corretto.
        if (!(scenario?.widgetDefaults && recipeId === 'goal')) {
            next = {
                ...next,
                value_code: primaryCode,
                threshold_code: next.threshold_code && next.threshold_code !== primaryCode
                    ? next.threshold_code
                    : (codes[1] ?? (primaryCode === 'period_net' ? 'period_income' : 'period_income')),
            };
        }
    }

    if (isSeriesDisplayType(displayType)) {
        if (recipeId === 'comparison') {
            // Confronto: serie periodo coerenti, indipendenti dalla singola card metrica.
            next = {
                ...next,
                series: PERIOD_COMPARISON_SERIES,
                // Evita che una query dinamica residua di una tabella precedenti “sballi” le barre.
                metric_query: undefined,
            };
        } else if (codes.length >= 2) {
            next = {
                ...next,
                series: codes.map((code) => ({
                    code,
                    label: SERIES_LABELS[code] ?? code,
                })),
                metric_query: undefined,
            };
        } else if (primaryCode) {
            // Barre richiedono ≥2 serie: completa con metriche di periodo affini.
            const companion = PERIOD_COMPARISON_SERIES.filter((entry) => entry.code !== primaryCode).slice(0, 2);
            next = {
                ...next,
                series: [
                    {
                        code: primaryCode,
                        label: SERIES_LABELS[primaryCode] ?? primaryCode,
                        color: PERIOD_COMPARISON_SERIES.find((entry) => entry.code === primaryCode)?.color,
                    },
                    ...companion,
                ],
                metric_query: undefined,
            };
        }
    }

    if (displayType === 'table') {
        next = {
            ...next,
            metric_query: next.metric_query ?? {
                datasource: 'transactions',
                measure: 'count',
                amount_field: 'amount_base',
                filters: [],
            },
            table: next.table ?? {
                mode: 'rows',
                row_limit: 10,
                sort: { field: 'date', direction: 'desc' },
            },
        };
    } else if (recipeId !== 'tabular') {
        // Fuori dalla tabella: non lasciare metric_query/table a inquinare KPI/line/bar.
        if (displayType !== 'line' && displayType !== 'area' && displayType !== 'kpi') {
            next = {
                ...next,
                metric_query: undefined,
                table: undefined,
            };
        } else if (next.table) {
            next = { ...next, table: undefined };
        }
    }

    return next;
}

export function recipeStepHint(recipeId: WidgetRecipeId, step: 1 | 2 | 3): string {
    if (step === 1) {
        return 'Scegli il risultato. Impostiamo visualizzazione e opzioni consigliate.';
    }

    if (step === 2) {
        if (recipeId === 'tabular') {
            return 'La tabella usa filtri e sorgente dati. La metrica resta un riferimento in libreria.';
        }
        if (recipeId === 'comparison') {
            return 'Scegli una metrica di periodo: le barre confronteranno incassato, speso e risparmiato.';
        }
        if (recipeId === 'goal') {
            return 'Scegli la metrica da confrontare con una soglia o un target.';
        }

        return 'Scegli metrica e periodo. L’anteprima si aggiorna subito.';
    }

    if (recipeId === 'tabular') {
        return 'Dai un nome e, se serve, regola modalità lista o aggregata. Nessun grafico: è una tabella.';
    }
    if (recipeId === 'comparison') {
        return 'Nome e tipo di confronto (barre). L’anteprima usa le metriche di periodo scelte.';
    }

    return 'Nome e aspetto. Solo visualizzazioni compatibili con l’obiettivo.';
}
