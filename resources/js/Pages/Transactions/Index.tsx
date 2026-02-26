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
import { formatCurrency, formatDate } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import CardBox from '@/Components/CardBox';
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

interface Tag {
    id: number;
    name: string;
    color: string | null;
}

interface Transaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    tax_deduction_type: string | null;
    attachments_count: number;
    transfer_id: number | null;
    refund_id: number | null;
    has_refunds: boolean;
    total_refunded_amount: number;
    is_fully_refunded: boolean;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
    tags: Tag[];
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Filters {
    account_id?: string;
    category_id?: string;
    type?: string;
    from?: string;
    to?: string;
}

interface IndexProps {
    transactions: PaginatedData<Transaction>;
    accounts: Array<{ id: number; name: string }>;
    categories: Category[];
    filters: Filters;
}


function TransactionRow({ transaction, onDeleteClick }: { transaction: Transaction; onDeleteClick: (id: number, description: string) => void }) {
    const isIncome = transaction.amount > 0;
    const isTransfer = transaction.transfer_id !== null;
    const isRefund = transaction.refund_id !== null;
    const hasRefunds = transaction.has_refunds;

    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-4 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50 -mx-4 px-4 transition-colors">
            <div className="flex items-center space-x-3">
                <div
                    className="flex h-10 w-10 items-center justify-center rounded-full text-lg"
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
                >
                    {isTransfer ? '🔄' : isRefund ? '💸' : transaction.category?.icon || (isIncome ? '💰' : '💸')}
                </div>
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {transaction.description || transaction.category?.name || 'Transazione'}
                        {transaction.is_private && (
                            <span className="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-300">
                                🔒 Privata
                            </span>
                        )}
                        {transaction.is_tax_deductible && (
                            <span className="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                📋 Detraibile
                            </span>
                        )}
                        {isTransfer && (
                            <span className="ml-2 text-xs text-amber-500">Trasferimento</span>
                        )}
                        {isRefund && (
                            <span className="ml-2 text-xs text-blue-500">Rimborso</span>
                        )}
                        {hasRefunds && (
                            <span className={clsx(
                                'ml-2 text-xs',
                                transaction.is_fully_refunded ? 'text-green-500' : 'text-amber-500'
                            )}>
                                {transaction.is_fully_refunded ? '✓ Rimborsato' : '◐ Parzialmente rimborsato'}
                            </span>
                        )}
                    </p>
                    <div className="flex flex-wrap items-center gap-1">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {transaction.account.name} • {formatDate(transaction.date)}
                        </p>
                        {transaction.tags && transaction.tags.length > 0 && (
                            <div className="flex flex-wrap gap-1 ml-2">
                                {transaction.tags.map((tag) => (
                                    <span
                                        key={tag.id}
                                        className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        style={{
                                            backgroundColor: tag.color ? `${tag.color}20` : '#e5e7eb',
                                            color: tag.color || '#374151',
                                        }}
                                    >
                                        {tag.name}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <div className="flex items-center space-x-4">
                <p
                    className={clsx(
                        'text-lg font-semibold',
                        isIncome ? 'text-green-500' : 'text-red-500'
                    )}
                >
                    {isIncome ? '+' : ''}
                    {formatCurrency(transaction.amount, transaction.account.currency_code)}
                </p>
                <div className="flex space-x-2">
                    <Link
                        href={route('transactions.show', transaction.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={18} />
                    </Link>
                    <Link
                        href={route('transactions.edit', transaction.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                    >
                        <PencilIcon size={18} />
                    </Link>
                    <button
                        onClick={() => onDeleteClick(transaction.id, transaction.description || transaction.category?.name || 'questa transazione')}
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


export default function Index({
    transactions,
    accounts,
    categories,
    filters,
}: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('transactions.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleFilterChange = (key: string, value: string) => {
        router.get(
            route('transactions.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const clearFilters = () => {
        router.get(route('transactions.index'));
    };

    const hasFilters = Object.values(filters).some((v) => v);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Transazioni"
                    actions={
                        <div className="flex items-center gap-2">
                            <LinkButton
                                href={route('transactions.import')}
                                variant="secondary"
                            >
                                Importa
                            </LinkButton>
                            <LinkButton
                                href={route('transactions.create')}
                                icon={<PlusIcon />}
                            >
                                Nuova Transazione
                            </LinkButton>
                        </div>
                    }
                />
            }
        >
            <Head title="Transazioni" />

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
                    {/* Filtri */}
                    <CardBox className="overflow-hidden p-4 shadow-sm">
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex-1 min-w-[150px]">
                                <select
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.account_id || ''}
                                    onChange={(e) => handleFilterChange('account_id', e.target.value)}
                                >
                                    <option value="">Tutti i conti</option>
                                    {accounts.map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex-1 min-w-[150px]">
                                <select
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.category_id || ''}
                                    onChange={(e) => handleFilterChange('category_id', e.target.value)}
                                >
                                    <option value="">Tutte le categorie</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.icon} {category.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex-1 min-w-[120px]">
                                <select
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.type || ''}
                                    onChange={(e) => handleFilterChange('type', e.target.value)}
                                >
                                    <option value="">Tipo</option>
                                    <option value="income">Entrate</option>
                                    <option value="expense">Uscite</option>
                                </select>
                            </div>
                            <div className="flex-1 min-w-[130px]">
                                <input
                                    type="date"
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.from || ''}
                                    onChange={(e) => handleFilterChange('from', e.target.value)}
                                    placeholder="Da"
                                />
                            </div>
                            <div className="flex-1 min-w-[130px]">
                                <input
                                    type="date"
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.to || ''}
                                    onChange={(e) => handleFilterChange('to', e.target.value)}
                                    placeholder="A"
                                />
                            </div>
                            {hasFilters && (
                                <button
                                    onClick={clearFilters}
                                    className="text-sm text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                                >
                                    Pulisci filtri
                                </button>
                            )}
                        </div>
                    </CardBox>

                    {/* Lista Transazioni */}
                    <CardBox className="overflow-hidden shadow-sm">
                        {transactions.data.length > 0 ? (
                            <>
                                <div className="p-4">
                                    {transactions.data.map((transaction) => (
                                        <TransactionRow
                                            key={transaction.id}
                                            transaction={transaction}
                                            onDeleteClick={openDeleteDialog}
                                        />
                                    ))}
                                </div>
                                <Pagination data={transactions} />
                            </>
                        ) : (
                            <EmptyState
                                icon="💸"
                                title="Nessuna transazione trovata"
                                description={
                                    hasFilters
                                        ? 'Prova a modificare i filtri di ricerca.'
                                        : 'Registra la tua prima transazione per iniziare.'
                                }
                                createUrl={!hasFilters ? route('transactions.create') : undefined}
                                createLabel="Nuova Transazione"
                                showCreateButton={!hasFilters}
                            />
                        )}
                    </CardBox>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
