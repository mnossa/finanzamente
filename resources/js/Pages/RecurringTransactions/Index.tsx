import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { formatRecurrenceScheduleRule } from '@/Components/RecurrenceScheduleFields';

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
    day_of_month_mode: 'start_date' | 'fixed' | 'last_day';
    day_of_month: number | null;
    non_working_day_policy: 'postpone' | 'anticipate' | 'keep';
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
        <div className="flex items-center border-b border-gray-100 py-3 last:border-0 -mx-4 px-3 sm:px-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
            <div
                className={clsx('mr-3 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base', !rt.is_active && 'opacity-50')}
                style={{ backgroundColor: rt.category?.color ? `${rt.category.color}20` : isIncome ? '#22c55e20' : '#ef444420' }}
            >
                {rt.category?.icon || (isIncome ? '💰' : '💸')}
            </div>
            <Link href={route('recurring-transactions.show', rt.id)} className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-1">
                    <p className={clsx('text-sm font-medium text-gray-900 dark:text-white', !rt.is_active && 'line-through opacity-50')}>
                        {rt.description || rt.category?.name || 'Ricorrenza'}
                    </p>
                    <FrequencyBadge frequency={rt.frequency} frequencyLabel={rt.frequency_label} />
                    {!rt.is_active && (
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-700">Terminata</span>
                    )}
                </div>
                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {rt.account.name} · {rt.next_due_date ? `Prossima: ${formatDate(rt.next_due_date)}` : `Dal ${formatDate(rt.start_date)}`}
                </p>
                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {formatRecurrenceScheduleRule(
                        rt.frequency,
                        rt.day_of_month_mode,
                        rt.day_of_month,
                        rt.non_working_day_policy,
                    )}
                </p>
            </Link>
            <div className="ml-2 flex shrink-0 items-center gap-1 sm:gap-2">
                <p className={clsx('text-base font-semibold tabular-nums', !rt.is_active && 'opacity-50', isIncome ? 'text-green-500' : 'text-red-500')}>
                    {isIncome ? '+' : ''}{formatCurrency(rt.amount, rt.account.currency_code)}
                </p>
                <div className="hidden sm:flex items-center gap-1">
                    <Link href={route('recurring-transactions.show', rt.id)} className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400" title="Visualizza">
                        <EyeIcon size={16} />
                    </Link>
                    <Link href={route('recurring-transactions.edit', rt.id)} className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400" title="Modifica">
                        <PencilIcon size={16} />
                    </Link>
                    <button onClick={() => onDeleteClick(rt.id, rt.description || rt.category?.name || 'questa ricorrenza')} className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400" title="Elimina">
                        <TrashIcon size={16} />
                    </button>
                </div>
                <span className="sm:hidden text-gray-300 dark:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </span>
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
                <PageHeader title="Transazioni Ricorrenti" />
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

            <PageContent maxWidth="7xl">
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Ricorrenze" icon={<span className="text-sm leading-none">🔁</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Gestisci operazioni periodiche automatiche e controlla le prossime scadenze.
                            </p>
                        </div>
                    </SectionCard>
                    <IndexPageMobileToolbar>
                        <LinkButton
                            href={route('recurrence-detection.index')}
                            variant="secondary"
                            size="sm"
                            className="w-full justify-center sm:w-auto"
                        >
                            🔍 Ricorrenze Rilevate
                        </LinkButton>
                    </IndexPageMobileToolbar>
                    <div className="mb-4 hidden flex-wrap items-center gap-2 lg:flex">
                        <LinkButton href={route('recurrence-detection.index')}>
                            🔍 Ricorrenze Rilevate
                        </LinkButton>
                        <LinkButton href={route('recurring-transactions.create')} icon={<PlusIcon />}>
                            Nuova Ricorrenza
                        </LinkButton>
                    </div>
                    {/* Riepilogo frequenze */}
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {Object.entries(frequencies).map(([key, label]) => {
                            const count = activeTransactions.filter((rt) => rt.frequency === key).length;
                            const total = activeTransactions
                                .filter((rt) => rt.frequency === key)
                                .reduce((sum, rt) => sum + rt.amount, 0);
                            
                            return (
                                <CardBox
                                    key={key}
                                    className="p-4 shadow-sm"
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
                                </CardBox>
                            );
                        })}
                    </div>

                    {/* Lista Transazioni Ricorrenti Attive */}
                    <CardBox className="overflow-hidden shadow-sm">
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
                    </CardBox>

                    {/* Lista Transazioni Ricorrenti Terminate */}
                    {inactiveTransactions.length > 0 && (
                        <CardBox className="overflow-hidden shadow-sm">
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
                        </CardBox>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
