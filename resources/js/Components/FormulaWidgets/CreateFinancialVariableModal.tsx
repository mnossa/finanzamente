import FinancialVariableBuilder, {
    type FinancialVariableDraft,
} from '@/Components/FormulaWidgets/FinancialVariableBuilder';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import axios from 'axios';
import { FormEventHandler, useEffect, useState } from 'react';
import type { FinancialVariableSummary, SystemVariableMeta } from '@/types/formulaWidget';

interface CreateFinancialVariableModalProps {
    open: boolean;
    systemVariables: SystemVariableMeta[];
    userVariables?: FinancialVariableSummary[];
    onClose: () => void;
    onCreated: (variable: FinancialVariableSummary) => void;
}

const EMPTY_DRAFT: FinancialVariableDraft = {
    name: '',
    code: '',
    type: 'formula',
    formula_string: '[period_net]',
    static_value: '',
};

export default function CreateFinancialVariableModal({
    open,
    systemVariables,
    userVariables = [],
    onClose,
    onCreated,
}: CreateFinancialVariableModalProps) {
    const [draft, setDraft] = useState<FinancialVariableDraft>(EMPTY_DRAFT);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) {
            return;
        }

        setDraft(EMPTY_DRAFT);
        setErrors({});
    }, [open]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        axios
            .post(
                route('formula-variables.store'),
                {
                    name: draft.name,
                    code: draft.code || undefined,
                    type: draft.type,
                    formula_string: draft.type === 'formula' ? draft.formula_string : undefined,
                    static_value: draft.type === 'static' ? draft.static_value : undefined,
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
            <div className="relative z-10 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl dark:bg-gray-800">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Crea variabile personalizzata</h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Esplora scenari, componi la formula con drag &amp; drop o scrivila in modalità avanzata.
                </p>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <FinancialVariableBuilder
                        draft={draft}
                        onChange={setDraft}
                        systemVariables={systemVariables}
                        userVariables={userVariables}
                        errors={errors}
                        idPrefix="modal-var"
                    />

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
