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
        description: 'Reddito netto nel periodo (metriche fiscali household).',
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
        description: 'Totale versamenti mensili dei piani PAC attivi.',
        formula: '[pac_monthly_total]',
        suggestedCode: 'pac_mensile',
    },
    {
        id: 'if_expense_alert',
        category: 'periodo',
        name: 'Alert spese elevate',
        description: 'Restituisce 1 se le spese superano 1000€ nel periodo, altrimenti 0.',
        formula: 'IF([period_expenses] > 1000, 1, 0)',
        suggestedCode: 'alert_spese_elevate',
        requiresPeriod: true,
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
