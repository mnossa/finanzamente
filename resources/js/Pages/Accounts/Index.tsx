import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import ArchiveIcon from '@/Components/Icons/ArchiveIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';

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


function AccountCard({ account }: { account: Account }) {
    return (
        <CardBox
            className={clsx(
                'p-4 shadow-sm transition-shadow hover:shadow-md',
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
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                    title="Modifica"
                >
                    <PencilIcon size={18} />
                </Link>
                <button
                    onClick={(e) => {
                        e.preventDefault();
                        router.post(route('accounts.toggle-active', account.id));
                    }}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                    title={account.active ? 'Archivia' : 'Riattiva'}
                >
                    <ArchiveIcon size={18} />
                </button>
            </div>
        </CardBox>
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
                <PageHeader
                    title="I tuoi Conti"
                    actions={
                        <LinkButton
                            href={route('accounts.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo Conto
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Conti" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {accounts.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="🏦"
                                title="Nessun conto trovato"
                                description="Crea il tuo primo conto per iniziare a monitorare le tue finanze."
                                createUrl={route('accounts.create')}
                                createLabel="Crea il tuo primo conto"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Saldo Totale */}
                            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                                <h3 className="text-sm font-medium text-slate-300">
                                    Patrimonio Totale
                                </h3>
                                <p className="mt-2 text-4xl font-bold">
                                    {formatCurrency(totalBalance)}
                                </p>
                                <p className="mt-1 text-sm text-slate-400">
                                    {activeAccounts.length} {activeAccounts.length === 1 ? 'conto attivo' : 'conti attivi'}
                                </p>
                            </div>

                            {/* Riepilogo per tipo */}
                            <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                {Object.entries(accountTypes).map(([type, label]) => {
                                    const stats = totalsByType[type];
                                    if (!stats) return null;
                                    return (
                                        <CardBox
                                            key={type}
                                            className="p-4 shadow-sm"
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
                                        </CardBox>
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
