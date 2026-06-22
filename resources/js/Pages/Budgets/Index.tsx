import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PlanningHubNav from '@/Components/PlanningHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import IndexCardGrid from '@/Components/Index/IndexCardGrid';
import IndexEntityCard, {
    IndexEntityCardFooterButton,
    IndexEntityCardFooterLink,
} from '@/Components/Index/IndexEntityCard';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import CardBox from '@/Components/CardBox';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { moneyTabular } from '@/utils/moneyGridClasses';
import { ProgressBar } from '@/Components/ProgressBar';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Category {
    id: number;
    name: string;
    icon: string | null;
}

interface Currency {
    code: string;
    symbol: string;
}

interface Budget {
    id: number;
    category: Category;
    amount: number;
    spent: number;
    remaining: number;
    percentage: number;
    currency: Currency;
    period_start: string;
    period_end: string;
    description: string | null;
    is_exceeded: boolean;
    is_active: boolean;
}

interface IndexProps {
    budgets: Budget[];
}




function BudgetCard({ budget, onDeleteClick }: { budget: Budget; onDeleteClick: (id: number, name: string) => void }) {
    return (
        <IndexEntityCard
            href={route('budgets.show', budget.id)}
            dimmed={!budget.is_active}
            icon={budget.category.icon || '📁'}
            title={
                <>
                    {budget.category.name}
                    {budget.is_exceeded && <span className="ml-1.5">⚠️</span>}
                </>
            }
            subtitle={`${formatDate(budget.period_start)} - ${formatDate(budget.period_end)}`}
            aside={
                budget.is_active ? (
                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                        Attivo
                    </span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                        Terminato
                    </span>
                )
            }
            extra={
                <>
                    <div className="mb-1 flex items-end justify-between">
                        <span className={clsx('text-lg font-bold text-gray-900 sm:text-xl dark:text-white', moneyTabular)}>
                            {formatCurrency(budget.spent, budget.currency.code)}
                        </span>
                        <span className={clsx('text-xs text-gray-500 sm:text-sm dark:text-gray-400', moneyTabular)}>
                            / {formatCurrency(budget.amount, budget.currency.code)}
                        </span>
                    </div>
                    <ProgressBar percentage={budget.percentage} isExceeded={budget.is_exceeded} />
                    <div className="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{budget.percentage.toFixed(1)}% utilizzato</span>
                        <span
                            className={clsx(
                                moneyTabular,
                                budget.is_exceeded && 'font-semibold text-red-500',
                            )}
                        >
                            {budget.is_exceeded ? 'Sforato di ' : 'Rimangono '}
                            {formatCurrency(Math.abs(budget.remaining), budget.currency.code)}
                        </span>
                    </div>
                </>
            }
            footer={
                <>
                    <IndexEntityCardFooterLink href={route('budgets.edit', budget.id)} title="Modifica">
                        <PencilIcon size={16} />
                    </IndexEntityCardFooterLink>
                    <IndexEntityCardFooterButton
                        onClick={() => onDeleteClick(budget.id, budget.category.name)}
                        title="Elimina"
                        className="hover:text-red-600 dark:hover:text-red-400"
                    >
                        <TrashIcon size={16} />
                    </IndexEntityCardFooterButton>
                </>
            }
        />
    );
}

export default function Index({ budgets }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; name: string } | null>(null);

    const openDeleteDialog = (id: number, name: string) => {
        setDeleteTarget({ id, name });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('budgets.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const activeBudgets = budgets.filter((b) => b.is_active);
    const pastBudgets = budgets.filter((b) => !b.is_active);

    // Calcoli per il riepilogo
    const totalBudgeted = activeBudgets.reduce((sum, b) => sum + b.amount, 0);
    const totalSpent = activeBudgets.reduce((sum, b) => sum + b.spent, 0);
    const exceededCount = activeBudgets.filter((b) => b.is_exceeded).length;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Budget"
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('budgets.create')} icon={<PlusIcon />}>
                                Nuovo Budget
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Budget" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare il budget per "${deleteTarget.name}"?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <PageContent maxWidth="7xl">
                    <PlanningHubNav active="budgets" />
                    <IndexIntroSection
                        label="Pianificazione budget"
                        icon={<span className="text-sm leading-none">📊</span>}
                        description="Monitora spese, progressi e superamenti in tempo reale per ogni categoria."
                    />
                    {budgets.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="📊"
                                title="Nessun budget trovato"
                                description="Crea il tuo primo budget per monitorare le tue spese."
                                createUrl={route('budgets.create')}
                                createLabel="Crea il tuo primo budget"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Riepilogo */}
                            <IndexKpiStrip columns={3}>
                                <div className="overflow-hidden rounded-xl bg-linear-to-br from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                                    <h3 className="text-sm font-medium text-slate-300">
                                        Budget Totale
                                    </h3>
                                    <p className={clsx('mt-2 text-3xl font-bold', moneyTabular)}>
                                        {formatCurrency(totalBudgeted)}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-400">
                                        {activeBudgets.length} budget attivi
                                    </p>
                                </div>
                                <div className="overflow-hidden rounded-xl bg-linear-to-br from-emerald-500 to-teal-600 p-6 text-white shadow-lg">
                                    <h3 className="text-sm font-medium text-emerald-100">
                                        Speso Finora
                                    </h3>
                                    <p className={clsx('mt-2 text-3xl font-bold', moneyTabular)}>
                                        {formatCurrency(totalSpent)}
                                    </p>
                                    <p className="mt-1 text-sm text-emerald-200">
                                        {totalBudgeted > 0
                                            ? ((totalSpent / totalBudgeted) * 100).toFixed(1)
                                            : 0}
                                        % del totale
                                    </p>
                                </div>
                                <div
                                    className={clsx(
                                        'overflow-hidden rounded-xl p-6 text-white shadow-lg',
                                        exceededCount > 0
                                            ? 'bg-linear-to-br from-red-500 to-orange-600'
                                            : 'bg-linear-to-br from-gray-500 to-gray-600'
                                    )}
                                >
                                    <h3 className="text-sm font-medium opacity-80">
                                        Budget Sforati
                                    </h3>
                                    <p className={clsx('mt-2 text-3xl font-bold', moneyTabular)}>{exceededCount}</p>
                                    <p className="mt-1 text-sm opacity-80">
                                        {exceededCount === 0
                                            ? 'Ottimo lavoro!'
                                            : 'Attenzione alle spese'}
                                    </p>
                                </div>
                            </IndexKpiStrip>

                            {/* Budget Attivi */}
                            {activeBudgets.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                        Budget Attivi
                                    </h3>
                                    <IndexCardGrid>
                                        {activeBudgets.map((budget) => (
                                            <BudgetCard key={budget.id} budget={budget} onDeleteClick={openDeleteDialog} />
                                        ))}
                                    </IndexCardGrid>
                                </div>
                            )}

                            {/* Budget Passati */}
                            {pastBudgets.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-500 dark:text-gray-400">
                                        Budget Passati
                                    </h3>
                                    <IndexCardGrid>
                                        {pastBudgets.map((budget) => (
                                            <BudgetCard key={budget.id} budget={budget} onDeleteClick={openDeleteDialog} />
                                        ))}
                                    </IndexCardGrid>
                                </div>
                            )}
                        </>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
