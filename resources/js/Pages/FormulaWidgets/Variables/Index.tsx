import FormulaStringInput, { type FormulaSuggestion } from '@/Components/FormulaWidgets/FormulaStringInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import CardBox from '@/Components/CardBox';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';
import SystemVariableReferenceList from '@/Components/FormulaWidgets/SystemVariableReferenceList';
import type { FinancialVariableSummary, SystemVariableMeta } from '@/types/formulaWidget';
import { systemVariableToFormulaSuggestion } from '@/utils/formulaVariableHints';

interface IndexProps {
    variables: FinancialVariableSummary[];
    systemVariables: SystemVariableMeta[];
}

function VariableRow({
    variable,
    onDelete,
}: {
    variable: FinancialVariableSummary;
    onDelete: (id: number, name: string) => void;
}) {
    return (
        <CardBox className="flex items-start justify-between gap-3 p-4 shadow-sm">
            <div>
                <h3 className="font-semibold text-gray-900 dark:text-white">{variable.name}</h3>
                <p className="mt-1 font-mono text-sm text-gray-500 dark:text-gray-400">[{variable.code}]</p>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {variable.type === 'formula'
                        ? variable.formula_string
                        : `Valore statico: ${variable.static_value?.toLocaleString('it-IT') ?? '—'}`}
                </p>
            </div>
            <button
                type="button"
                onClick={() => onDelete(variable.id, variable.name)}
                className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                aria-label={`Elimina ${variable.name}`}
            >
                <TrashIcon className="h-4 w-4" />
            </button>
        </CardBox>
    );
}

export default function VariablesIndex({ variables, systemVariables }: IndexProps) {
    const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        code: '',
        type: 'formula',
        static_value: '',
        formula_string: '[household_balance]',
        is_public: false,
    });

    const { financialSystemVariables, contextSystemVariables } = useMemo(() => {
        const financial: SystemVariableMeta[] = [];
        const context: SystemVariableMeta[] = [];

        systemVariables.forEach((variable) => {
            if (variable.category === 'context') {
                context.push(variable);
            } else {
                financial.push(variable);
            }
        });

        return { financialSystemVariables: financial, contextSystemVariables: context };
    }, [systemVariables]);

    const formulaSuggestions = useMemo<FormulaSuggestion[]>(
        () => [
            ...systemVariables.map(systemVariableToFormulaSuggestion),
            ...variables.map((variable) => ({
                code: variable.code,
                label: variable.name,
                hint: 'Tua variabile',
            })),
        ],
        [systemVariables, variables],
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('formula-variables.store'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Variabili finanziarie"
                    backLink={route('formula-widgets.index')}
                />
            }
        >
            <Head title="Variabili finanziarie" />

            <PageContent maxWidth="5xl">
                <div className="mb-6 rounded-xl border border-primary-200 bg-primary-50/60 p-4 text-sm text-gray-700 dark:border-primary-900/40 dark:bg-primary-950/30 dark:text-gray-300">
                    <h2 className="font-semibold text-primary-900 dark:text-primary-100">Come funzionano le variabili</h2>
                    <ul className="mt-2 list-disc space-y-1 pl-4">
                        <li>
                            <strong>Formula</strong>: combina variabili finanziarie, di contesto (anno, mese, giorni…) e personalizzate con{' '}
                            <span className="font-mono">[codice]</span> e operatori (+, −, ×, ÷).
                        </li>
                        <li>
                            <strong>Valore statico</strong>: numero fisso (es. soglia personale o budget annuo).
                        </li>
                        <li>Le variabili create qui possono essere collegate ai widget nella pagina «Nuovo widget a formula».</li>
                    </ul>
                </div>

                <SectionCard>
                    <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Nuova variabile</h2>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="name" value="Nome" />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="code" value="Codice (opzionale)" />
                                <TextInput
                                    id="code"
                                    className="mt-1 block w-full font-mono"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    placeholder="es. saldo_personale"
                                />
                                <InputError message={errors.code} className="mt-1" />
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="type" value="Tipo" />
                            <select
                                id="type"
                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                            >
                                <option value="formula">Formula</option>
                                <option value="static">Valore statico</option>
                            </select>
                        </div>

                        {data.type === 'formula' ? (
                            <div>
                                <InputLabel htmlFor="formula_string" value="Formula" />
                                <div className="mt-1">
                                    <FormulaStringInput
                                        id="formula_string"
                                        value={data.formula_string}
                                        onChange={(value) => setData('formula_string', value)}
                                        suggestions={formulaSuggestions}
                                        required
                                        placeholder="es. [total_income] - [total_expenses]"
                                    />
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Digita <span className="font-mono">[</span> per l&apos;autocomplete delle variabili disponibili.
                                </p>
                                <InputError message={errors.formula_string} className="mt-1" />
                            </div>
                        ) : (
                            <div>
                                <InputLabel htmlFor="static_value" value="Valore" />
                                <TextInput
                                    id="static_value"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.static_value}
                                    onChange={(e) => setData('static_value', e.target.value)}
                                    required
                                />
                                <InputError message={errors.static_value} className="mt-1" />
                            </div>
                        )}

                        <PrimaryButton disabled={processing}>Salva variabile</PrimaryButton>
                    </form>
                </SectionCard>

                <div className="mt-8">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Le tue variabili</h2>
                    <div className="mt-4 space-y-3">
                        {variables.length === 0 ? (
                            <p className="text-sm text-gray-500 dark:text-gray-400">Nessuna variabile personalizzata.</p>
                        ) : (
                            variables.map((variable) => (
                                <VariableRow
                                    key={variable.id}
                                    variable={variable}
                                    onDelete={(id, name) => setDeleteTarget({ id, name })}
                                />
                            ))
                        )}
                    </div>
                </div>

                <div className="mt-8 space-y-6">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Variabili finanziarie</h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Saldo, entrate, uscite e metriche collegate ai tuoi dati.
                        </p>
                        <SystemVariableReferenceList
                            variables={financialSystemVariables}
                            className="mt-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                        />
                    </div>

                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Variabili di contesto</h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Data, calendario e durata del periodo — utili per medie giornaliere, proiezioni e prorata.
                        </p>
                        <SystemVariableReferenceList
                            variables={contextSystemVariables}
                            className="mt-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                        />
                    </div>
                </div>

                <p className="mt-6 text-sm text-gray-600 dark:text-gray-400">
                    Torna ai{' '}
                    <Link href={route('formula-widgets.index')} className="font-medium text-primary-600 hover:underline">
                        widget a formula
                    </Link>
                    .
                </p>
            </PageContent>

            <ConfirmDeleteDialog
                open={deleteTarget !== null}
                title="Elimina variabile"
                description={deleteTarget ? `Vuoi eliminare «${deleteTarget.name}»? I widget collegati potrebbero smettere di funzionare.` : undefined}
                onConfirm={() => {
                    if (deleteTarget) {
                        router.delete(route('formula-variables.destroy', deleteTarget.id), {
                            onFinish: () => setDeleteTarget(null),
                        });
                    }
                }}
                onCancel={() => setDeleteTarget(null)}
            />
        </AuthenticatedLayout>
    );
}
