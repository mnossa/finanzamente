import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import CardBox from '@/Components/CardBox';
import { IndexPageHeaderActions, IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import DuplicateFormulaWidgetNotice from '@/Components/FormulaWidgets/DuplicateFormulaWidgetNotice';
import { Head, Link, router, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';
import type { PageProps } from '@/types';
import { formulaWidgetDisplayLabel } from '@/utils/formulaWidgetDisplayLabels';

interface IndexProps {
    widgets: FormulaWidgetSummary[];
}

function WidgetCard({ widget, onDelete }: { widget: FormulaWidgetSummary; onDelete: (id: number, name: string) => void }) {
    return (
        <CardBox className="flex flex-col gap-3 p-4 shadow-sm">
            <div className="min-w-0">
                <h3 className="font-semibold text-gray-900 dark:text-white">{widget.name}</h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {formulaWidgetDisplayLabel(widget.display_type)}
                    {widget.financial_variable ? ` · ${widget.financial_variable.name}` : ''}
                </p>
                {widget.source_id ? (
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Installato dalla galleria</p>
                ) : null}
            </div>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                {widget.is_public && widget.share_token && (
                    <p className="text-xs text-gray-500 dark:text-gray-400 sm:min-w-0 sm:flex-1">
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
                <div className="flex shrink-0 flex-col gap-2 sm:ml-auto sm:flex-row">
                    <Link
                        href={route('formula-widgets.edit', widget.id)}
                        className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-surface-300 px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700/60 sm:w-auto sm:py-1.5"
                    >
                        <PencilIcon className="h-4 w-4" />
                        Modifica
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post(route('formula-widgets.pin', widget.id))}
                        className="w-full rounded-lg border border-primary-200 bg-primary-50 px-3 py-2.5 text-sm font-semibold text-primary-700 transition-colors hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-200 dark:hover:bg-primary-900/50 sm:w-auto sm:py-1.5"
                    >
                        Aggiungi alla dashboard
                    </button>
                    <button
                        type="button"
                        onClick={() => onDelete(widget.id, widget.name)}
                        className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-surface-300 px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-red-50 hover:text-red-600 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-red-900/20 sm:w-auto sm:py-1.5"
                        aria-label={`Elimina ${widget.name}`}
                    >
                        <TrashIcon className="h-4 w-4" />
                        Elimina
                    </button>
                </div>
            </div>
        </CardBox>
    );
}

export default function Index({ widgets }: IndexProps) {
    const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);
    const [duplicateDismissed, setDuplicateDismissed] = useState(false);
    const { flash, errors } = usePage<PageProps>().props;
    const duplicateWidget = flash?.duplicateWidget;
    const duplicateErrorMessage = typeof errors?.widget === 'string' ? errors.widget : undefined;
    const showOwnDuplicateNotice = duplicateWidget !== undefined && !duplicateDismissed;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Widget a formula"
                    mobileTitle="Widget"
                    actions={
                        <IndexPageHeaderActions>
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
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Widget a formula" />

            <PageContent maxWidth="5xl">
                <IndexPageMobileToolbar equalWidth={false}>
                    <LinkButton href={route('formula-marketplace.index')} variant="secondary" size="sm">
                        Galleria
                    </LinkButton>
                    <LinkButton href={route('formula-variables.index')} variant="secondary" size="sm">
                        Variabili
                    </LinkButton>
                </IndexPageMobileToolbar>

                {showOwnDuplicateNotice ? (
                    <div className="mb-6">
                        <DuplicateFormulaWidgetNotice
                            widget={duplicateWidget}
                            message={duplicateErrorMessage}
                            onDismiss={() => setDuplicateDismissed(true)}
                        />
                    </div>
                ) : null}

                <p className="mb-6 hidden text-sm text-gray-600 dark:text-gray-400 sm:block">
                    Crea widget personalizzati collegati alle tue variabili finanziarie, oppure installa template dalla{' '}
                    <Link href={route('formula-marketplace.index')} className="font-medium text-primary-600 hover:underline">
                        galleria
                    </Link>
                    . Ogni widget supporta KPI, grafici Recharts (linea, barre, torta, treemap…) e anteprima live in fase di creazione.
                </p>
                <p className="mb-4 text-sm text-gray-600 dark:text-gray-400 sm:hidden">
                    Installa template dalla{' '}
                    <Link href={route('formula-marketplace.index')} className="font-medium text-primary-600 hover:underline">
                        galleria
                    </Link>{' '}
                    o crea un widget personalizzato.
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
                    <div className={clsx('grid gap-3 sm:grid-cols-2 sm:gap-4')}>
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
