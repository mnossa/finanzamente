export type FinancialVariableScenarioCategory =
    | 'bilancio_conto'
    | 'patrimonio'
    | 'periodo'
    | 'investimenti'
    | 'personalizzato';

export interface FinancialVariableScenario {
    id: string;
    category: FinancialVariableScenarioCategory;
    name: string;
    description: string;
    formula: string;
    suggestedCode?: string;
    requiresPeriod?: boolean;
    /** Se presente, alla selezione metrica forza display/chart del widget. */
    widgetDefaults?: {
        displayType: string;
        chartConfig: {
            variant?: string;
            value_code?: string;
            threshold_code?: string;
            threshold_amount?: number;
            bands?: { warn?: number; danger?: number };
            parameters?: Array<{
                key: string;
                type: 'number' | 'account' | 'period_nav' | 'tag' | 'category' | 'currency' | 'debt_credit' | 'transaction_type';
                label: string;
                default?: string;
            }>;
        };
    };
}

export const FINANCIAL_VARIABLE_SCENARIO_CATEGORIES: Record<
    FinancialVariableScenarioCategory,
    { label: string; description: string }
> = {
    bilancio_conto: {
        label: 'Bilancio conto',
        description: 'Metriche per conto nel periodo: incassi, spese e saldo.',
    },
    patrimonio: {
        label: 'Patrimonio',
        description: 'Liquidità, investimenti e patrimonio netto.',
    },
    periodo: {
        label: 'Periodo',
        description: 'Entrate, uscite e metriche collegate al periodo del widget.',
    },
    investimenti: {
        label: 'Investimenti & PAC',
        description: 'Versamenti, proiezioni e posizioni investite.',
    },
    personalizzato: {
        label: 'Personalizzato',
        description: 'Parti da zero e componi la formula.',
    },
};

export const FINANCIAL_VARIABLE_SCENARIOS: FinancialVariableScenario[] = [
    {
        id: 'period_income',
        category: 'bilancio_conto',
        name: 'Incassato nel periodo',
        description: 'Entrate nel periodo del widget. Con filtro conto in dashboard, limita a un conto.',
        formula: '[period_income]',
        suggestedCode: 'incassato_periodo',
        requiresPeriod: true,
    },
    {
        id: 'period_expenses',
        category: 'bilancio_conto',
        name: 'Speso nel periodo',
        description: 'Uscite nel periodo del widget. Con filtro conto, mostra solo quel conto.',
        formula: '[period_expenses]',
        suggestedCode: 'speso_periodo',
        requiresPeriod: true,
    },
    {
        id: 'period_net',
        category: 'bilancio_conto',
        name: 'Bilancio conto',
        description: 'Risparmio netto (incassi − spese) nel periodo. Ideale per monitorare la gestione del conto.',
        formula: '[period_net]',
        suggestedCode: 'bilancio_conto',
        requiresPeriod: true,
    },
    {
        id: 'household_balance',
        category: 'patrimonio',
        name: 'Liquidità attuale',
        description: 'Saldo complessivo dei conti accessibili.',
        formula: '[household_balance]',
        suggestedCode: 'liquidita',
    },
    {
        id: 'patrimonio_total',
        category: 'patrimonio',
        name: 'Patrimonio netto',
        description: 'Patrimonio totale (conti + investimenti − debiti).',
        formula: '[patrimonio_total]',
        suggestedCode: 'patrimonio_netto',
    },
    {
        id: 'net_income',
        category: 'periodo',
        name: 'Reddito netto',
        description: 'Entrate nel periodo (aggregato household).',
        formula: '[net_income]',
        suggestedCode: 'reddito_netto',
        requiresPeriod: true,
    },
    {
        id: 'savings_rate',
        category: 'periodo',
        name: 'Tasso di risparmio',
        description: 'Percentuale risparmiata sul reddito nel periodo.',
        formula: '[period_net] / [period_income] * 100',
        suggestedCode: 'tasso_risparmio',
        requiresPeriod: true,
    },
    {
        id: 'pac_monthly',
        category: 'investimenti',
        name: 'Versamenti PAC mensili',
        description: 'Totale versamenti mensili pianificati dei soli piani PAC attivi.',
        formula: '[pac_monthly_total]',
        suggestedCode: 'pac_mensile',
    },
    {
        id: 'investment_purchases',
        category: 'investimenti',
        name: 'Versamenti investimenti nel periodo',
        description: 'Acquisti di tutti gli asset nel periodo del widget: PAC e investimenti singoli.',
        formula: '[investment_purchases]',
        suggestedCode: 'versamenti_investimenti',
        requiresPeriod: true,
    },
    {
        id: 'if_expense_alert',
        category: 'periodo',
        name: 'Alert spese elevate',
        description: 'Semaforo verde/arancione/rosso rispetto a una soglia di spesa editabile.',
        // Identità univoca (≠ [period_expenses]); il progress usa value_code, non questa formula.
        formula: 'MAX([period_expenses], 0)',
        suggestedCode: 'alert_spese_elevate',
        requiresPeriod: true,
        widgetDefaults: {
            displayType: 'progress',
            chartConfig: {
                variant: 'traffic_light',
                value_code: 'period_expenses',
                threshold_amount: 1000,
                bands: { warn: 70, danger: 100 },
                parameters: [
                    { key: 'threshold', type: 'number', label: 'Soglia (€)', default: '1000' },
                ],
            },
        },
    },
    {
        id: 'when_positive_net',
        category: 'bilancio_conto',
        name: 'Risparmio solo se positivo',
        description: 'WHEN restituisce il valore solo se la condizione è vera, altrimenti 0.',
        formula: 'WHEN([period_net] > 0, [period_net])',
        suggestedCode: 'risparmio_se_positivo',
        requiresPeriod: true,
    },
    {
        id: 'custom',
        category: 'personalizzato',
        name: 'Formula personalizzata',
        description: 'Componi liberamente con variabili di sistema, contesto e le tue variabili.',
        formula: '',
    },
];

export function scenariosByCategory(
    category: FinancialVariableScenarioCategory,
): FinancialVariableScenario[] {
    return FINANCIAL_VARIABLE_SCENARIOS.filter((scenario) => scenario.category === category);
}

export function normalizeFormula(formula: string): string {
    return formula.replace(/\s+/g, '').trim();
}

export function findScenarioByFormula(formula: string | null | undefined): FinancialVariableScenario | undefined {
    if (!formula) {
        return undefined;
    }

    const normalized = normalizeFormula(formula);

    const direct = FINANCIAL_VARIABLE_SCENARIOS.find(
        (scenario) => scenario.id !== 'custom' && normalizeFormula(scenario.formula) === normalized,
    );

    if (direct) {
        return direct;
    }

    const aliasScenarioId = PICKER_FORMULA_ALIAS_TO_SCENARIO[normalized];
    if (!aliasScenarioId) {
        return undefined;
    }

    return FINANCIAL_VARIABLE_SCENARIOS.find((scenario) => scenario.id === aliasScenarioId);
}

/**
 * Alias formula → scenario id (template ufficiali con formula leggermente diversa).
 */
const PICKER_FORMULA_ALIAS_TO_SCENARIO: Record<string, string> = {
    [normalizeFormula('(([period_income]-[period_expenses])/[period_income])*100')]: 'savings_rate',
    [normalizeFormula('([period_income]-[period_expenses])/[period_income]*100')]: 'savings_rate',
    [normalizeFormula('IF([period_expenses] > 1000, 1, 0)')]: 'if_expense_alert',
};

/**
 * Formule ufficiali / alias senza card scenario dedicata: non in «Le tue metriche».
 */
const PICKER_COVERED_EXTRA_FORMULAS: string[] = [
    '[total_investments]',
    '[totale_investimenti]',
    '([total_investments]/[patrimonio_total])*100',
].map(normalizeFormula);

function normalizeScenarioName(name: string): string {
    return name.trim().toLocaleLowerCase('it');
}

/** Scenario pronto con stesso nome (collisione chip vs card). */
export function findReadyScenarioByName(name: string | null | undefined): FinancialVariableScenario | undefined {
    if (!name) {
        return undefined;
    }

    const normalized = normalizeScenarioName(name);

    return readyMetricScenarios().find((scenario) => normalizeScenarioName(scenario.name) === normalized);
}

/** True se la formula è già offrabile dalle card scenario / catalogo ufficiale. */
export function isPickerCoveredFormula(formula: string | null | undefined): boolean {
    if (!formula) {
        return false;
    }

    if (findScenarioByFormula(formula)) {
        return true;
    }

    return PICKER_COVERED_EXTRA_FORMULAS.includes(normalizeFormula(formula));
}

/** Chip «Le tue metriche»: solo custom vere (non ufficiali, non equivalenti a scenario). */
export function isTrueCustomPickerVariable(variable: {
    type: string;
    name?: string;
    formula_string?: string | null;
    is_official_origin?: boolean;
}): boolean {
    if (variable.is_official_origin) {
        return false;
    }

    if (variable.type === 'static') {
        return true;
    }

    // Stesso nome di una card pronto → nascondi (anche se formula storicamente sbagliata).
    if (findReadyScenarioByName(variable.name)) {
        return false;
    }

    if (!variable.formula_string) {
        return false;
    }

    return !isPickerCoveredFormula(variable.formula_string);
}

/** Scenari pronti (esclude formula personalizzata). */
export function readyMetricScenarios(): FinancialVariableScenario[] {
    return FINANCIAL_VARIABLE_SCENARIOS.filter((scenario) => scenario.id !== 'custom');
}
