import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { moneyKpiGrid3, moneyTabular } from '@/utils/moneyGridClasses';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
}

interface Transaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    category: Category | null;
    user: {
        id: number;
        name: string;
    };
}

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
    created_at: string;
}

interface ShowProps {
    account: Account;
    recentTransactions: Transaction[];
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}


function TransactionRow({ transaction, currency }: { transaction: Transaction; currency: string }) {
    const isIncome = transaction.amount > 0;
    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-3 last:border-0 dark:border-gray-700">
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
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {new Date(transaction.date).toLocaleDateString('it-IT')}
                    </p>
                </div>
            </div>
            <p
                className={clsx(
                    'font-semibold',
                    moneyTabular,
                    isIncome ? 'text-green-500' : 'text-red-500'
                )}
            >
                {isIncome ? '+' : ''}
                {formatCurrency(transaction.amount, currency)}
            </p>
        </div>
    );
}

export default function Show({ account, recentTransactions }: ShowProps) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Conto: ${account.name}`}
                    backLink={route('accounts.index')}
                    actions={
                        <LinkButton href={route('accounts.edit', account.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    }
                />
            }
        >
            <Head title={account.name} />

            <PageContent maxWidth="5xl">
                    <IndexPageMobileToolbar>
                        <LinkButton href={route('accounts.edit', account.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    </IndexPageMobileToolbar>
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Dettaglio conto" icon={<span className="text-sm leading-none">🏦</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Stato del conto, saldi e ultime operazioni in un unico riepilogo.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Riepilogo */}
                    <div className={moneyKpiGrid3}>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Saldo corrente
                            </p>
                            <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                {formatCurrency(account.current_balance, account.currency_code)}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Saldo iniziale
                            </p>
                            <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                {formatCurrency(account.initial_balance, account.currency_code)}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Creato il
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {account.created_at}
                            </p>
                        </CardBox>
                    </div>

                    {/* Transazioni Recenti */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Ultime transazioni
                            </h3>
                        </div>
                        <div className="p-4">
                            {recentTransactions.length > 0 ? (
                                recentTransactions.map((transaction) => (
                                    <TransactionRow
                                        key={transaction.id}
                                        transaction={transaction}
                                        currency={account.currency_code}
                                    />
                                ))
                            ) : (
                                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                    Nessuna transazione recente.
                                </div>
                            )}
                        </div>
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
