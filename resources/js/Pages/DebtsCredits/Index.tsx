import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';

interface Currency {
    code: string;
    symbol: string;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    remaining_amount: number;
    currency: Currency;
    type: string;
    type_label: string;
    due_date: string | null;
    status: string;
    status_label: string;
    description: string | null;
    created_by: string;
    created_at: string;
}

interface Summary {
    total_debts: number;
    total_credits: number;
    overdue_count: number;
}

interface Types {
    [key: string]: string;
}

interface Statuses {
    [key: string]: string;
}

interface IndexProps {
    debtsCredits: DebtCredit[];
    summary: Summary;
    types: Types;
    statuses: Statuses;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

import { StatusBadge } from '@/Components/StatusBadge';

function DebtCreditCard({ item }: { item: DebtCredit }) {
    const handleClose = () => {
        if (confirm('Vuoi segnare questo elemento come chiuso/saldato?')) {
            router.post(route('debts-credits.close', item.id));
        }
    };

    const handleReopen = () => {
        router.post(route('debts-credits.reopen', item.id));
    };

    const handleDelete = () => {
        if (confirm('Sei sicuro di voler eliminare questo elemento?')) {
            router.delete(route('debts-credits.destroy', item.id));
        }
    };

    const isDebt = item.type === 'debt';

    return (
        <CardBox
            className={clsx(
                'p-4 shadow-sm transition-shadow hover:shadow-md',
                item.status === 'closed' && 'opacity-70'
            )}
        >
            <Link href={route('debts-credits.show', item.id)} className="block">
                <div className="flex items-start justify-between">
                    <div className="flex items-center space-x-3">
                        <div
                            className={clsx(
                                'flex h-10 w-10 items-center justify-center rounded-full',
                                isDebt
                                    ? 'bg-red-100 dark:bg-red-900/30'
                                    : 'bg-emerald-100 dark:bg-emerald-900/30'
                            )}
                        >
                            <span className="text-lg">{isDebt ? '📤' : '📥'}</span>
                        </div>
                        <div>
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                {item.counterparty}
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {item.type_label}
                                {item.due_date && ` • Scadenza: ${formatDate(item.due_date)}`}
                            </p>
                        </div>
                    </div>
                    <div className="text-right">
                        <p
                            className={clsx(
                                'text-lg font-bold',
                                isDebt ? 'text-red-500' : 'text-emerald-500'
                            )}
                        >
                            {isDebt ? '-' : '+'}
                            {formatCurrency(item.remaining_amount, item.currency.code)}
                        </p>
                        <StatusBadge status={item.status} statusLabel={item.status_label} />
                    </div>
                </div>
                {item.description && (
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                        {item.description}
                    </p>
                )}
            </Link>

            <div className="mt-3 flex justify-end space-x-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                {item.status !== 'closed' ? (
                    <button
                        onClick={handleClose}
                        className="rounded px-3 py-1 text-sm text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                    >
                        ✓ Chiudi
                    </button>
                ) : (
                    <button
                        onClick={handleReopen}
                        className="rounded px-3 py-1 text-sm text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                    >
                        ↩ Riapri
                    </button>
                )}
                <Link
                    href={route('debts-credits.edit', item.id)}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                    title="Modifica"
                >
                    <PencilIcon size={18} />
                </Link>
                <button
                    onClick={handleDelete}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                    title="Elimina"
                >
                    <TrashIcon size={18} />
                </button>
            </div>
        </CardBox>
    );
}

export default function Index({ debtsCredits, summary, types, statuses }: IndexProps) {
    const openItems = debtsCredits.filter((item) => item.status !== 'closed');
    const closedItems = debtsCredits.filter((item) => item.status === 'closed');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Debiti e Crediti"
                    actions={
                        <LinkButton
                            href={route('debts-credits.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Debiti e Crediti" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {debtsCredits.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="💸"
                                title="Nessun debito o credito trovato"
                                description="Tieni traccia dei soldi che devi o che ti devono."
                                createUrl={route('debts-credits.create')}
                                createLabel="Aggiungi il primo"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Riepilogo */}
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="overflow-hidden rounded-xl bg-gradient-to-br from-red-500 to-rose-600 p-6 text-white shadow-lg">
                                    <h3 className="text-sm font-medium text-red-100">
                                        Debiti Aperti
                                    </h3>
                                    <p className="mt-2 text-3xl font-bold">
                                        {formatCurrency(summary.total_debts)}
                                    </p>
                                    <p className="mt-1 text-sm text-red-200">
                                        Soldi che devi
                                    </p>
                                </div>
                                <div className="overflow-hidden rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 p-6 text-white shadow-lg">
                                    <h3 className="text-sm font-medium text-emerald-100">
                                        Crediti Aperti
                                    </h3>
                                    <p className="mt-2 text-3xl font-bold">
                                        {formatCurrency(summary.total_credits)}
                                    </p>
                                    <p className="mt-1 text-sm text-emerald-200">
                                        Soldi che ti devono
                                    </p>
                                </div>
                                <div
                                    className={clsx(
                                        'overflow-hidden rounded-xl p-6 text-white shadow-lg',
                                        summary.total_credits - summary.total_debts >= 0
                                            ? 'bg-gradient-to-br from-blue-500 to-emerald-600'
                                            : 'bg-gradient-to-br from-amber-500 to-orange-600'
                                    )}
                                >
                                    <h3 className="text-sm font-medium opacity-80">
                                        Bilancio Netto
                                    </h3>
                                    <p className="mt-2 text-3xl font-bold">
                                        {formatCurrency(
                                            summary.total_credits - summary.total_debts
                                        )}
                                    </p>
                                    <p className="mt-1 text-sm opacity-80">
                                        {summary.overdue_count > 0 && (
                                            <span className="font-semibold">
                                                ⚠️ {summary.overdue_count} scaduti
                                            </span>
                                        )}
                                    </p>
                                </div>
                            </div>

                            {/* Elementi Aperti */}
                            {openItems.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                        Aperti ({openItems.length})
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {openItems.map((item) => (
                                            <DebtCreditCard key={item.id} item={item} />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Elementi Chiusi */}
                            {closedItems.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-500 dark:text-gray-400">
                                        Chiusi ({closedItems.length})
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {closedItems.map((item) => (
                                            <DebtCreditCard key={item.id} item={item} />
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
