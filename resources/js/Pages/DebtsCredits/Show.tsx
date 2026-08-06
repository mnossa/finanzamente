import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { StatusBadge } from '@/Components/StatusBadge';
import { ProgressBar } from '@/Components/ProgressBar';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import PageHeader from '@/Components/PageHeader';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { useState } from 'react';

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

interface Transaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    account: { name: string; currency_code: string };
    category: { name: string; icon: string | null } | null;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    initial_amount: number;
    paid_amount: number;
    remaining_amount: number;
    interest_rate: number | null;
    tan_rate: number | null;
    taeg_rate: number | null;
    interest_type: string | null;
    accrued_interest: number;
    total_with_interest: number;
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
    adjustments: Array<{ id: number; amount: number; effective_date: string; reason: string | null; notes: string | null; user: string | null }>;
}

interface ShowProps {
    debtCredit: DebtCredit;
    transactions: Transaction[];
    types: Types;
    statuses: Statuses;
}

import { formatCurrency, formatDateLong } from '@/utils/format';

export default function Show({ debtCredit, transactions, types, statuses }: ShowProps) {
    const { permissions } = usePage<PageProps>().props;
    const canModify = permissions.canModify ?? false;
    const isDebt = debtCredit.type === 'debt';
    const initialAmount = debtCredit.initial_amount || debtCredit.amount;
    const paidPercent = initialAmount > 0 ? Math.min(100, (debtCredit.paid_amount / initialAmount) * 100) : 0;
    const [adjustmentAmount, setAdjustmentAmount] = useState('');
    const [adjustmentReason, setAdjustmentReason] = useState('');
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [confirmClose, setConfirmClose] = useState(false);

    const handleClose = () => {
        router.post(route('debts-credits.close', debtCredit.id));
        setConfirmClose(false);
    };

    const handleReopen = () => {
        router.post(route('debts-credits.reopen', debtCredit.id));
    };

    const handleDelete = () => {
        router.delete(route('debts-credits.destroy', debtCredit.id));
        setConfirmDelete(false);
    };

    const handleNonMonetaryReduction = () => {
        if (!adjustmentAmount || Number(adjustmentAmount) <= 0) return;
        router.post(route('debts-credits.adjustments.store', debtCredit.id), {
            amount: adjustmentAmount,
            reason: adjustmentReason || null,
            effective_date: new Date().toISOString().slice(0, 10),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${debtCredit.type_label} - ${debtCredit.counterparty}`}
                    backLink={route('debts-credits.index')}
                    actions={
                        canModify ? (
                            <LinkButton href={route('debts-credits.edit', debtCredit.id)} icon={<PencilIcon />}>
                                Modifica
                            </LinkButton>
                        ) : undefined
                    }
                />
            }
        >
            <Head title={`${debtCredit.type_label} - ${debtCredit.counterparty}`} />

            <PageContent maxWidth="3xl">
                    {canModify && (
                        <IndexPageMobileToolbar>
                            <LinkButton href={route('debts-credits.edit', debtCredit.id)} icon={<PencilIcon />}>
                                Modifica
                            </LinkButton>
                        </IndexPageMobileToolbar>
                    )}
                    {/* Card riepilogo */}
                    <CardBox className="overflow-hidden p-6 shadow-sm">
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
                                {formatCurrency(debtCredit.remaining_amount, debtCredit.currency.code)}
                            </p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                rimanente su {formatCurrency(initialAmount, debtCredit.currency.code)} totali
                            </p>
                        </div>

                        {/* Barra di avanzamento */}
                        <div className="mt-6">
                            <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <span>Pagato: <span className="font-medium text-gray-900 dark:text-white">{formatCurrency(debtCredit.paid_amount, debtCredit.currency.code)}</span></span>
                                <span className="font-medium text-gray-900 dark:text-white">{paidPercent.toFixed(0)}%</span>
                            </div>
                            <ProgressBar
                                percentage={paidPercent}
                                color={paidPercent >= 100 ? 'bg-emerald-500' : isDebt ? 'bg-red-400' : 'bg-emerald-400'}
                                height="0.75rem"
                            />
                        </div>

                        {/* Interessi */}
                        {debtCredit.interest_rate && debtCredit.interest_rate > 0 && (
                            <div className="mt-4 rounded-lg bg-amber-50 p-3 text-sm dark:bg-amber-900/20">
                                <div className="flex justify-between">
                                    <span className="text-amber-700 dark:text-amber-300">
                                        Interessi maturati ({debtCredit.interest_rate}% {debtCredit.interest_type === 'compound' ? 'composto' : 'semplice'})
                                    </span>
                                    <span className="font-medium text-amber-800 dark:text-amber-200">
                                        +{formatCurrency(debtCredit.accrued_interest, debtCredit.currency.code)}
                                    </span>
                                </div>
                                <div className="mt-1 flex justify-between font-semibold">
                                    <span className="text-amber-800 dark:text-amber-200">Totale con interessi</span>
                                    <span className="text-amber-900 dark:text-amber-100">
                                        {formatCurrency(debtCredit.total_with_interest, debtCredit.currency.code)}
                                    </span>
                                </div>
                            </div>
                        )}

                        {/* Dettagli */}
                        <div className="mt-6 space-y-3">
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Tipo</span>
                                <span className="font-medium text-gray-900 dark:text-white">{debtCredit.type_label}</span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Valuta</span>
                                <span className="font-medium text-gray-900 dark:text-white">{debtCredit.currency.code} ({debtCredit.currency.symbol})</span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">TAN/TAEG</span>
                                <span className="font-medium text-gray-900 dark:text-white">{debtCredit.tan_rate ?? '—'}% / {debtCredit.taeg_rate ?? '—'}%</span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Data di Scadenza</span>
                                <span className={clsx('font-medium', debtCredit.status === 'overdue' ? 'text-red-500' : 'text-gray-900 dark:text-white')}>
                                    {debtCredit.due_date ? formatDateLong(debtCredit.due_date) : 'Non impostata'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Creato da</span>
                                <span className="font-medium text-gray-900 dark:text-white">{debtCredit.created_by}</span>
                            </div>
                            <div className="flex justify-between pb-3">
                                <span className="text-gray-500 dark:text-gray-400">Creato il</span>
                                <span className="font-medium text-gray-900 dark:text-white">{debtCredit.created_at}</span>
                            </div>
                        </div>

                        {debtCredit.description && (
                            <div className="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                                <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Note</p>
                                <p className="mt-1 text-gray-900 dark:text-white">{debtCredit.description}</p>
                            </div>
                        )}
                    </CardBox>

                    {canModify && (
                    <div className="flex flex-wrap justify-center gap-3">
                        {debtCredit.status !== 'closed' && (
                            <LinkButton href={`${route('transactions.create')}?debt_credit_id=${debtCredit.id}`} icon={<span>💳</span>}>
                                Registra pagamento
                            </LinkButton>
                        )}
                        {debtCredit.status !== 'closed' ? (
                            <PrimaryButton
                                type="button"
                                onClick={() => setConfirmClose(true)}
                                className="bg-emerald-600 hover:bg-emerald-700"
                            >
                                ✓ Segna come {isDebt ? 'saldato' : 'incassato'}
                            </PrimaryButton>
                        ) : (
                            <PrimaryButton
                                type="button"
                                onClick={handleReopen}
                                className="bg-blue-600 hover:bg-blue-700 shadow-none"
                            >
                                ↩ Riapri
                            </PrimaryButton>
                        )}
                        <button
                            type="button"
                            onClick={() => setConfirmDelete(true)}
                            className="inline-flex items-center gap-2 rounded-xl border border-red-300 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
                        >
                            <TrashIcon size={16} /> Elimina
                        </button>
                    </div>
                    )}

                    {canModify && debtCredit.status !== 'closed' && (
                        <CardBox className="p-4 shadow-sm">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">Riduzione non monetaria</h3>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Usa questo flusso per ridurre il debito senza una transazione di conto (es. cessione bene).</p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                <TextInput value={adjustmentAmount} onChange={(e) => setAdjustmentAmount(e.target.value)} type="number" min="0.01" step="0.01" placeholder="Importo" />
                                <TextInput value={adjustmentReason} onChange={(e) => setAdjustmentReason(e.target.value)} type="text" placeholder="Motivo (opzionale)" />
                                <PrimaryButton type="button" onClick={handleNonMonetaryReduction}>
                                    Registra riduzione
                                </PrimaryButton>
                            </div>
                        </CardBox>
                    )}

                    {/* Storico riduzioni */}
                    {debtCredit.adjustments.length > 0 && (
                        <CardBox className="overflow-hidden shadow-sm">
                            <div className="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    Riduzioni non monetarie
                                    <span className="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {debtCredit.adjustments.length}
                                    </span>
                                </h3>
                            </div>
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {debtCredit.adjustments.map((adj) => (
                                    <div key={adj.id} className="flex items-center justify-between px-6 py-3">
                                        <div>
                                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                {adj.reason || 'Riduzione'}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {new Date(adj.effective_date).toLocaleDateString('it-IT')}
                                                {adj.user && ` · ${adj.user}`}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                            −{formatCurrency(adj.amount, debtCredit.currency.code)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardBox>
                    )}

                    {/* Lista transazioni collegate */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Transazioni collegate
                                {transactions.length > 0 && (
                                    <span className="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {transactions.length}
                                    </span>
                                )}
                            </h3>
                            {debtCredit.status !== 'closed' && (
                                <Link
                                    href={`${route('transactions.create')}?debt_credit_id=${debtCredit.id}`}
                                    className="text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-200"
                                >
                                    + Aggiungi
                                </Link>
                            )}
                        </div>

                        {transactions.length === 0 ? (
                            <div className="px-6 py-10 text-center">
                                <div className="mb-3 text-4xl">💳</div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Nessuna transazione registrata.
                                </p>
                                {debtCredit.status !== 'closed' && (
                                    <Link
                                        href={`${route('transactions.create')}?debt_credit_id=${debtCredit.id}`}
                                        className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                                    >
                                        Registra il primo pagamento →
                                    </Link>
                                )}
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                {transactions.map((t) => {
                                    const isIncome = t.amount > 0;
                                    return (
                                        <div key={t.id} className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <div className="flex items-center gap-3">
                                                <div className={clsx(
                                                    'flex h-8 w-8 items-center justify-center rounded-full text-sm',
                                                    isIncome ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30'
                                                )}>
                                                    {t.category?.icon || (isIncome ? '💰' : '💸')}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                        {t.description || t.category?.name || 'Transazione'}
                                                    </p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {t.account.name} • {new Date(t.date).toLocaleDateString('it-IT')}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className={clsx('text-sm font-semibold', isIncome ? 'text-emerald-500' : 'text-red-500')}>
                                                    {isIncome ? '+' : ''}{formatCurrency(t.amount, t.account.currency_code)}
                                                </span>
                                                <Link
                                                    href={route('transactions.show', t.id)}
                                                    className="text-xs text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                                >
                                                    →
                                                </Link>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardBox>
                    <ConfirmDeleteDialog
                        open={confirmDelete}
                        title="Elimina posizione"
                        description="Sei sicuro di voler eliminare questo elemento? Questa azione non può essere annullata."
                        onConfirm={handleDelete}
                        onCancel={() => setConfirmDelete(false)}
                    />
                    <ConfirmDeleteDialog
                        open={confirmClose}
                        title={isDebt ? 'Segna come saldato' : 'Segna come incassato'}
                        description={`Vuoi segnare questo elemento come ${isDebt ? 'saldato' : 'incassato'}?`}
                        confirmLabel="Conferma"
                        variant="info"
                        onConfirm={handleClose}
                        onCancel={() => setConfirmClose(false)}
                    />
            </PageContent>
        </AuthenticatedLayout>
    );
}
