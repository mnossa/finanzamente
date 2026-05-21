export type SimulationTabId = 'compound' | 'debt_vs_invest' | 'emergency' | 'stress_test';

export interface CompoundTabState {
    initialCapital: number;
    monthlyContribution: number;
    annualReturn: number;
    years: number;
    inflationEnabled: boolean;
    inflationRate: number;
}

export interface DebtVsInvestTabState {
    capital: number;
    debtRate: number;
    investReturn: number;
    taxRate: number;
    years: number;
}

export interface EmergencyTabState {
    monthlyExpenses: number;
    safetyMonths: number;
    currentFund: number;
    monthlySaving: number;
}

export interface StressTestTabState {
    portfolioValue: number;
    equityPercent: number;
    selectedCrisisId: string;
}

export type SimulationTabStates = {
    compound: CompoundTabState;
    debt_vs_invest: DebtVsInvestTabState;
    emergency: EmergencyTabState;
    stress_test: StressTestTabState;
};

export const DEFAULT_SIMULATION_TAB_STATES: SimulationTabStates = {
    compound: {
        initialCapital: 10000,
        monthlyContribution: 300,
        annualReturn: 7,
        years: 20,
        inflationEnabled: false,
        inflationRate: 2.5,
    },
    debt_vs_invest: {
        capital: 10000,
        debtRate: 5,
        investReturn: 7,
        taxRate: 26,
        years: 10,
    },
    emergency: {
        monthlyExpenses: 2000,
        safetyMonths: 6,
        currentFund: 3000,
        monthlySaving: 300,
    },
    stress_test: {
        portfolioValue: 50000,
        equityPercent: 70,
        selectedCrisisId: '',
    },
};

function asNumber(value: unknown, fallback: number): number {
    const n = Number(value);

    return Number.isFinite(n) ? n : fallback;
}

function asBoolean(value: unknown, fallback: boolean): boolean {
    return typeof value === 'boolean' ? value : fallback;
}

function asString(value: unknown, fallback: string): string {
    return typeof value === 'string' ? value : fallback;
}

export function hydrateTabState<T extends SimulationTabId>(
    tab: T,
    payload: Record<string, unknown> | null | undefined,
    defaultCrisisId = '',
): SimulationTabStates[T] {
    if (!payload || typeof payload !== 'object') {
        return createInitialTabStates(defaultCrisisId)[tab] as SimulationTabStates[T];
    }

    switch (tab) {
        case 'compound': {
            const d = DEFAULT_SIMULATION_TAB_STATES.compound;
            return {
                initialCapital: asNumber(payload.initialCapital, d.initialCapital),
                monthlyContribution: asNumber(payload.monthlyContribution, d.monthlyContribution),
                annualReturn: asNumber(payload.annualReturn, d.annualReturn),
                years: asNumber(payload.years, d.years),
                inflationEnabled: asBoolean(payload.inflationEnabled, d.inflationEnabled),
                inflationRate: asNumber(payload.inflationRate, d.inflationRate),
            } as SimulationTabStates[T];
        }
        case 'debt_vs_invest': {
            const d = DEFAULT_SIMULATION_TAB_STATES.debt_vs_invest;
            return {
                capital: asNumber(payload.capital, d.capital),
                debtRate: asNumber(payload.debtRate, d.debtRate),
                investReturn: asNumber(payload.investReturn, d.investReturn),
                taxRate: asNumber(payload.taxRate, d.taxRate),
                years: asNumber(payload.years, d.years),
            } as SimulationTabStates[T];
        }
        case 'emergency': {
            const d = DEFAULT_SIMULATION_TAB_STATES.emergency;
            return {
                monthlyExpenses: asNumber(payload.monthlyExpenses, d.monthlyExpenses),
                safetyMonths: asNumber(payload.safetyMonths, d.safetyMonths),
                currentFund: asNumber(payload.currentFund, d.currentFund),
                monthlySaving: asNumber(payload.monthlySaving, d.monthlySaving),
            } as SimulationTabStates[T];
        }
        case 'stress_test': {
            const d = DEFAULT_SIMULATION_TAB_STATES.stress_test;
            return {
                portfolioValue: asNumber(payload.portfolioValue, d.portfolioValue),
                equityPercent: asNumber(payload.equityPercent, d.equityPercent),
                selectedCrisisId: asString(
                    payload.selectedCrisisId,
                    d.selectedCrisisId || defaultCrisisId,
                ),
            } as SimulationTabStates[T];
        }
        default:
            return createInitialTabStates(defaultCrisisId)[tab] as SimulationTabStates[T];
    }
}

export function serializeTabState<T extends SimulationTabId>(
    tab: T,
    state: SimulationTabStates[T],
): Record<string, unknown> {
    return { ...state };
}

export function createInitialTabStates(defaultCrisisId: string): SimulationTabStates {
    return {
        compound: { ...DEFAULT_SIMULATION_TAB_STATES.compound },
        debt_vs_invest: { ...DEFAULT_SIMULATION_TAB_STATES.debt_vs_invest },
        emergency: { ...DEFAULT_SIMULATION_TAB_STATES.emergency },
        stress_test: {
            ...DEFAULT_SIMULATION_TAB_STATES.stress_test,
            selectedCrisisId: defaultCrisisId,
        },
    };
}

export const TAB_LABELS: Record<SimulationTabId, string> = {
    compound: 'Interesse composto',
    debt_vs_invest: 'Debito vs investimento',
    emergency: 'Fondo di emergenza',
    stress_test: 'Stress test',
};
