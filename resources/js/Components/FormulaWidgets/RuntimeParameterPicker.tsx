import type { FormulaWidgetParameterDefinition } from '@/types/formulaWidget';

interface RuntimeParameterPickerProps {
    parameters?: FormulaWidgetParameterDefinition[];
    onToggle: (parameter: FormulaWidgetParameterDefinition, enabled: boolean) => void;
}

const AVAILABLE_PARAMETERS: FormulaWidgetParameterDefinition[] = [
    { key: 'account_id', type: 'account', label: 'Conto selezionabile', default: 'all' },
    { key: 'period_offset', type: 'period_nav', label: 'Mese scorrevole', default: '0' },
    { key: 'tag_selected', type: 'tag', label: 'Tag selezionabile', default: 'all' },
    { key: 'category_excluded', type: 'category', label: 'Escludi categoria', default: 'none' },
    { key: 'currency_selected', type: 'currency', label: 'Valuta', default: 'all' },
    { key: 'debt_credit_selected', type: 'debt_credit', label: 'Debito/Credito', default: 'all' },
    { key: 'transaction_type_selected', type: 'transaction_type', label: 'Tipo transazione', default: 'all' },
];

export default function RuntimeParameterPicker({ parameters = [], onToggle }: RuntimeParameterPickerProps) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {AVAILABLE_PARAMETERS.map((parameter) => {
                const enabled = parameters.some((entry) => entry.key === parameter.key);

                return (
                    <label
                        key={parameter.key}
                        className="flex items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700"
                    >
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={enabled}
                            aria-label={parameter.label}
                            onChange={(e) => onToggle(parameter, e.target.checked)}
                        />
                        <span>
                            <span className="block font-medium text-gray-900 dark:text-white">{parameter.label}</span>
                            <span className="text-gray-600 dark:text-gray-400">
                                Controllo runtime in dashboard per «{parameter.key}».
                            </span>
                        </span>
                    </label>
                );
            })}
        </div>
    );
}
