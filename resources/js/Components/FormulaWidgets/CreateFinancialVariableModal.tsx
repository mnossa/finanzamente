import FormulaStringInput, { type FormulaSuggestion } from '@/Components/FormulaWidgets/FormulaStringInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import axios from 'axios';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';
import type { FinancialVariableSummary, SystemVariableMeta } from '@/types/formulaWidget';
import { systemVariableToFormulaSuggestion } from '@/utils/formulaVariableHints';

interface CreateFinancialVariableModalProps {
    open: boolean;
    systemVariables: SystemVariableMeta[];
    onClose: () => void;
    onCreated: (variable: FinancialVariableSummary) => void;
}

export default function CreateFinancialVariableModal({
    open,
    systemVariables,
    onClose,
    onCreated,
}: CreateFinancialVariableModalProps) {
    const [name, setName] = useState('');
    const [code, setCode] = useState('');
    const [type, setType] = useState<'formula' | 'static'>('formula');
    const [formulaString, setFormulaString] = useState('[household_balance]');
    const [staticValue, setStaticValue] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) {
            return;
        }

        setName('');
        setCode('');
        setType('formula');
        setFormulaString('[household_balance]');
        setStaticValue('');
        setErrors({});
    }, [open]);

    const formulaSuggestions = useMemo<FormulaSuggestion[]>(
        () => systemVariables.map(systemVariableToFormulaSuggestion),
        [systemVariables],
    );

    const insertToken = (token: string) => {
        setFormulaString((current) => `${current}${current.endsWith(']') || current === '' ? '' : ' '}[${token}]`);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        axios
            .post(
                route('formula-variables.store'),
                {
                    name,
                    code: code || undefined,
                    type,
                    formula_string: type === 'formula' ? formulaString : undefined,
                    static_value: type === 'static' ? staticValue : undefined,
                    is_public: false,
                },
                { headers: { Accept: 'application/json' } },
            )
            .then((response) => {
                onCreated(response.data.variable as FinancialVariableSummary);
                onClose();
            })
            .catch((error) => {
                const validationErrors = error?.response?.data?.errors as Record<string, string[]> | undefined;
                if (validationErrors) {
                    const flat: Record<string, string> = {};
                    Object.entries(validationErrors).forEach(([key, messages]) => {
                        flat[key] = messages[0] ?? 'Errore di validazione.';
                    });
                    setErrors(flat);
                } else {
                    setErrors({ form: 'Non sono riuscito a creare la variabile. Riprova.' });
                }
            })
            .finally(() => setProcessing(false));
    };

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true">
            <button
                type="button"
                className="absolute inset-0 bg-black/50"
                aria-label="Chiudi"
                onClick={onClose}
            />
            <div className="relative z-10 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Crea variabile personalizzata</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    La variabile sarà selezionata automaticamente nel widget.
                </p>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <div>
                        <InputLabel htmlFor="modal-var-name" value="Nome" />
                        <TextInput
                            id="modal-var-name"
                            className="mt-1 block w-full"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="modal-var-code" value="Codice (opzionale)" />
                        <TextInput
                            id="modal-var-code"
                            className="mt-1 block w-full font-mono"
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            placeholder="es. risparmio_mensile"
                        />
                        <InputError message={errors.code} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="modal-var-type" value="Tipo" />
                        <select
                            id="modal-var-type"
                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-900"
                            value={type}
                            onChange={(e) => setType(e.target.value as 'formula' | 'static')}
                        >
                            <option value="formula">Formula</option>
                            <option value="static">Valore statico</option>
                        </select>
                    </div>

                    {type === 'formula' ? (
                        <div>
                            <InputLabel htmlFor="modal-var-formula" value="Formula" />
                            <div className="mt-1">
                                <FormulaStringInput
                                    id="modal-var-formula"
                                    value={formulaString}
                                    onChange={setFormulaString}
                                    suggestions={formulaSuggestions}
                                    required
                                    placeholder="es. [period_income] - [period_expenses]"
                                />
                            </div>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Digita <span className="font-mono">[</span> per l&apos;autocomplete. Frecce + Invio/Tab per selezionare.
                            </p>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {systemVariables.slice(0, 8).map((variable) => (
                                    <button
                                        key={variable.code}
                                        type="button"
                                        title={variable.example ? `Es. ${variable.example}` : variable.label}
                                        onClick={() => insertToken(variable.code)}
                                        className="rounded-full bg-surface-100 px-2 py-0.5 font-mono text-xs text-gray-700 hover:bg-primary-100 hover:text-primary-800 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-primary-900/40"
                                    >
                                        [{variable.code}]
                                    </button>
                                ))}
                            </div>
                            <InputError message={errors.formula_string} className="mt-1" />
                        </div>
                    ) : (
                        <div>
                            <InputLabel htmlFor="modal-var-static" value="Valore" />
                            <TextInput
                                id="modal-var-static"
                                type="number"
                                step="0.01"
                                className="mt-1 block w-full"
                                value={staticValue}
                                onChange={(e) => setStaticValue(e.target.value)}
                                required
                            />
                            <InputError message={errors.static_value} className="mt-1" />
                        </div>
                    )}

                    <InputError message={errors.form} className="mt-1" />

                    <div className="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Annulla
                        </button>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Salvataggio…' : 'Crea e usa'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    );
}
