import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import React from 'react';
import CardBox from '@/Components/CardBox';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import PageHeader from '@/Components/PageHeader';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface User {
    id: number;
    name: string;
}

interface OriginalTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    total_refunded: number;
    original_amount: number;
    net_amount: number;
    refund_percentage: number;
    account: Account | null;
    category: Category | null;
    user: User | null;
}

interface RefundTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    account: Account | null;
    category: Category | null;
}

interface Refund {
    id: number;
    uuid: string;
    amount: number;
    currency_code: string;
    status: string;
    description: string | null;
    created_at: string;
    original_transaction: OriginalTransaction;
    refund_transaction: RefundTransaction | null;
    user: User | null;
}

interface ShowProps {
    refund: Refund;
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

export default function Show({ refund }: ShowProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const originalTx = refund.original_transaction;
    const refundTx = refund.refund_transaction;

    const handleDelete = () => {
        router.delete(route('refunds.destroy', refund.id));
        setDeleteDialogOpen(false);
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Dettaglio Rimborso"
                    backLink={route('refunds.index')}
                    actions={
                        <LinkButton href={route('refunds.edit', refund.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Dettaglio Rimborso" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description="Sei sicuro di voler eliminare questo rimborso? Il saldo del conto verrà ripristinato."
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleDelete}
                onCancel={() => setDeleteDialogOpen(false)}
            />

            <PageContent>
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Dettaglio rimborso" icon={<span className="text-sm leading-none">🧾</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Analizza rimborso, spesa originale e transazione collegata in modo completo.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Card principale rimborso */}
                    <CardBox className="overflow-hidden p-6 shadow-sm">
                        <div className="text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-3xl dark:bg-green-900/30">
                                💸
                            </div>
                            <h3 className="mt-4 text-xl font-semibold text-gray-900 dark:text-white">
                                {refund.description || 'Rimborso'}
                            </h3>
                            <p className="mt-2 text-4xl font-bold text-green-500">
                                +{formatCurrency(refund.amount, refund.currency_code)}
                            </p>
                            <p className="mt-2 text-gray-500 dark:text-gray-400">
                                {refund.created_at}
                            </p>
                            <span
                                className={clsx(
                                    'mt-3 inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                                    refund.status === 'completed'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : refund.status === 'pending'
                                        ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                )}
                            >
                                {refund.status === 'completed' ? '✓ Completato' : refund.status === 'pending' ? '⏳ In attesa' : '✗ Annullato'}
                            </span>
                        </div>
                    </CardBox>

                    {/* Transazione Originale */}
                    <CardBox className="overflow-hidden p-6 shadow-sm">
                        <h4 className="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-white">
                            <span className="mr-2">📤</span> Spesa Originale
                        </h4>
                        <CardBox className="bg-red-50 p-4 dark:bg-red-900/20">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center space-x-3">
                                    <div
                                        className="flex h-12 w-12 items-center justify-center rounded-full text-xl"
                                        style={{
                                            backgroundColor: originalTx.category?.color
                                                ? `${originalTx.category.color}20`
                                                : '#ef444420',
                                        }}
                                    >
                                        {originalTx.category?.icon || '💸'}
                                    </div>
                                    <div>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {originalTx.description || originalTx.category?.name || 'Transazione'}
                                            {originalTx.is_private && (
                                                <span className="ml-2 text-xs text-gray-400">🔒</span>
                                            )}
                                        </p>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {originalTx.account?.name} • {formatDate(originalTx.date)}
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <p className="text-xl font-bold text-red-600 dark:text-red-400">
                                        {formatCurrency(originalTx.amount, refund.currency_code)}
                                    </p>
                                </div>
                            </div>

                            {/* Barra progresso rimborsi */}
                            <div className="mt-4">
                                <div className="mb-1 flex justify-between text-sm">
                                    <span className="text-gray-600 dark:text-gray-400">Stato rimborso</span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {originalTx.refund_percentage}%
                                    </span>
                                </div>
                                <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        className="h-full rounded-full bg-green-500 transition-all"
                                        style={{ width: `${Math.min(originalTx.refund_percentage, 100)}%` }}
                                    />
                                </div>
                                <div className="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>Rimborsato: {formatCurrency(originalTx.total_refunded, refund.currency_code)}</span>
                                    <span>Costo netto: {formatCurrency(Math.abs(originalTx.net_amount), refund.currency_code)}</span>
                                </div>
                            </div>
                        </CardBox>

                        <Link
                            href={route('transactions.show', originalTx.id)}
                            className="mt-3 inline-flex items-center text-sm text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                        >
                            Vedi transazione originale →
                        </Link>
                    </CardBox>

                    {/* Transazione di Rimborso */}
                    {refundTx && (
                        <CardBox className="overflow-hidden p-6 shadow-sm">
                            <h4 className="mb-4 flex items-center text-lg font-semibold text-gray-900 dark:text-white">
                                <span className="mr-2">📥</span> Transazione di Rimborso
                            </h4>
                            <CardBox className="bg-green-50 p-4 dark:bg-green-900/20">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div
                                            className="flex h-12 w-12 items-center justify-center rounded-full text-xl"
                                            style={{
                                                backgroundColor: refundTx.category?.color
                                                    ? `${refundTx.category.color}20`
                                                    : '#22c55e20',
                                            }}
                                        >
                                            {refundTx.category?.icon || '💰'}
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                {refundTx.description}
                                                {refundTx.is_private && (
                                                    <span className="ml-2 text-xs text-gray-400">🔒</span>
                                                )}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                {refundTx.account?.name} • {formatDate(refundTx.date)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xl font-bold text-green-600 dark:text-green-400">
                                            +{formatCurrency(refundTx.amount, refund.currency_code)}
                                        </p>
                                    </div>
                                </div>
                            </CardBox>

                            <Link
                                href={route('transactions.show', refundTx.id)}
                                className="mt-3 inline-flex items-center text-sm text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                            >
                                Vedi transazione di rimborso →
                            </Link>
                        </CardBox>
                    )}

                    {/* Dettagli aggiuntivi */}
                    <CardBox className="overflow-hidden p-6 shadow-sm">
                        <h4 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                            Dettagli
                        </h4>
                        <div className="space-y-3">
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">ID Rimborso</span>
                                <span className="font-mono text-sm text-gray-900 dark:text-white">
                                    {refund.uuid.substring(0, 8)}...
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Creato da</span>
                                <span className="text-gray-900 dark:text-white">
                                    {refund.user?.name || 'Utente sconosciuto'}
                                </span>
                            </div>
                            <div className="flex justify-between pb-3">
                                <span className="text-gray-500 dark:text-gray-400">Data registrazione</span>
                                <span className="text-gray-900 dark:text-white">{refund.created_at}</span>
                            </div>
                        </div>
                    </CardBox>

                    {/* Azioni */}
                    <div className="flex flex-wrap justify-center gap-3">
                        <LinkButton href={route('refunds.edit', refund.id)} size="lg" icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                        <button
                            onClick={() => setDeleteDialogOpen(true)}
                            className="inline-flex items-center gap-2 rounded-lg border border-red-300 px-6 py-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            <TrashIcon size={18} /> Elimina
                        </button>
                    </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
