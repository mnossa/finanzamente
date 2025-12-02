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
    created_at: string;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
    tags: Tag[];
}

interface ShowProps {
    transaction: Transaction;
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

export default function Show({ transaction }: ShowProps) {
    const isIncome = transaction.amount > 0;

    const handleDelete = () => {
        if (confirm('Sei sicuro di voler eliminare questa transazione?')) {
            router.delete(route('transactions.destroy', transaction.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={route('transactions.index')}
                            className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            ←
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Dettaglio Transazione
                        </h2>
                    </div>
                    <Link
                        href={route('transactions.edit', transaction.id)}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        ✏️ Modifica
                    </Link>
                </div>
            }
        >
            <Head title="Dettaglio Transazione" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Card principale */}
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
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
                                {transaction.is_private && (
                                    <span className="ml-2 text-sm text-gray-400">🔒 Privata</span>
                                )}
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
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Tipo</span>
                                <span
                                    className={clsx(
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium',
                                        isIncome
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                    )}
                                >
                                    {isIncome ? '📥 Entrata' : '📤 Uscita'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Valuta</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {transaction.account.currency_code}
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
                    </div>

                    {/* Azioni */}
                    <div className="flex flex-wrap justify-center gap-3">
                        <Link
                            href={route('transactions.edit', transaction.id)}
                            className="inline-flex items-center rounded-lg bg-indigo-600 px-6 py-3 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            ✏️ Modifica
                        </Link>
                        <button
                            onClick={handleDelete}
                            className="inline-flex items-center rounded-lg border border-red-300 px-6 py-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            🗑️ Elimina
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
