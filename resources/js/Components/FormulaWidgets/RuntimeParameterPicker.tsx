import type { FormulaWidgetParameterDefinition } from '@/types/formulaWidget';

interface RuntimeParameterPickerProps {
    parameters?: FormulaWidgetParameterDefinition[];
    /** Se valorizzato, mostra solo questi controlli (coerenti con periodo/metrica). */
    availableParameters?: FormulaWidgetParameterDefinition[];
    onToggle: (parameter: FormulaWidgetParameterDefinition, enabled: boolean) => void;
    emptyHint?: string;
}

const FALLBACK_PARAMETERS: FormulaWidgetParameterDefinition[] = [
    { key: 'account_id', type: 'account', label: 'Conto selezionabile', default: 'all' },
    { key: 'period_offset', type: 'period_nav', label: 'Mese scorrevole', default: '0' },
];

export default function RuntimeParameterPicker({
    parameters = [],
    availableParameters,
    onToggle,
    emptyHint = 'Nessun controllo aggiuntivo ha senso con le scelte attuali (metrica e periodo).',
}: RuntimeParameterPickerProps) {
    const catalog = availableParameters ?? FALLBACK_PARAMETERS;

    if (catalog.length === 0) {
        return (
            <p className="rounded-lg border border-dashed border-gray-200 px-3 py-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                {emptyHint}
            </p>
        );
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {catalog.map((parameter) => {
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
                                {parameter.key === 'period_offset'
                                    ? 'Scorri i mesi in dashboard (utile con «Mese corrente»).'
                                    : parameter.key === 'threshold'
                                        ? 'Modifica la soglia del semaforo direttamente in dashboard.'
                                        : `Controllo runtime in dashboard per «${parameter.key}».`}
                            </span>
                        </span>
                    </label>
                );
            })}
        </div>
    );
}
