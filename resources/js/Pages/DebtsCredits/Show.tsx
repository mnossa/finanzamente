import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

interface Currency {
    code: string;
    symbol: string;
}

interface Types {
    [key: string]: string;
}

interface Statuses {
    [key: string]: string;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    currency: Currency;
    type: string;
    type_label: string;
    due_date: string | null;
    status: string;
    status_label: string;
    description: string | null;
    created_by: string;
    created_at: string;
    updated_at: string;
}

interface ShowProps {
    debtCredit: DebtCredit;
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
        month: 'long',
        year: 'numeric',
    });
}

import { StatusBadge } from '@/Components/StatusBadge';

export default function Show({ debtCredit, types, statuses }: ShowProps) {
    const isDebt = debtCredit.type === 'debt';

    const handleClose = () => {
        if (confirm('Vuoi segnare questo elemento come chiuso/saldato?')) {
            router.post(route('debts-credits.close', debtCredit.id));
        }
    };

    const handleReopen = () => {
        router.post(route('debts-credits.reopen', debtCredit.id));
    };

    const handleDelete = () => {
        if (confirm('Sei sicuro di voler eliminare questo elemento?')) {
            router.delete(route('debts-credits.destroy', debtCredit.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={route('debts-credits.index')}
                            className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            ←
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {debtCredit.type_label}
                        </h2>
                    </div>
                    <LinkButton href={route('debts-credits.edit', debtCredit.id)} icon={<PencilIcon />}>
                        Modifica
                    </LinkButton>
                </div>
            }
        >
            <Head title={`${debtCredit.type_label} - ${debtCredit.counterparty}`} />

            <div className="py-6">
                <div className="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Card principale */}
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div className="text-center">
                            <div
                                className={clsx(
                                    'mx-auto flex h-16 w-16 items-center justify-center rounded-full',
                                    isDebt
                                        ? 'bg-red-100 dark:bg-red-900/30'
                                        : 'bg-emerald-100 dark:bg-emerald-900/30'
                                )}
                            >
                                <span className="text-3xl">{isDebt ? '📤' : '📥'}</span>
                            </div>
                            <h3 className="mt-4 text-2xl font-bold text-gray-900 dark:text-white">
                                {debtCredit.counterparty}
                            </h3>
                            <div className="mt-2">
                                    <StatusBadge
                                    status={debtCredit.status}
                                    statusLabel={debtCredit.status_label}
                                />
                            </div>
                            <p
                                className={clsx(
                                    'mt-4 text-4xl font-bold',
                                    isDebt ? 'text-red-500' : 'text-emerald-500'
                                )}
                            >
                                {isDebt ? '-' : '+'}
                                {formatCurrency(debtCredit.amount, debtCredit.currency.code)}
                            </p>
                        </div>

                        {/* Dettagli */}
                        <div className="mt-8 space-y-4">
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">
                                    Tipo
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {debtCredit.type_label}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">
                                    Valuta
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {debtCredit.currency.code} ({debtCredit.currency.symbol})
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">
                                    Data di Scadenza
                                </span>
                                <span
                                    className={clsx(
                                        'font-medium',
                                        debtCredit.status === 'overdue'
                                            ? 'text-red-500'
                                            : 'text-gray-900 dark:text-white'
                                    )}
                                >
                                    {debtCredit.due_date
                                        ? formatDate(debtCredit.due_date)
                                        : 'Non impostata'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">
                                    Creato da
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {debtCredit.created_by}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">
                                    Data Creazione
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {debtCredit.created_at}
                                </span>
                            </div>
                            <div className="flex justify-between pb-3">
                                <span className="text-gray-500 dark:text-gray-400">
                                    Ultimo Aggiornamento
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {debtCredit.updated_at}
                                </span>
                            </div>
                        </div>

                        {debtCredit.description && (
                            <div className="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                                <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Note
                                </p>
                                <p className="mt-1 text-gray-900 dark:text-white">
                                    {debtCredit.description}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Azioni */}
                    <div className="flex flex-wrap justify-center gap-3">
                        {debtCredit.status !== 'closed' ? (
                            <button
                                onClick={handleClose}
                                className="inline-flex items-center rounded-lg bg-emerald-600 px-6 py-3 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                ✓ Segna come {isDebt ? 'saldato' : 'incassato'}
                            </button>
                        ) : (
                            <button
                                onClick={handleReopen}
                                className="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-medium text-white hover:bg-blue-700"
                            >
                                ↩ Riapri
                            </button>
                        )}
                        <button
                            onClick={handleDelete}
                            className="inline-flex items-center gap-2 rounded-lg border border-red-300 px-6 py-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            <TrashIcon size={18} /> Elimina
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
