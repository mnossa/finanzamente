import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import PageHeader from '@/Components/PageHeader';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import CardBox from '@/Components/CardBox';

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

interface Tag {
    id: number;
    name: string;
    color: string | null;
}

interface RefundItem {
    id: number;
    amount: number;
    date: string | null;
    description: string | null;
    status: string;
}

interface RefundInfo {
    total_refunded: number;
    max_refundable: number;
    refund_percentage: number;
    refunds: RefundItem[];
}

interface Transaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    tax_deduction_rate: number | null;
    tax_deduction_type: string | null;
    tax_year: number | null;
    created_at: string;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
    tags: Tag[];
    transfer_id: number | null;
    refund_id: number | null;
    recurring_transaction_id: number | null;
    recurring_summary: {
        id: number;
        description: string | null;
        frequency: string;
    } | null;
    refund_info: RefundInfo | null;
    investment_id: number | null;
    is_investment: boolean;
    is_pac?: boolean;
    pac_summary?: {
        id: number;
        asset_name: string | null;
    } | null;
}

interface ShowProps {
    transaction: Transaction;
    indexQueryForReturn?: Record<string, string | number>;
}

function returnIndexQueryJson(q: Record<string, string | number>): string {
    return Object.keys(q).length === 0 ? '' : JSON.stringify(q);
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

export default function Show({ transaction, indexQueryForReturn }: ShowProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const isIncome = transaction.amount > 0;
    const isTransfer = transaction.transfer_id !== null;
    const isRefundTransaction = transaction.refund_id !== null;
    const canBeRefunded = !isIncome && !isTransfer && !isRefundTransaction && (transaction.refund_info?.max_refundable ?? 0) > 0.01;
    const hasRefunds = transaction.refund_info && transaction.refund_info.refunds.length > 0;
    const taxTypeLabel = transaction.tax_deduction_type
        ? TAX_DEDUCTION_TYPES.find(t => t.value === transaction.tax_deduction_type)?.label
        : null;

    const indexReturn = indexQueryForReturn ?? {};

    const handleDelete = () => {
        const payload = returnIndexQueryJson(indexReturn);
        router.delete(route('transactions.destroy', transaction.id), {
            ...(payload ? { data: { return_index_query: payload } } : {}),
        });
        setDeleteDialogOpen(false);
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Dettaglio Transazione`}
                    backLink={route('transactions.index', indexReturn)}
                />

            }
        >
            <Head title="Dettaglio Transazione" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description="Sei sicuro di voler eliminare questa transazione?"
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleDelete}
                onCancel={() => setDeleteDialogOpen(false)}
            />

            <PageContent maxWidth="3xl">
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Dettaglio transazione" icon={<span className="text-sm leading-none">🧾</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Visualizza importo, classificazione fiscale, privacy e stato rimborsi.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Banner trasferimento */}
                    {isTransfer && (
                        <div className="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                            <span className="text-xl">🔄</span>
                            <div>
                                <p className="font-medium text-amber-800 dark:text-amber-200">
                                    Trasferimento tra conti
                                </p>
                                <p className="text-sm text-amber-700 dark:text-amber-300">
                                    Questa transazione fa parte di un trasferimento.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Banner rimborso */}
                    {isRefundTransaction && (
                        <CardBox className="flex items-center gap-3 border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20 p-4">
                            <span className="text-xl">💸</span>
                            <div>
                                <p className="font-medium text-blue-800 dark:text-blue-200">
                                    Transazione di rimborso
                                </p>
                                <p className="text-sm text-blue-700 dark:text-blue-300">
                                    Questa transazione è un rimborso collegato a una spesa.
                                </p>
                            </div>
                            <Link
                                href={route('refunds.show', transaction.refund_id!)}
                                className="ml-auto text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            >
                                Vedi dettagli →
                            </Link>
                        </CardBox>
                    )}

                    {/* Card principale */}
                    <CardBox className="overflow-hidden p-6 shadow-sm">
                        <div className="text-center">
                            <div
                                className="mx-auto flex h-16 w-16 items-center justify-center rounded-full text-3xl"
                                style={{
                                    backgroundColor: transaction.category?.color
                                        ? `${transaction.category.color}20`
                                        : isIncome
                                            ? '#22c55e20'
                                            : '#ef444420',
                                }}
                            >
                                {transaction.category?.icon || (isIncome ? '💰' : '💸')}
                            </div>
                            <h3 className="mt-4 text-xl font-semibold text-gray-900 dark:text-white">
                                {transaction.description || transaction.category?.name || 'Transazione'}
                            </h3>
                            <p
                                className={clsx(
                                    'mt-2 text-4xl font-bold',
                                    isIncome ? 'text-green-500' : 'text-red-500'
                                )}
                            >
                                {isIncome ? '+' : ''}
                                {formatCurrency(transaction.amount, transaction.account.currency_code)}
                            </p>
                            <p className="mt-2 text-gray-500 dark:text-gray-400">
                                {formatDate(transaction.date)}
                            </p>
                        </div>

                        {/* Tag */}
                        {transaction.tags && transaction.tags.length > 0 && (
                            <div className="mt-6 flex flex-wrap justify-center gap-2">
                                {transaction.tags.map((tag) => (
                                    <span
                                        key={tag.id}
                                        className="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                                        style={{
                                            backgroundColor: tag.color ? `${tag.color}20` : '#e5e7eb',
                                            color: tag.color || '#374151',
                                        }}
                                    >
                                        🏷️ {tag.name}
                                    </span>
                                ))}
                            </div>
                        )}

                        {/* Dettagli */}
                        <div className="mt-8 space-y-4">
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Conto</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {transaction.account.name}
                                </span>
                            </div>
                            
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Categoria</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {transaction.category ? (
                                        <>
                                            {transaction.category.icon} {transaction.category.name}
                                        </>
                                    ) : (
                                        'Non categorizzata'
                                    )}
                                </span>
                            </div>
                            {transaction.recurring_summary && (
                                <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                    <span className="text-gray-500 dark:text-gray-400">Ricorrenza</span>
                                    <Link
                                        href={route('recurring-transactions.show', transaction.recurring_summary.id)}
                                        className="text-sm font-medium text-violet-600 hover:text-violet-700 dark:text-violet-400"
                                    >
                                        🔁 {transaction.recurring_summary.description || 'Vedi ricorrenza'} ({transaction.recurring_summary.frequency})
                                    </Link>
                                </div>
                            )}
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Tipo</span>
                                <span
                                    className={clsx(
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium',
                                        isTransfer
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                            : isIncome
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                    )}
                                >
                                    {isTransfer ? '🔄 Trasferimento' : isIncome ? '📥 Entrata' : '📤 Uscita'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Valuta</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {transaction.account.currency_code}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Privacy</span>
                                <span className={clsx(
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium',
                                    transaction.is_private
                                        ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                        : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                )}>
                                    {transaction.is_private ? '🔒 Privata' : '👥 Condivisa'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Creata da</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {transaction.user.name}
                                </span>
                            </div>
                            <div className="flex justify-between pb-3">
                                <span className="text-gray-500 dark:text-gray-400">Data creazione</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {transaction.created_at}
                                </span>
                            </div>
                        </div>

                        {transaction.description && (
                            <div className="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                                <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Descrizione
                                </p>
                                <p className="mt-1 text-gray-900 dark:text-white">
                                    {transaction.description}
                                </p>
                            </div>
                        )}
                    </CardBox>

                    {/* Sezione Detrazione Fiscale */}
                    {transaction.is_tax_deductible && (
                        <div className="overflow-hidden rounded-xl border-2 border-emerald-200 bg-white p-6 shadow-sm dark:border-emerald-700 dark:bg-gray-800">
                            <h3 className="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2">📋</span> Detrazione Fiscale (730)
                            </h3>

                            <div className="space-y-4">
                                <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                    <span className="text-gray-500 dark:text-gray-400">Tipo</span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {taxTypeLabel || transaction.tax_deduction_type}
                                    </span>
                                </div>

                                <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                    <span className="text-gray-500 dark:text-gray-400">Percentuale</span>
                                    <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                        {transaction.tax_deduction_rate}%
                                    </span>
                                </div>

                                <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                    <span className="text-gray-500 dark:text-gray-400">Anno fiscale</span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {transaction.tax_year}
                                    </span>
                                </div>

                                <div className="flex justify-between pb-3">
                                    <span className="text-gray-500 dark:text-gray-400">Importo detraibile</span>
                                    <span className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                        {formatCurrency(
                                            (Math.abs(transaction.amount) * (transaction.tax_deduction_rate || 0)) / 100,
                                            transaction.account.currency_code
                                        )}
                                    </span>
                                </div>
                            </div>

                            <CardBox className="mt-4 bg-emerald-50 dark:bg-emerald-900/20 p-3">
                                <p className="text-xs text-emerald-700 dark:text-emerald-300">
                                    💡 Ricorda di allegare i documenti necessari (scontrini, fatture) per la dichiarazione dei redditi.
                                </p>
                            </CardBox>
                        </div>
                    )}

                    {/* Sezione Rimborsi (solo per spese) */}
                    {(hasRefunds || canBeRefunded) && (
                        <CardBox className="overflow-hidden p-6 shadow-sm">
                            <h3 className="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2">💸</span> Rimborsi
                            </h3>

                            {/* Barra progresso rimborsi */}
                            {transaction.refund_info && transaction.refund_info.total_refunded > 0 && (
                                <div className="mb-4">
                                    <div className="mb-1 flex justify-between text-sm">
                                        <span className="text-gray-600 dark:text-gray-400">Stato rimborso</span>
                                        <span className="font-medium text-gray-900 dark:text-white">
                                            {transaction.refund_info.refund_percentage}%
                                        </span>
                                    </div>
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        <div
                                            className="h-full rounded-full bg-green-500 transition-all"
                                            style={{ width: `${Math.min(transaction.refund_info.refund_percentage, 100)}%` }}
                                        />
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>
                                            Rimborsato: {formatCurrency(transaction.refund_info.total_refunded, transaction.account.currency_code)}
                                        </span>
                                        <span>
                                            Costo netto: {formatCurrency(Math.abs(transaction.amount) - transaction.refund_info.total_refunded, transaction.account.currency_code)}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* Lista rimborsi */}
                            {hasRefunds && (
                                <div className="mb-4 space-y-2">
                                    {transaction.refund_info!.refunds.map((refund) => (
                                        <Link
                                            key={refund.id}
                                            href={route('refunds.show', refund.id)}
                                            className="flex items-center justify-between rounded-lg border border-gray-200 p-3 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                                        >
                                            <div>
                                                <p className="font-medium text-gray-900 dark:text-white">
                                                    {refund.description || 'Rimborso'}
                                                </p>
                                                {refund.date && (
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                        {new Date(refund.date).toLocaleDateString('it-IT')}
                                                    </p>
                                                )}
                                            </div>
                                            <span className="font-semibold text-green-600 dark:text-green-400">
                                                +{formatCurrency(refund.amount, transaction.account.currency_code)}
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            )}

                            {/* Pulsante per creare rimborso */}
                            {canBeRefunded && (
                                <div className="space-y-2">
                                    <Link
                                        href={route('refunds.create', { transaction_id: transaction.id })}
                                        className="inline-flex w-full items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/40"
                                    >
                                        💸 Registra rimborso
                                    </Link>
                                    <p className="text-center text-xs text-gray-500 dark:text-gray-400">
                                        Apri il modulo per indicare l&apos;importo ricevuto (totale o parziale, fino a{' '}
                                        {formatCurrency(transaction.refund_info?.max_refundable ?? Math.abs(transaction.amount), transaction.account.currency_code)}).
                                    </p>
                                </div>
                            )}

                            {/* Messaggio se completamente rimborsato */}
                            {transaction.refund_info && transaction.refund_info.max_refundable <= 0.01 && (
                                <div className="rounded-lg bg-green-50 p-3 text-center dark:bg-green-900/20">
                                    <span className="text-sm font-medium text-green-700 dark:text-green-400">
                                        ✓ Transazione completamente rimborsata
                                    </span>
                                </div>
                            )}
                        </CardBox>
                    )}

                    {transaction.is_pac && transaction.pac_summary && (
                        <CardBox className="mb-4 border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-900 dark:bg-sky-950/30">
                            <p className="text-sm text-sky-900 dark:text-sky-200">
                                Transazione generata da un piano PAC
                                {transaction.pac_summary.asset_name ? ` (${transaction.pac_summary.asset_name})` : ''}: non può essere eliminata da qui.
                                {' '}
                                <Link
                                    href={route('investment-pacs.show', transaction.pac_summary.id)}
                                    className="font-medium underline hover:text-sky-700 dark:hover:text-sky-100"
                                >
                                    Apri il piano PAC
                                </Link>
                            </p>
                        </CardBox>
                    )}

                    {transaction.is_investment && !transaction.is_pac && (
                        <CardBox className="mb-4 border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
                            <p className="text-sm text-indigo-900 dark:text-indigo-200">
                                Transazione collegata a un investimento: non può essere eliminata da qui.
                                {' '}
                                <Link
                                    href={route('investments.show', transaction.investment_id!)}
                                    className="font-medium underline hover:text-indigo-700 dark:hover:text-indigo-100"
                                >
                                    Gestisci la posizione in Investimenti
                                </Link>
                            </p>
                        </CardBox>
                    )}

                    {/* Azioni */}
                    <div className="flex flex-wrap justify-center gap-3">
                        <LinkButton
                            href={route('transactions.edit', { transaction: transaction.id, ...indexReturn })}
                            size="lg"
                            icon={<PencilIcon />}
                        >
                            Modifica
                        </LinkButton>
                        {!transaction.is_investment && (
                            <button
                                onClick={() => setDeleteDialogOpen(true)}
                                className="inline-flex items-center gap-2 rounded-lg border border-red-300 px-6 py-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                <TrashIcon size={18} /> Elimina
                            </button>
                        )}
                    </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
