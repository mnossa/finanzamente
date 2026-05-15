import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { moneyKpiGrid3, moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency, formatDate } from '@/utils/format';
import { ProgressBar } from '@/Components/ProgressBar';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';

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




export default function Show({ budget, transactions }: ShowProps) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Budget: ${budget.category.name}`}
                    backLink={route('budgets.index')}
                    actions={
                        <LinkButton href={route('budgets.edit', budget.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    }
                />
            }
        >
            <Head title={`Budget - ${budget.category.name}`} />

            <PageContent maxWidth="4xl">
                    <IndexPageMobileToolbar>
                        <LinkButton href={route('budgets.edit', budget.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    </IndexPageMobileToolbar>
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Dettaglio budget" icon={<span className="text-sm leading-none">📈</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Vista completa su avanzamento, importi e transazioni del periodo.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Card principale */}
                    <CardBox>
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
                                <span className={clsx('text-3xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                    {formatCurrency(budget.spent, budget.currency.code)}
                                </span>
                                <span className={clsx('text-lg text-gray-500 dark:text-gray-400', moneyTabular)}>
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
                                        moneyTabular,
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
                        <div className={moneyKpiGrid3}>
                            <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Budget
                                </p>
                                <p className={clsx('text-xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                    {formatCurrency(budget.amount, budget.currency.code)}
                                </p>
                            </div>
                            <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Speso
                                </p>
                                <p className={clsx('text-xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
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
                                        moneyTabular,
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
                    </CardBox>

                    {/* Transazioni */}
                    <CardBox className="overflow-hidden shadow-sm">
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
                                        <p className={clsx('font-semibold text-red-500', moneyTabular)}>
                                            -{formatCurrency(transaction.amount, budget.currency.code)}
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
