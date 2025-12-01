import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

interface Account {
    id: number;
    name: string;
    type: string;
    type_label: string;
    initial_balance: number;
    current_balance: number;
    currency_code: string;
    active: boolean;
    is_private: boolean;
    owner: { id: number; name: string } | null;
    created_at: string;
}

interface TotalsByType {
    [key: string]: {
        count: number;
        total: number;
    };
}

interface AccountTypes {
    [key: string]: string;
}

interface IndexProps {
    accounts: Account[];
    totalsByType: TotalsByType;
    totalBalance: number;
    accountTypes: AccountTypes;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function getAccountTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        bank: '🏦',
        cash: '💵',
        card: '💳',
        broker: '📈',
        crypto: '₿',
        other: '💰',
    };
    return icons[type] || '💰';
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="mb-4 text-6xl">🏦</div>
            <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                Nessun conto trovato
            </h3>
            <p className="mb-6 text-gray-500 dark:text-gray-400">
                Crea il tuo primo conto per iniziare a monitorare le tue finanze.
            </p>
            <Link
                href={route('accounts.create')}
                className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
            >
                <span className="mr-2">➕</span>
                Crea il tuo primo conto
            </Link>
        </div>
    );
}

function AccountCard({ account }: { account: Account }) {
    return (
        <div
            className={clsx(
                'rounded-xl bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800',
                !account.active && 'opacity-60'
            )}
        >
            <Link
                href={route('accounts.show', account.id)}
                className="block"
            >
                <div className="flex items-start justify-between">
                    <div className="flex items-center space-x-3">
                        <span className="text-3xl">
                            {getAccountTypeIcon(account.type)}
                        </span>
                        <div>
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                {account.name}
                                {account.is_private && (
                                    <span className="ml-2 text-xs text-gray-400">🔒</span>
                                )}
                                {!account.active && (
                                    <span className="ml-2 rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        Archiviato
                                    </span>
                                )}
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {account.type_label}
                            </p>
                        </div>
                    </div>
                    <div className="text-right">
                        <p
                            className={clsx(
                                'text-lg font-bold',
                                account.current_balance >= 0
                                    ? 'text-gray-900 dark:text-white'
                                    : 'text-red-500'
                            )}
                        >
                            {formatCurrency(account.current_balance, account.currency_code)}
                        </p>
                        {account.current_balance !== account.initial_balance && (
                            <p className="text-xs text-gray-400">
                                Iniziale: {formatCurrency(account.initial_balance, account.currency_code)}
                            </p>
                        )}
                    </div>
                </div>
            </Link>
            <div className="mt-3 flex justify-end space-x-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                <Link
                    href={route('accounts.edit', account.id)}
                    className="rounded px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    Modifica
                </Link>
                <button
                    onClick={(e) => {
                        e.preventDefault();
                        router.post(route('accounts.toggle-active', account.id));
                    }}
                    className="rounded px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    {account.active ? 'Archivia' : 'Riattiva'}
                </button>
            </div>
        </div>
    );
}

export default function Index({
    accounts,
    totalsByType,
    totalBalance,
    accountTypes,
}: IndexProps) {
    const activeAccounts = accounts.filter((a) => a.active);
    const archivedAccounts = accounts.filter((a) => !a.active);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        I tuoi Conti
                    </h2>
                    <Link
                        href={route('accounts.create')}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <span className="mr-2">➕</span>
                        Nuovo Conto
                    </Link>
                </div>
            }
        >
            <Head title="Conti" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {accounts.length === 0 ? (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <EmptyState />
                        </div>
                    ) : (
                        <>
                            {/* Saldo Totale */}
                            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 p-6 text-white shadow-lg">
                                <h3 className="text-sm font-medium text-indigo-100">
                                    Patrimonio Totale
                                </h3>
                                <p className="mt-2 text-4xl font-bold">
                                    {formatCurrency(totalBalance)}
                                </p>
                                <p className="mt-1 text-sm text-indigo-200">
                                    {activeAccounts.length} {activeAccounts.length === 1 ? 'conto attivo' : 'conti attivi'}
                                </p>
                            </div>

                            {/* Riepilogo per tipo */}
                            <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                {Object.entries(accountTypes).map(([type, label]) => {
                                    const stats = totalsByType[type];
                                    if (!stats) return null;
                                    return (
                                        <div
                                            key={type}
                                            className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800"
                                        >
                                            <div className="flex items-center space-x-2">
                                                <span className="text-xl">
                                                    {getAccountTypeIcon(type)}
                                                </span>
                                                <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                    {label}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-lg font-bold text-gray-900 dark:text-white">
                                                {formatCurrency(stats.total)}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                {stats.count} {stats.count === 1 ? 'conto' : 'conti'}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Lista Conti Attivi */}
                            {activeAccounts.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                        Conti Attivi
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {activeAccounts.map((account) => (
                                            <AccountCard key={account.id} account={account} />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Lista Conti Archiviati */}
                            {archivedAccounts.length > 0 && (
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold text-gray-500 dark:text-gray-400">
                                        Conti Archiviati
                                    </h3>
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {archivedAccounts.map((account) => (
                                            <AccountCard key={account.id} account={account} />
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
