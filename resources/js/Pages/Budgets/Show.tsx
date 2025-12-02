import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';

interface Category {
    id: number;
    name: string;
    icon: string | null;
}

interface Currency {
    code: string;
    symbol: string;
}

interface Transaction {
    id: number;
    description: string;
    amount: number;
    date: string;
    account: string;
}

interface Budget {
    id: number;
    category: Category;
    amount: number;
    spent: number;
    remaining: number;
    percentage: number;
    currency: Currency;
    period_start: string;
    period_end: string;
    description: string | null;
    is_exceeded: boolean;
}

interface ShowProps {
    budget: Budget;
    transactions: Transaction[];
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function ProgressBar({ percentage, isExceeded }: { percentage: number; isExceeded: boolean }) {
    return (
        <div className="h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                className={clsx(
                    'h-full rounded-full transition-all',
                    isExceeded
                        ? 'bg-red-500'
                        : percentage >= 80
                          ? 'bg-amber-500'
                          : 'bg-emerald-500'
                )}
                style={{ width: `${Math.min(100, percentage)}%` }}
            />
        </div>
    );
}

export default function Show({ budget, transactions }: ShowProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={route('budgets.index')}
                            className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            ←
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            {budget.category.icon || '📁'} {budget.category.name}
                        </h2>
                    </div>
                    <Link
                        href={route('budgets.edit', budget.id)}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        ✏️ Modifica
                    </Link>
                </div>
            }
        >
            <Head title={`Budget - ${budget.category.name}`} />

            <div className="py-6">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Card principale */}
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div className="mb-6 text-center">
                            <span className="text-5xl">{budget.category.icon || '📁'}</span>
                            <h3 className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                                {budget.category.name}
                            </h3>
                            <p className="text-gray-500 dark:text-gray-400">
                                {formatDate(budget.period_start)} - {formatDate(budget.period_end)}
                            </p>
                        </div>

                        {/* Progresso */}
                        <div className="mb-6">
                            <div className="mb-2 flex items-end justify-between">
                                <span className="text-3xl font-bold text-gray-900 dark:text-white">
                                    {formatCurrency(budget.spent, budget.currency.code)}
                                </span>
                                <span className="text-lg text-gray-500 dark:text-gray-400">
                                    / {formatCurrency(budget.amount, budget.currency.code)}
                                </span>
                            </div>
                            <ProgressBar
                                percentage={budget.percentage}
                                isExceeded={budget.is_exceeded}
                            />
                            <div className="mt-2 flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">
                                    {budget.percentage.toFixed(1)}% utilizzato
                                </span>
                                <span
                                    className={clsx(
                                        'font-medium',
                                        budget.is_exceeded
                                            ? 'text-red-500'
                                            : 'text-emerald-500'
                                    )}
                                >
                                    {budget.is_exceeded ? 'Sforato di ' : 'Rimangono '}
                                    {formatCurrency(
                                        Math.abs(budget.remaining),
                                        budget.currency.code
                                    )}
                                </span>
                            </div>
                        </div>

                        {/* Statistiche */}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Budget
                                </p>
                                <p className="text-xl font-bold text-gray-900 dark:text-white">
                                    {formatCurrency(budget.amount, budget.currency.code)}
                                </p>
                            </div>
                            <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Speso
                                </p>
                                <p className="text-xl font-bold text-gray-900 dark:text-white">
                                    {formatCurrency(budget.spent, budget.currency.code)}
                                </p>
                            </div>
                            <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {budget.is_exceeded ? 'Eccedenza' : 'Disponibile'}
                                </p>
                                <p
                                    className={clsx(
                                        'text-xl font-bold',
                                        budget.is_exceeded
                                            ? 'text-red-500'
                                            : 'text-emerald-500'
                                    )}
                                >
                                    {formatCurrency(
                                        Math.abs(budget.remaining),
                                        budget.currency.code
                                    )}
                                </p>
                            </div>
                        </div>

                        {budget.description && (
                            <div className="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Note
                                </p>
                                <p className="text-gray-900 dark:text-white">
                                    {budget.description}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Transazioni */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className="border-b border-gray-200 p-4 dark:border-gray-700">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                Transazioni ({transactions.length})
                            </h3>
                        </div>
                        {transactions.length === 0 ? (
                            <div className="p-8 text-center">
                                <p className="text-gray-500 dark:text-gray-400">
                                    Nessuna transazione in questo periodo.
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-200 dark:divide-gray-700">
                                {transactions.map((transaction) => (
                                    <Link
                                        key={transaction.id}
                                        href={route('transactions.show', transaction.id)}
                                        className="flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                {transaction.description}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                {formatDate(transaction.date)} •{' '}
                                                {transaction.account}
                                            </p>
                                        </div>
                                        <p className="font-semibold text-red-500">
                                            -{formatCurrency(transaction.amount, budget.currency.code)}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
