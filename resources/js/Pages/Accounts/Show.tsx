import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { Head } from '@inertiajs/react';
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
    tickets_delta: number | null;
}

interface Account {
    id: number;
    name: string;
    type: string;
    type_label: string;
    initial_balance: number;
    current_balance: number;
    currency_code: string;
    ticket_unit_value: number | null;
    ticket_count: number | null;
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

function formatTicketsDelta(delta: number): string {
    const formatted = new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: Number.isInteger(delta) ? 0 : 1,
        maximumFractionDigits: 2,
    }).format(Math.abs(delta));
    const sign = delta > 0 ? '+' : delta < 0 ? '−' : '';
    const unit = Math.abs(delta) === 1 ? 'ticket' : 'ticket';
    return `${sign}${formatted} ${unit}`;
}

function TransactionRow({
    transaction,
    currency,
    showTickets,
}: {
    transaction: Transaction;
    currency: string;
    showTickets: boolean;
}) {
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
            <div className="text-right">
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
                {showTickets && transaction.tickets_delta !== null && (
                    <p
                        className={clsx(
                            'text-xs',
                            moneyTabular,
                            transaction.tickets_delta > 0
                                ? 'text-green-600 dark:text-green-400'
                                : transaction.tickets_delta < 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-500 dark:text-gray-400'
                        )}
                    >
                        {formatTicketsDelta(transaction.tickets_delta)}
                    </p>
                )}
            </div>
        </div>
    );
}

export default function Show({ account, recentTransactions }: ShowProps) {
    const isMealVoucher = account.type === 'meal_voucher';

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
                            <SectionBadge
                                label="Dettaglio conto"
                                icon={<span className="text-sm leading-none">{getAccountTypeIcon(account.type)}</span>}
                            />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                {isMealVoucher
                                    ? 'Saldo in euro, buoni pasto equivalenti e ultime operazioni.'
                                    : 'Stato del conto, saldi e ultime operazioni in un unico riepilogo.'}
                            </p>
                        </div>
                    </SectionCard>
                    <div className={moneyKpiGrid3}>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Saldo corrente
                            </p>
                            <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                {formatCurrency(account.current_balance, account.currency_code)}
                            </p>
                        </CardBox>
                        {isMealVoucher ? (
                            <>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Ticket disponibili
                                    </p>
                                    <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {account.ticket_count ?? 0}
                                    </p>
                                </CardBox>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Valore di un ticket
                                    </p>
                                    <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {account.ticket_unit_value !== null
                                            ? formatCurrency(account.ticket_unit_value, account.currency_code)
                                            : '—'}
                                    </p>
                                </CardBox>
                            </>
                        ) : (
                            <>
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
                            </>
                        )}
                    </div>

                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Ultime transazioni
                            </h3>
                            {isMealVoucher && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Equivalenza in ticket per movimento
                                </p>
                            )}
                        </div>
                        <div className="p-4">
                            {recentTransactions.length > 0 ? (
                                recentTransactions.map((transaction) => (
                                    <TransactionRow
                                        key={transaction.id}
                                        transaction={transaction}
                                        currency={account.currency_code}
                                        showTickets={isMealVoucher}
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
