import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import CardBox from '@/Components/CardBox';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';

interface IndexProps {
    widgets: FormulaWidgetSummary[];
}

const DISPLAY_LABELS: Record<string, string> = {
    kpi: 'KPI',
    line: 'Linea',
    area: 'Area',
    bar: 'Barre verticali',
    horizontal_bar: 'Barre orizzontali',
    stacked_bar: 'Barre impilate',
    pie: 'Torta',
    treemap: 'Treemap',
    progress: 'Avanzamento',
};

function WidgetCard({ widget, onDelete }: { widget: FormulaWidgetSummary; onDelete: (id: number, name: string) => void }) {
    return (
        <CardBox className="flex flex-col gap-3 p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-gray-900 dark:text-white">{widget.name}</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {DISPLAY_LABELS[widget.display_type] ?? widget.display_type}
                        {widget.financial_variable ? ` · ${widget.financial_variable.name}` : ''}
                    </p>
                </div>
                <div className="flex shrink-0 gap-1">
                    <button
                        type="button"
                        onClick={() => router.post(route('formula-widgets.pin', widget.id))}
                        className="rounded-lg border border-surface-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-surface-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Aggiungi alla dashboard
                    </button>
                    <button
                        type="button"
                        onClick={() => onDelete(widget.id, widget.name)}
                        className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                        aria-label={`Elimina ${widget.name}`}
                    >
                        <TrashIcon className="h-4 w-4" />
                    </button>
                </div>
            </div>
            {widget.is_public && widget.share_token && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    Condiviso:{' '}
                    <a
                        href={route('shared.formula.show', widget.share_token)}
                        className="font-medium text-primary-600 hover:underline"
                        target="_blank"
                        rel="noreferrer"
                    >
                        link pubblico
                    </a>
                </p>
            )}
        </CardBox>
    );
}

export default function Index({ widgets }: IndexProps) {
    const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Widget a formula"
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <LinkButton href={route('formula-marketplace.index')} variant="secondary">
                                Galleria
                            </LinkButton>
                            <LinkButton href={route('formula-variables.index')} variant="secondary">
                                Variabili
                            </LinkButton>
                            <LinkButton href={route('formula-widgets.create')}>
                                <PlusIcon className="h-4 w-4" />
                                Nuovo widget
                            </LinkButton>
                        </div>
                    }
                />
            }
        >
            <Head title="Widget a formula" />

            <PageContent maxWidth="5xl">
                <p className="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    Crea widget personalizzati collegati alle tue variabili finanziarie, oppure installa template dalla{' '}
                    <Link href={route('formula-marketplace.index')} className="font-medium text-primary-600 hover:underline">
                        galleria
                    </Link>
                    . Ogni widget supporta KPI, grafici Recharts (linea, barre, torta, treemap…) e anteprima live in fase di creazione.
                </p>

                {widgets.length === 0 ? (
                    <EmptyState
                        icon="📐"
                        title="Nessun widget personalizzato"
                        description="Installa un template dalla galleria o crea il tuo primo widget."
                        showCreateButton={false}
                    >
                        <div className="mt-4 flex flex-wrap justify-center gap-2">
                            <LinkButton href={route('formula-marketplace.index')}>Apri galleria</LinkButton>
                            <LinkButton href={route('formula-widgets.create')} variant="secondary">
                                Crea widget
                            </LinkButton>
                        </div>
                    </EmptyState>
                ) : (
                    <div className={clsx('grid gap-4 sm:grid-cols-2')}>
                        {widgets.map((widget) => (
                            <WidgetCard key={widget.id} widget={widget} onDelete={(id, name) => setDeleteTarget({ id, name })} />
                        ))}
                    </div>
                )}
            </PageContent>

            <ConfirmDeleteDialog
                open={deleteTarget !== null}
                title="Elimina widget"
                description={deleteTarget ? `Vuoi rimuovere «${deleteTarget.name}»? Verrà eliminato anche dalla dashboard.` : undefined}
                onConfirm={() => {
                    if (deleteTarget) {
                        router.delete(route('formula-widgets.destroy', deleteTarget.id), {
                            onFinish: () => setDeleteTarget(null),
                        });
                    }
                }}
                onCancel={() => setDeleteTarget(null)}
            />
        </AuthenticatedLayout>
    );
}
