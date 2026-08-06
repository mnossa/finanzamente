import {
    FINANCIAL_VARIABLE_SCENARIO_CATEGORIES,
    findScenarioByFormula,
    isTrueCustomPickerVariable,
    readyMetricScenarios,
    type FinancialVariableScenario,
    type FinancialVariableScenarioCategory,
} from '@/utils/financialVariableScenarios';
import {
    recipeRestrictsScenarios,
    scenarioMatchesRecipe,
    type WidgetRecipeId,
} from '@/utils/formulaWidgetRuntimeControls';
import clsx from 'clsx';
import { useMemo } from 'react';
import type { FinancialVariableSummary } from '@/types/formulaWidget';

interface MetricScenarioPickerProps {
    variables: FinancialVariableSummary[];
    selectedVariableId: number | '';
    ensuringScenarioId: string | null;
    recipeId?: WidgetRecipeId;
    onSelectScenario: (scenario: FinancialVariableScenario) => void;
    onSelectVariable: (variable: FinancialVariableSummary) => void;
    onOpenCustomFormula: () => void;
}

const CATEGORY_ORDER: FinancialVariableScenarioCategory[] = [
    'bilancio_conto',
    'patrimonio',
    'periodo',
    'investimenti',
];

export default function MetricScenarioPicker({
    variables,
    selectedVariableId,
    ensuringScenarioId,
    recipeId = 'single_value',
    onSelectScenario,
    onSelectVariable,
    onOpenCustomFormula,
}: MetricScenarioPickerProps) {
    const selectedVariable = variables.find((variable) => variable.id === selectedVariableId);
    const selectedScenario = findScenarioByFormula(selectedVariable?.formula_string);
    const readyScenarios = readyMetricScenarios();
    const customVariables = variables.filter(isTrueCustomPickerVariable);
    const restricted = recipeRestrictsScenarios(recipeId);

    const filteredScenarios = useMemo(
        () => readyScenarios.filter((scenario) => scenarioMatchesRecipe(scenario, recipeId)),
        [readyScenarios, recipeId],
    );

    return (
        <div className="space-y-4" data-testid="metric-scenario-picker">
            <p className="text-sm text-gray-600 dark:text-gray-400">
                {restricted
                    ? 'Solo metriche compatibili con l’obiettivo scelto. Un tap e la metrica è pronta.'
                    : 'Scegli cosa mettere nel widget — come scegli le celle per un grafico in Excel. Un tap e la metrica è pronta.'}
            </p>

            {CATEGORY_ORDER.map((category) => {
                const scenarios = filteredScenarios.filter((scenario) => scenario.category === category);
                if (scenarios.length === 0) {
                    return null;
                }

                const meta = FINANCIAL_VARIABLE_SCENARIO_CATEGORIES[category];

                return (
                    <div key={category}>
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {meta.label}
                        </p>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                            {scenarios.map((scenario) => {
                                const isSelected = selectedScenario?.id === scenario.id;
                                const isBusy = ensuringScenarioId === scenario.id;

                                return (
                                    <button
                                        key={scenario.id}
                                        type="button"
                                        disabled={ensuringScenarioId !== null}
                                        onClick={() => onSelectScenario(scenario)}
                                        aria-labelledby={`metric-scenario-title-${scenario.id}`}
                                        aria-describedby={`metric-scenario-desc-${scenario.id}`}
                                        aria-pressed={isSelected}
                                        className={clsx(
                                            'rounded-xl border px-3 py-3 text-left transition-colors',
                                            isSelected
                                                ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-950/40'
                                                : 'border-surface-200 bg-white hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-primary-700',
                                            ensuringScenarioId !== null && !isBusy && 'opacity-60',
                                        )}
                                    >
                                        <span id={`metric-scenario-title-${scenario.id}`} className="block text-sm font-semibold text-gray-900 dark:text-white">
                                            {isBusy ? 'Selezione…' : scenario.name}
                                        </span>
                                        <span id={`metric-scenario-desc-${scenario.id}`} className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                            {scenario.description}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                );
            })}

            {customVariables.length > 0 ? (
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Le tue metriche
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        {customVariables.map((variable) => {
                            const isSelected = selectedVariableId === variable.id;

                            return (
                                <button
                                    key={variable.id}
                                    type="button"
                                    disabled={ensuringScenarioId !== null}
                                    onClick={() => onSelectVariable(variable)}
                                    aria-pressed={isSelected}
                                    className={clsx(
                                        'rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                                        isSelected
                                            ? 'border-primary-500 bg-primary-50 text-primary-800 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100'
                                            : 'border-surface-200 bg-white text-gray-700 hover:border-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200',
                                    )}
                                >
                                    {variable.name}
                                </button>
                            );
                        })}
                    </div>
                </div>
            ) : null}

            <button
                type="button"
                onClick={onOpenCustomFormula}
                disabled={ensuringScenarioId !== null}
                className="w-full rounded-xl border border-dashed border-surface-300 px-3 py-3 text-left text-sm font-medium text-primary-700 transition-colors hover:border-primary-400 hover:bg-primary-50/60 dark:border-gray-600 dark:text-primary-300 dark:hover:border-primary-500 dark:hover:bg-primary-950/30"
            >
                Formula personalizzata…
                <span className="mt-0.5 block text-xs font-normal text-gray-500 dark:text-gray-400">
                    Come scrivere una formula Excel avanzata (IF, operatori, variabili tue).
                </span>
            </button>
        </div>
    );
}
