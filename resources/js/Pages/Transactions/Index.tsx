import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

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

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function TransactionRow({ transaction }: { transaction: Transaction }) {
    const isIncome = transaction.amount > 0;

    return (
        <Link
            href={route('transactions.show', transaction.id)}
            className="flex items-center justify-between border-b border-gray-100 py-4 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50 -mx-4 px-4 transition-colors"
        >
            <div className="flex items-center space-x-3">
                <div
                    className="flex h-10 w-10 items-center justify-center rounded-full text-lg"
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
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {transaction.description || transaction.category?.name || 'Transazione'}
                        {transaction.is_private && (
                            <span className="ml-2 text-xs text-gray-400">🔒</span>
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
                <div className="flex space-x-2" onClick={(e) => e.preventDefault()}>
                    <Link
                        href={route('transactions.edit', transaction.id)}
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    >
                        ✏️
                    </Link>
                    <button
                        onClick={() => {
                            if (confirm('Sei sicuro di voler eliminare questa transazione?')) {
                                router.delete(route('transactions.destroy', transaction.id));
                            }
                        }}
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-500 dark:hover:bg-gray-700"
                    >
                        🗑️
                    </button>
                </div>
            </div>
        </Link>
    );
}

function Pagination({ data }: { data: PaginatedData<Transaction> }) {
    if (data.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            <div className="text-sm text-gray-500 dark:text-gray-400">
                {data.from}-{data.to} di {data.total} transazioni
            </div>
            <div className="flex space-x-1">
                {data.links.map((link, index) => (
                    <button
                        key={index}
                        onClick={() => link.url && router.get(link.url)}
                        disabled={!link.url}
                        className={clsx(
                            'rounded px-3 py-1 text-sm',
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : link.url
                                ? 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                                : 'cursor-not-allowed text-gray-300 dark:text-gray-600'
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
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
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Transazioni
                    </h2>
                    <Link
                        href={route('transactions.create')}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <span className="mr-2">➕</span>
                        Nuova Transazione
                    </Link>
                </div>
            }
        >
            <Head title="Transazioni" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Filtri */}
                    <div className="overflow-hidden rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex-1 min-w-[150px]">
                                <select
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.from || ''}
                                    onChange={(e) => handleFilterChange('from', e.target.value)}
                                    placeholder="Da"
                                />
                            </div>
                            <div className="flex-1 min-w-[130px]">
                                <input
                                    type="date"
                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={filters.to || ''}
                                    onChange={(e) => handleFilterChange('to', e.target.value)}
                                    placeholder="A"
                                />
                            </div>
                            {hasFilters && (
                                <button
                                    onClick={clearFilters}
                                    className="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                >
                                    Pulisci filtri
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Lista Transazioni */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        {transactions.data.length > 0 ? (
                            <>
                                <div className="p-4">
                                    {transactions.data.map((transaction) => (
                                        <TransactionRow
                                            key={transaction.id}
                                            transaction={transaction}
                                        />
                                    ))}
                                </div>
                                <Pagination data={transactions} />
                            </>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="mb-4 text-6xl">💸</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessuna transazione trovata
                                </h3>
                                <p className="mb-6 text-gray-500 dark:text-gray-400">
                                    {hasFilters
                                        ? 'Prova a modificare i filtri di ricerca.'
                                        : 'Registra la tua prima transazione per iniziare.'}
                                </p>
                                {!hasFilters && (
                                    <Link
                                        href={route('transactions.create')}
                                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                                    >
                                        <span className="mr-2">➕</span>
                                        Nuova Transazione
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
