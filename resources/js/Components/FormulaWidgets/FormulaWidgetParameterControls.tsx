import type { FormulaWidgetRuntimeParameter } from '@/types/formulaWidget';
import clsx from 'clsx';

interface FormulaWidgetParameterControlsProps {
    parameters?: FormulaWidgetRuntimeParameter[];
    disabled?: boolean;
    onChange: (key: string, value: string) => void;
    className?: string;
}

function AccountControl({
    parameter,
    disabled,
    onChange,
}: {
    parameter: FormulaWidgetRuntimeParameter;
    disabled: boolean;
    onChange: (key: string, value: string) => void;
}) {
    return (
        <label className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
            <span className="font-medium">{parameter.label}</span>
            <select
                className="rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-800 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                value={parameter.value}
                disabled={disabled}
                onChange={(event) => onChange(parameter.key, event.target.value)}
                aria-label={parameter.label}
            >
                {parameter.options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

function PeriodNavControl({
    parameter,
    disabled,
    onChange,
}: {
    parameter: FormulaWidgetRuntimeParameter;
    disabled: boolean;
    onChange: (key: string, value: string) => void;
}) {
    const offset = Number.parseInt(parameter.value, 10) || 0;
    const min = parameter.min ?? -36;
    const max = parameter.max ?? 0;
    const canGoBack = !disabled && offset > min;
    const canGoForward = !disabled && offset < max;
    const label = parameter.display_label ?? parameter.label;

    const buttonClass =
        'flex h-6 w-6 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700';

    return (
        <div className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
            <button
                type="button"
                className={buttonClass}
                disabled={!canGoBack}
                onClick={() => onChange(parameter.key, String(offset - 1))}
                aria-label="Mese precedente"
                title="Mese precedente"
            >
                <span aria-hidden="true">‹</span>
            </button>
            <span className="min-w-[6.5rem] text-center font-medium capitalize">{label}</span>
            <button
                type="button"
                className={buttonClass}
                disabled={!canGoForward}
                onClick={() => onChange(parameter.key, String(offset + 1))}
                aria-label="Mese successivo"
                title="Mese successivo"
            >
                <span aria-hidden="true">›</span>
            </button>
        </div>
    );
}

export default function FormulaWidgetParameterControls({
    parameters = [],
    disabled = false,
    onChange,
    className,
}: FormulaWidgetParameterControlsProps) {
    if (parameters.length === 0) {
        return null;
    }

    return (
        <div className={clsx('flex flex-wrap items-center gap-x-3 gap-y-2', className)}>
            {parameters.map((parameter) =>
                parameter.type === 'period_nav' ? (
                    <PeriodNavControl
                        key={parameter.key}
                        parameter={parameter}
                        disabled={disabled}
                        onChange={onChange}
                    />
                ) : (
                    <AccountControl
                        key={parameter.key}
                        parameter={parameter}
                        disabled={disabled}
                        onChange={onChange}
                    />
                ),
            )}
        </div>
    );
}
