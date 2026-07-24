import type { FormulaWidgetParameterDefinition, MetricQueryDefinition } from '@/types/formulaWidget';
import type { FinancialVariableScenario } from '@/utils/financialVariableScenarios';
import { findScenarioByFormula } from '@/utils/financialVariableScenarios';

export const RUNTIME_CONTROL_CATALOG: FormulaWidgetParameterDefinition[] = [
    { key: 'account_id', type: 'account', label: 'Conto selezionabile', default: 'all' },
    { key: 'period_offset', type: 'period_nav', label: 'Mese scorrevole', default: '0' },
    { key: 'tag_selected', type: 'tag', label: 'Tag selezionabile', default: 'all' },
    { key: 'category_excluded', type: 'category', label: 'Escludi categoria', default: 'none' },
    { key: 'currency_selected', type: 'currency', label: 'Valuta', default: 'all' },
    { key: 'debt_credit_selected', type: 'debt_credit', label: 'Debito/Credito', default: 'all' },
    { key: 'transaction_type_selected', type: 'transaction_type', label: 'Tipo transazione', default: 'all' },
    { key: 'threshold', type: 'number', label: 'Soglia (€)', default: '1000' },
];

export type WidgetRecipeId = 'single_value' | 'trend' | 'comparison' | 'goal' | 'tabular';

/** Preset periodo su cui ha senso lo scorrimento mese-per-mese in dashboard. */
export function periodSupportsMonthNavigation(periodPreset: string): boolean {
    return periodPreset === 'current_month';
}

export function accountControlApplicable(scenario: FinancialVariableScenario | undefined): boolean {
    if (!scenario) {
        return true;
    }

    // Piano PAC mensile: non è un saldo per conto.
    if (scenario.id === 'pac_monthly') {
        return false;
    }

    return true;
}

/**
 * Controlli runtime coerenti con periodo, metrica e eventuale query dinamica.
 * Non propone opzioni inutilizzabili rispetto alle scelte degli step precedenti.
 */
export function availableRuntimeControls(input: {
    periodPreset: string;
    requiresPeriod: boolean;
    formulaString?: string | null;
    metricQuery?: MetricQueryDefinition;
    displayType?: string;
    chartVariant?: string | null;
}): FormulaWidgetParameterDefinition[] {
    const scenario = findScenarioByFormula(input.formulaString);
    const hasMetricQuery = Boolean(input.metricQuery);
    const datasource = input.metricQuery?.datasource ?? 'transactions';
    const available: FormulaWidgetParameterDefinition[] = [];

    if (accountControlApplicable(scenario) || (hasMetricQuery && datasource === 'transactions')) {
        available.push(RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'account_id')!);
    }

    if (input.requiresPeriod && periodSupportsMonthNavigation(input.periodPreset)) {
        available.push(RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'period_offset')!);
    }

    if (input.displayType === 'progress' && input.chartVariant === 'traffic_light') {
        available.push(RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'threshold')!);
    }

    if (!hasMetricQuery) {
        return available.filter(Boolean);
    }

    if (datasource === 'transactions') {
        available.push(
            RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'tag_selected')!,
            RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'category_excluded')!,
            RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'currency_selected')!,
            RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'transaction_type_selected')!,
        );
    }

    if (datasource === 'debts_credits' || datasource === 'transactions') {
        available.push(RUNTIME_CONTROL_CATALOG.find((entry) => entry.key === 'debt_credit_selected')!);
    }

    if (datasource === 'investment_pacs' || (hasMetricQuery && datasource === 'transactions')) {
        // account already added above when applicable
    }

    return available.filter(Boolean);
}

export function pruneRuntimeParameters(
    parameters: FormulaWidgetParameterDefinition[] | undefined,
    allowed: FormulaWidgetParameterDefinition[],
): FormulaWidgetParameterDefinition[] {
    const allowedKeys = new Set(allowed.map((entry) => entry.key));

    return (parameters ?? []).filter((parameter) => allowedKeys.has(parameter.key));
}

/** Scenario ids consigliati per ricetta step 1. null = tutte. */
const RECIPE_SCENARIO_IDS: Record<WidgetRecipeId, string[] | null> = {
    single_value: null,
    trend: [
        'period_income',
        'period_expenses',
        'period_net',
        'net_income',
        'savings_rate',
        'investment_purchases',
        'when_positive_net',
    ],
    comparison: [
        'period_income',
        'period_expenses',
        'period_net',
        'when_positive_net',
    ],
    goal: [
        'period_net',
        'savings_rate',
        'when_positive_net',
        'if_expense_alert',
        'investment_purchases',
    ],
    tabular: [
        'period_income',
        'period_expenses',
        'period_net',
        'investment_purchases',
        'when_positive_net',
    ],
};

export function scenarioIdsForRecipe(recipeId: WidgetRecipeId): string[] | null {
    return RECIPE_SCENARIO_IDS[recipeId] ?? null;
}

export function scenarioMatchesRecipe(
    scenario: FinancialVariableScenario,
    recipeId: WidgetRecipeId,
): boolean {
    const allowed = scenarioIdsForRecipe(recipeId);
    if (allowed === null) {
        return scenario.id !== 'custom';
    }

    return allowed.includes(scenario.id);
}
