import FormulaStringInput from '@/Components/FormulaWidgets/FormulaStringInput';
import InputLabel from '@/Components/InputLabel';
import type { MetricQueryConfig } from '@/utils/metricQueryForm';
import type { FormulaSuggestion } from '@/Components/FormulaWidgets/FormulaStringInput';

const DEFAULT_FORMULA_FUNCTIONS: MetricQueryConfig['formula_functions'] = {
    IF: 'IF(condizione, valoreSeVero, valoreSeFalso)',
    WHEN: 'WHEN(condizione, valore) — restituisce valore se condizione vera, altrimenti 0',
    ABS: 'ABS(valore)',
    MIN: 'MIN(a, b)',
    MAX: 'MAX(a, b)',
    ROUND: 'ROUND(valore, decimali)',
};

interface AdvancedFormulaPanelProps {
    formula: string;
    onChange: (formula: string) => void;
    suggestions: FormulaSuggestion[];
    metricQueryConfig?: MetricQueryConfig;
    errors?: Record<string, string>;
}

export default function AdvancedFormulaPanel({
    formula,
    onChange,
    suggestions,
    metricQueryConfig,
    errors = {},
}: AdvancedFormulaPanelProps) {
    const examples = [
        'IF([period_expenses] > 1000, 1, 0)',
        'WHEN([period_net] > 0, [period_net])',
        'ABS([period_income] - [period_expenses])',
        'ROUND([period_net] / [days_in_period], 2)',
    ];

    return (
        <div className="space-y-4">
            <div>
                <InputLabel value="Formula avanzata" />
                <FormulaStringInput
                    id="advanced-formula-input"
                    value={formula}
                    onChange={onChange}
                    suggestions={suggestions}
                    placeholder="Es. IF([period_expenses] > 1000, [period_expenses], 0)"
                />
                {errors.formula_string ? (
                    <p className="mt-1 text-sm text-red-600">{errors.formula_string}</p>
                ) : null}
            </div>

            <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                <p className="font-medium text-gray-900 dark:text-white">Funzioni supportate</p>
                <ul className="mt-2 space-y-1 text-gray-600 dark:text-gray-400">
                    {Object.entries(metricQueryConfig?.formula_functions ?? DEFAULT_FORMULA_FUNCTIONS).map(([name, hint]) => (
                        <li key={name}>
                            <code className="font-mono text-xs">{name}</code> — {hint}
                        </li>
                    ))}
                </ul>
                <p className="mt-3 font-medium text-gray-900 dark:text-white">Esempi rapidi</p>
                <div className="mt-2 flex flex-wrap gap-2">
                    {examples.map((example) => (
                        <button
                            key={example}
                            type="button"
                            className="rounded-full border border-gray-300 px-2 py-1 text-xs font-mono hover:bg-white dark:border-gray-600 dark:hover:bg-gray-800"
                            onClick={() => onChange(example)}
                        >
                            {example}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
