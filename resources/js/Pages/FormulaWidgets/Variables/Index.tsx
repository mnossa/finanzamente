import FinancialVariableBuilder, {
    type FinancialVariableDraft,
} from '@/Components/FormulaWidgets/FinancialVariableBuilder';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SectionCard from '@/Components/SectionCard';
import CardBox from '@/Components/CardBox';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import SystemVariableReferenceList from '@/Components/FormulaWidgets/SystemVariableReferenceList';
import type { FinancialVariableSummary, SystemVariableMeta } from '@/types/formulaWidget';

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

    const { data, setData, post, processing, errors, reset } = useForm<FinancialVariableDraft>({
        name: '',
        code: '',
        type: 'formula',
        static_value: '',
        formula_string: '[period_net]',
    });

    const financialSystemVariables = systemVariables.filter((variable) => variable.category !== 'context');
    const contextSystemVariables = systemVariables.filter((variable) => variable.category === 'context');

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
                            <strong>Esplora scenari</strong> per partire da casi comuni come il bilancio conto, poi personalizza.
                        </li>
                        <li>
                            <strong>Componi</strong> la formula trascinando variabili dalla palette o scrivila in modalità avanzata.
                        </li>
                        <li>
                            Le variabili create qui si collegano ai widget nella pagina «Nuovo widget a formula».
                        </li>
                    </ul>
                </div>

                <SectionCard>
                    <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Nuova variabile</h2>
                    <form onSubmit={submit} className="space-y-4">
                        <FinancialVariableBuilder
                            draft={data}
                            onChange={setData}
                            systemVariables={systemVariables}
                            userVariables={variables}
                            errors={errors}
                            idPrefix="index-var"
                        />
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
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Variabili finanziarie di sistema</h2>
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
