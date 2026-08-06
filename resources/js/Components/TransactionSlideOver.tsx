import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import React, { Fragment } from 'react';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { formatCurrency, formatDate } from '@/utils/format';
import type { TransactionListIndexQuery } from '@/Components/TransactionListRow';

export interface TransactionSlideOverTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    created_at: string;
    category: {
        id: number;
        name: string;
        color: string | null;
        icon: string | null;
        type: 'income' | 'expense';
    } | null;
    account: {
        id: number;
        name: string;
        currency_code: string;
    };
    user: {
        id: number;
        name: string;
    };
    tags: Array<{
        id: number;
        name: string;
        color: string | null;
    }>;
    transfer_id: number | null;
    refund_id: number | null;
    recurring_transaction_id: number | null;
    recurring_summary: {
        id: number;
        description: string | null;
        frequency: string;
    } | null;
    investment_id: number | null;
    is_investment: boolean;
    is_pac?: boolean;
    pac_summary?: {
        id: number;
        asset_name: string | null;
    } | null;
}

interface Props {
    open: boolean;
    loading: boolean;
    error: string | null;
    transaction: TransactionSlideOverTransaction | null;
    indexQuery: TransactionListIndexQuery;
    onClose: () => void;
}

function returnIndexQueryJson(q: TransactionListIndexQuery): string {
    return Object.keys(q).length === 0 ? '' : JSON.stringify(q);
}

export default function TransactionSlideOver({
    open,
    loading,
    error,
    transaction,
    indexQuery,
    onClose,
}: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);

    const handleDelete = () => {
        if (!transaction) {
            return;
        }
        const payload = returnIndexQueryJson(indexQuery);
        router.delete(route('transactions.destroy', transaction.id), {
            ...(payload ? { data: { return_index_query: payload } } : {}),
            onSuccess: () => {
                setDeleteDialogOpen(false);
                onClose();
            },
        });
    };

    const isIncome = transaction != null && transaction.amount > 0;
    const isTransfer = transaction?.transfer_id != null;
    const isRefund = transaction?.refund_id != null;
    const title =
        transaction?.description ||
        transaction?.category?.name ||
        (loading ? 'Caricamento…' : 'Dettaglio transazione');

    return (
        <>
            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description="Sei sicuro di voler eliminare questa transazione?"
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleDelete}
                onCancel={() => setDeleteDialogOpen(false)}
            />

            <Transition show={open} as={Fragment}>
                <Dialog as="div" className="relative z-50" onClose={onClose}>
                    <TransitionChild
                        as={Fragment}
                        enter="ease-out duration-200"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" aria-hidden />
                    </TransitionChild>

                    <div className="fixed inset-0 overflow-hidden">
                        <div className="absolute inset-0 overflow-hidden">
                            <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                                <TransitionChild
                                    as={Fragment}
                                    enter="transform transition ease-out duration-200"
                                    enterFrom="translate-x-full"
                                    enterTo="translate-x-0"
                                    leave="transform transition ease-in duration-150"
                                    leaveFrom="translate-x-0"
                                    leaveTo="translate-x-full"
                                >
                                    <DialogPanel
                                        className="pointer-events-auto flex h-full w-screen max-w-md flex-col bg-white shadow-xl dark:bg-slate-900"
                                        data-testid="transaction-slide-over"
                                    >
                                        <div className="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                                            <div className="min-w-0">
                                                <DialogTitle className="truncate text-base font-semibold text-gray-900 dark:text-white">
                                                    {title}
                                                </DialogTitle>
                                                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                    Dettaglio senza lasciare l&apos;elenco
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={onClose}
                                                className="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                                aria-label="Chiudi dettaglio"
                                                data-testid="transaction-slide-over-close"
                                            >
                                                <span aria-hidden className="text-lg leading-none">
                                                    ×
                                                </span>
                                            </button>
                                        </div>

                                        <div className="flex-1 overflow-y-auto px-4 py-5 sm:px-6">
                                            {loading ? (
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    Caricamento dettaglio…
                                                </p>
                                            ) : null}

                                            {error ? (
                                                <p className="text-sm text-red-600 dark:text-red-400" role="alert">
                                                    {error}
                                                </p>
                                            ) : null}

                                            {transaction && !loading ? (
                                                <div className="space-y-5">
                                                    <div className="text-center">
                                                        <div
                                                            className="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-2xl"
                                                            style={{
                                                                backgroundColor: isTransfer
                                                                    ? '#f59e0b20'
                                                                    : isRefund
                                                                      ? '#3b82f620'
                                                                      : transaction.category?.color
                                                                        ? `${transaction.category.color}20`
                                                                        : isIncome
                                                                          ? '#22c55e20'
                                                                          : '#ef444420',
                                                            }}
                                                            aria-hidden
                                                        >
                                                            {isTransfer
                                                                ? '🔄'
                                                                : isRefund
                                                                  ? '💸'
                                                                  : transaction.category?.icon ||
                                                                    (isIncome ? '💰' : '💸')}
                                                        </div>
                                                        <p
                                                            className={clsx(
                                                                'mt-3 text-3xl font-bold tabular-nums',
                                                                isIncome ? 'text-green-500' : 'text-red-500',
                                                            )}
                                                        >
                                                            {isIncome ? '+' : ''}
                                                            {formatCurrency(
                                                                transaction.amount,
                                                                transaction.account.currency_code,
                                                            )}
                                                        </p>
                                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                            {formatDate(transaction.date)}
                                                        </p>
                                                    </div>

                                                    <dl className="space-y-3 text-sm">
                                                        <div className="flex justify-between gap-3">
                                                            <dt className="text-gray-500 dark:text-gray-400">Conto</dt>
                                                            <dd className="font-medium text-gray-900 dark:text-white">
                                                                {transaction.account.name}
                                                            </dd>
                                                        </div>
                                                        {transaction.category ? (
                                                            <div className="flex justify-between gap-3">
                                                                <dt className="text-gray-500 dark:text-gray-400">
                                                                    Categoria
                                                                </dt>
                                                                <dd className="font-medium text-gray-900 dark:text-white">
                                                                    {transaction.category.name}
                                                                </dd>
                                                            </div>
                                                        ) : null}
                                                        <div className="flex justify-between gap-3">
                                                            <dt className="text-gray-500 dark:text-gray-400">
                                                                Registrata da
                                                            </dt>
                                                            <dd className="font-medium text-gray-900 dark:text-white">
                                                                {transaction.user.name}
                                                            </dd>
                                                        </div>
                                                        <div className="flex justify-between gap-3">
                                                            <dt className="text-gray-500 dark:text-gray-400">Creata</dt>
                                                            <dd className="font-medium text-gray-900 dark:text-white">
                                                                {transaction.created_at}
                                                            </dd>
                                                        </div>
                                                    </dl>

                                                    {transaction.tags.length > 0 ? (
                                                        <div className="flex flex-wrap gap-2">
                                                            {transaction.tags.map((tag) => (
                                                                <span
                                                                    key={tag.id}
                                                                    className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                                    style={{
                                                                        backgroundColor: tag.color
                                                                            ? `${tag.color}20`
                                                                            : '#e5e7eb',
                                                                        color: tag.color || '#374151',
                                                                    }}
                                                                >
                                                                    {tag.name}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    ) : null}

                                                    <div className="flex flex-wrap gap-2">
                                                        {transaction.is_private ? (
                                                            <span className="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                                                Privata
                                                            </span>
                                                        ) : null}
                                                        {transaction.is_tax_deductible ? (
                                                            <span className="rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                                                Detraibile
                                                            </span>
                                                        ) : null}
                                                        {isTransfer ? (
                                                            <span className="rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                                                Trasferimento
                                                            </span>
                                                        ) : null}
                                                        {transaction.recurring_summary ? (
                                                            <span className="rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-800 dark:bg-violet-900/40 dark:text-violet-200">
                                                                Da ricorrenza
                                                            </span>
                                                        ) : null}
                                                    </div>

                                                    {isTransfer && transaction.transfer_id ? (
                                                        <Link
                                                            href={route('transfers.show', transaction.transfer_id)}
                                                            className="inline-flex text-sm font-medium text-amber-700 hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100"
                                                        >
                                                            Vedi trasferimento completo →
                                                        </Link>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                        </div>

                                        {transaction && !loading ? (
                                            <div className="border-t border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
                                                <div className="flex flex-col gap-2">
                                                    <LinkButton
                                                        href={route('transactions.edit', {
                                                            transaction: transaction.id,
                                                            ...indexQuery,
                                                        })}
                                                        className="inline-flex w-full items-center justify-center gap-1.5"
                                                    >
                                                        <PencilIcon size={15} />
                                                        Modifica
                                                    </LinkButton>
                                                    <Link
                                                        href={route('transactions.show', {
                                                            transaction: transaction.id,
                                                            ...indexQuery,
                                                        })}
                                                        className="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                    >
                                                        Apri pagina completa
                                                    </Link>
                                                    {!transaction.is_investment ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDeleteDialogOpen(true)}
                                                            className="inline-flex w-full items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                                        >
                                                            <TrashIcon size={15} />
                                                            Elimina
                                                        </button>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ) : null}
                                    </DialogPanel>
                                </TransitionChild>
                            </div>
                        </div>
                    </div>
                </Dialog>
            </Transition>
        </>
    );
}
