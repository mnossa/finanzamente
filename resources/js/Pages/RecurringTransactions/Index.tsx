import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    type: 'income' | 'expense';
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Frequencies {
    [key: string]: string;
}

interface RecurringTransaction {
    id: number;
    amount: number;
    frequency: string;
    frequency_label: string;
    start_date: string;
    end_date: string | null;
    description: string | null;
    next_due_date: string | null;
    is_active: boolean;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
}

interface IndexProps {
    recurringTransactions: RecurringTransaction[];
    frequencies: Frequencies;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function FrequencyBadge({ frequency, frequencyLabel }: { frequency: string; frequencyLabel: string }) {
    const colors: Record<string, string> = {
        daily: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        weekly: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        monthly: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        yearly: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    };

    const icons: Record<string, string> = {
        daily: '📅',
        weekly: '📆',
        monthly: '🗓️',
        yearly: '📋',
    };

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                colors[frequency] || colors.monthly
            )}
        >
            {icons[frequency]} {frequencyLabel}
        </span>
    );
}

function RecurringTransactionRow({ rt, onDeleteClick }: { rt: RecurringTransaction; onDeleteClick: (id: number, description: string) => void }) {
    const isIncome = rt.amount > 0;

    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-4 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50 -mx-4 px-4 transition-colors">
            <div className="flex items-center space-x-3">
                <div
                    className={clsx(
                        'flex h-10 w-10 items-center justify-center rounded-full text-lg',
                        !rt.is_active && 'opacity-50'
                    )}
                    style={{
                        backgroundColor: rt.category?.color
                            ? `${rt.category.color}20`
                            : isIncome
                            ? '#22c55e20'
                            : '#ef444420',
                    }}
                >
                    {rt.category?.icon || (isIncome ? '💰' : '💸')}
                </div>
                <div>
                    <div className="flex items-center gap-2">
                        <p className={clsx(
                            'font-medium text-gray-900 dark:text-white',
                            !rt.is_active && 'line-through opacity-50'
                        )}>
                            {rt.description || rt.category?.name || 'Ricorrenza'}
                        </p>
                        <FrequencyBadge frequency={rt.frequency} frequencyLabel={rt.frequency_label} />
                        {!rt.is_active && (
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700">
                                Terminata
                            </span>
                        )}
                    </div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {rt.account.name} • 
                        {rt.next_due_date ? (
                            <span className="ml-1">Prossima: {formatDate(rt.next_due_date)}</span>
                        ) : (
                            <span className="ml-1">Dal {formatDate(rt.start_date)}</span>
                        )}
                    </p>
                </div>
            </div>
            <div className="flex items-center space-x-4">
                <p
                    className={clsx(
                        'text-lg font-semibold',
                        !rt.is_active && 'opacity-50',
                        isIncome ? 'text-green-500' : 'text-red-500'
                    )}
                >
                    {isIncome ? '+' : ''}
                    {formatCurrency(rt.amount, rt.account.currency_code)}
                </p>
                <div className="flex space-x-2">
                    <Link
                        href={route('recurring-transactions.show', rt.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={18} />
                    </Link>
                    <Link
                        href={route('recurring-transactions.edit', rt.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                    >
                        <PencilIcon size={18} />
                    </Link>
                    <button
                        onClick={() => onDeleteClick(rt.id, rt.description || rt.category?.name || 'questa ricorrenza')}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                    >
                        <TrashIcon size={18} />
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function Index({ recurringTransactions, frequencies }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('recurring-transactions.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const activeTransactions = recurringTransactions.filter((rt) => rt.is_active);
    const inactiveTransactions = recurringTransactions.filter((rt) => !rt.is_active);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Transazioni Ricorrenti"
                    actions={
                        <LinkButton
                            href={route('recurring-transactions.create')}
                            icon={<PlusIcon />}
                        >
                            Nuova Ricorrenza
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Transazioni Ricorrenti" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare ${deleteTarget.description}?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Riepilogo frequenze */}
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {Object.entries(frequencies).map(([key, label]) => {
                            const count = activeTransactions.filter((rt) => rt.frequency === key).length;
                            const total = activeTransactions
                                .filter((rt) => rt.frequency === key)
                                .reduce((sum, rt) => sum + rt.amount, 0);
                            
                            return (
                                <div
                                    key={key}
                                    className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800"
                                >
                                    <FrequencyBadge frequency={key} frequencyLabel={label} />
                                    <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                                        {count}
                                    </p>
                                    <p className={clsx(
                                        'text-sm font-medium',
                                        total >= 0 ? 'text-green-500' : 'text-red-500'
                                    )}>
                                        {formatCurrency(total)}
                                    </p>
                                </div>
                            );
                        })}
                    </div>

                    {/* Lista Transazioni Ricorrenti Attive */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                            <h3 className="font-medium text-gray-900 dark:text-white">
                                Attive ({activeTransactions.length})
                            </h3>
                        </div>
                        {activeTransactions.length > 0 ? (
                            <div className="p-4">
                                {activeTransactions.map((rt) => (
                                    <RecurringTransactionRow key={rt.id} rt={rt} onDeleteClick={openDeleteDialog} />
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon="🔄"
                                title="Nessuna transazione ricorrente"
                                description="Crea una transazione ricorrente per automatizzare le operazioni periodiche."
                                createUrl={route('recurring-transactions.create')}
                                createLabel="Nuova Ricorrenza"
                            />
                        )}
                    </div>

                    {/* Lista Transazioni Ricorrenti Terminate */}
                    {inactiveTransactions.length > 0 && (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                <h3 className="font-medium text-gray-500 dark:text-gray-400">
                                    Terminate ({inactiveTransactions.length})
                                </h3>
                            </div>
                            <div className="p-4">
                                {inactiveTransactions.map((rt) => (
                                    <RecurringTransactionRow key={rt.id} rt={rt} onDeleteClick={openDeleteDialog} />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
