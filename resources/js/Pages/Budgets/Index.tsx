import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import CardBox from '@/Components/CardBox';
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
        <CardBox
            className={clsx(
                'p-4 shadow-sm transition-shadow hover:shadow-md',
                !budget.is_active && 'opacity-70'
            )}
        >
            <Link href={route('budgets.show', budget.id)} className="block">
                <div className="flex items-start justify-between">
                    <div className="flex items-center space-x-3">
                        <span className="text-3xl">{budget.category.icon || '📁'}</span>
                        <div>
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                {budget.category.name}
                                {budget.is_exceeded && (
                                    <span className="ml-2 text-red-500">⚠️</span>
                                )}
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {formatDate(budget.period_start)} -{' '}
                                {formatDate(budget.period_end)}
                            </p>
                        </div>
                    </div>
                    {budget.is_active ? (
                        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            Attivo
                        </span>
                    ) : (
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                            Terminato
                        </span>
                    )}
                </div>

                <div className="mt-4">
                    <div className="mb-1 flex items-end justify-between">
                        <span className="text-2xl font-bold text-gray-900 dark:text-white">
                            {formatCurrency(budget.spent, budget.currency.code)}
                        </span>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            / {formatCurrency(budget.amount, budget.currency.code)}
                        </span>
                    </div>
                    <ProgressBar
                        percentage={budget.percentage}
                        isExceeded={budget.is_exceeded}
                    />
                    <div className="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{budget.percentage.toFixed(1)}% utilizzato</span>
                        <span
                            className={clsx(
                                budget.is_exceeded && 'font-semibold text-red-500'
                            )}
                        >
                            {budget.is_exceeded ? 'Sforato di ' : 'Rimangono '}
                            {formatCurrency(
                                Math.abs(budget.remaining),
                                budget.currency.code
                            )}
                        </span>
                    </div>
                </div>
            </Link>

            <div className="mt-3 flex justify-end space-x-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                <Link
                    href={route('budgets.edit', budget.id)}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                    title="Modifica"
                >
                    <PencilIcon size={18} />
                </Link>
                <button
                    onClick={(e) => { e.preventDefault(); onDeleteClick(budget.id, budget.category.name); }}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                    title="Elimina"
                >
                    <TrashIcon size={18} />
                </button>
            </div>
        </CardBox>
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
                        <LinkButton
                            href={route('budgets.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo Budget
                        </LinkButton>
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

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="overflow-hidden rounded-xl bg-gradient-to-br from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                                    <h3 className="text-sm font-medium text-slate-300">
                                        Budget Totale
                                    </h3>
                                    <p className="mt-2 text-3xl font-bold">
                                        {formatCurrency(totalBudgeted)}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-400">
                                        {activeBudgets.length} budget attivi
                                    </p>
                                </div>
                                <div className="overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 p-6 text-white shadow-lg">
                                    <h3 className="text-sm font-medium text-emerald-100">
                                        Speso Finora
                                    </h3>
                                    <p className="mt-2 text-3xl font-bold">
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
                                            ? 'bg-gradient-to-br from-red-500 to-orange-600'
                                            : 'bg-gradient-to-br from-gray-500 to-gray-600'
                                    )}
                                >
                                    <h3 className="text-sm font-medium opacity-80">
                                        Budget Sforati
                                    </h3>
                                    <p className="mt-2 text-3xl font-bold">{exceededCount}</p>
                                    <p className="mt-1 text-sm opacity-80">
                                        {exceededCount === 0
                                            ? 'Ottimo lavoro!'
                                            : 'Attenzione alle spese'}
                                    </p>
                                </div>
                            </div>

                            {/* Budget Attivi */}
                            {activeBudgets.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                        Budget Attivi
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {activeBudgets.map((budget) => (
                                            <BudgetCard key={budget.id} budget={budget} onDeleteClick={openDeleteDialog} />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Budget Passati */}
                            {pastBudgets.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-500 dark:text-gray-400">
                                        Budget Passati
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {pastBudgets.map((budget) => (
                                            <BudgetCard key={budget.id} budget={budget} onDeleteClick={openDeleteDialog} />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
