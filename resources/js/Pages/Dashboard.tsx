import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PlusIcon from '@/Components/Icons/PlusIcon';
import QuickActionCard from '@/Components/QuickActionCard';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';

interface Account {
    id: number;
    name: string;
    type: string;
    currency_code: string;
    initial_balance: number;
    current_balance: number;
    is_private: boolean;
}

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
    account: {
        id: number;
        name: string;
    };
    user: {
        id: number;
        name: string;
    };
}

interface MonthlyStats {
    income: number;
    expenses: number;
    net: number;
    transaction_count: number;
}

interface ActiveBudget {
    id: number;
    category_name: string;
    category_icon: string | null;
    amount: number;
    spent: number;
    percentage: number;
    is_exceeded: boolean;
    currency_symbol: string;
}

interface OpenDebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    type: string;
    status: string;
    due_date: string | null;
    currency_symbol: string;
}

interface DebtsCreditsSummary {
    total_debts: number;
    total_credits: number;
    overdue_count: number;
}

interface DashboardProps {
    accounts: Account[];
    totalBalance: number;
    recentTransactions: Transaction[];
    monthlyStats: MonthlyStats;
    lastMonthStats: MonthlyStats;
    currentMonth: string;
    lastMonth: string;
    activeBudgets: ActiveBudget[];
    openDebtsCredits: OpenDebtCredit[];
    debtsCreditsSummary: DebtsCreditsSummary;
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
    }).format(date);
}

function getAccountTypeLabel(type: string): string {
    const types: Record<string, string> = {
        bank: 'Conto Bancario',
        cash: 'Contanti',
        credit_card: 'Carta di Credito',
        debit_card: 'Carta di Debito',
        investment: 'Investimento',
        crypto: 'Crypto',
        other: 'Altro',
    };
    return types[type] || type;
}

function getAccountTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        bank: '🏦',
        cash: '💵',
        credit_card: '💳',
        debit_card: '💳',
        investment: '📈',
        crypto: '₿',
        other: '💰',
    };
    return icons[type] || '💰';
}

function StatCard({
    title,
    value,
    subtitle,
    trend,
    trendLabel,
    className,
}: {
    title: string;
    value: string;
    subtitle?: string;
    trend?: 'up' | 'down' | 'neutral';
    trendLabel?: string;
    className?: string;
}) {
    return (
        <div
            className={clsx(
                'overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800',
                className
            )}
        >
            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">
                {title}
            </h3>
            <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {value}
            </p>
            {(subtitle || trendLabel) && (
                <div className="mt-2 flex items-center text-sm">
                    {trend && (
                        <span
                            className={clsx(
                                'mr-1',
                                trend === 'up' && 'text-green-500',
                                trend === 'down' && 'text-red-500',
                                trend === 'neutral' && 'text-gray-400'
                            )}
                        >
                            {trend === 'up' && '↑'}
                            {trend === 'down' && '↓'}
                            {trend === 'neutral' && '→'}
                        </span>
                    )}
                    {trendLabel && (
                        <span
                            className={clsx(
                                trend === 'up' && 'text-green-500',
                                trend === 'down' && 'text-red-500',
                                trend === 'neutral' && 'text-gray-500'
                            )}
                        >
                            {trendLabel}
                        </span>
                    )}
                    {subtitle && !trendLabel && (
                        <span className="text-gray-500 dark:text-gray-400">
                            {subtitle}
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}

function AccountCard({ account }: { account: Account }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
            <div className="flex items-center space-x-3">
                <span className="text-2xl">
                    {getAccountTypeIcon(account.type)}
                </span>
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {account.name}
                        {account.is_private && (
                            <span className="ml-2 text-xs text-gray-400">
                                🔒
                            </span>
                        )}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {getAccountTypeLabel(account.type)}
                    </p>
                </div>
            </div>
            <div className="text-right">
                <p
                    className={clsx(
                        'text-lg font-semibold',
                        account.current_balance >= 0
                            ? 'text-gray-900 dark:text-white'
                            : 'text-red-500'
                    )}
                >
                    {formatCurrency(account.current_balance, account.currency_code)}
                </p>
            </div>
        </div>
    );
}

function TransactionRow({ transaction }: { transaction: Transaction }) {
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
                        {transaction.account.name} • {formatDate(transaction.date)}
                    </p>
                </div>
            </div>
            <p
                className={clsx(
                    'font-semibold',
                    isIncome ? 'text-green-500' : 'text-red-500'
                )}
            >
                {isIncome ? '+' : ''}
                {formatCurrency(transaction.amount)}
            </p>
        </div>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-12 text-center">
            <div className="mb-4 text-4xl">📊</div>
            <p className="text-gray-500 dark:text-gray-400">{message}</p>
        </div>
    );
}

function BudgetProgressBar({ percentage, isExceeded }: { percentage: number; isExceeded: boolean }) {
    return (
        <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-600">
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

function BudgetCard({ budget }: { budget: ActiveBudget }) {
    return (
        <Link
            href={route('budgets.show', budget.id)}
            className="block rounded-lg bg-gray-50 p-3 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
        >
            <div className="mb-2 flex items-center justify-between">
                <div className="flex items-center space-x-2">
                    <span>{budget.category_icon || '📁'}</span>
                    <span className="font-medium text-gray-900 dark:text-white">
                        {budget.category_name}
                    </span>
                </div>
                {budget.is_exceeded && (
                    <span className="text-red-500">⚠️</span>
                )}
            </div>
            <BudgetProgressBar percentage={budget.percentage} isExceeded={budget.is_exceeded} />
            <div className="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{budget.currency_symbol}{budget.spent.toFixed(0)}</span>
                <span>{budget.percentage}%</span>
                <span>{budget.currency_symbol}{budget.amount.toFixed(0)}</span>
            </div>
        </Link>
    );
}

function DebtCreditRow({ item }: { item: OpenDebtCredit }) {
    const isDebt = item.type === 'debt';
    const isOverdue = item.status === 'overdue';
    
    return (
        <Link
            href={route('debts-credits.show', item.id)}
            className="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
        >
            <div className="flex items-center space-x-2">
                <span className={clsx(
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm',
                    isDebt ? 'bg-red-100 dark:bg-red-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30'
                )}>
                    {isDebt ? '📤' : '📥'}
                </span>
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {item.counterparty}
                        {isOverdue && <span className="ml-1 text-red-500">⚠️</span>}
                    </p>
                    {item.due_date && (
                        <p className={clsx(
                            'text-xs',
                            isOverdue ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'
                        )}>
                            Scadenza: {formatDate(item.due_date)}
                        </p>
                    )}
                </div>
            </div>
            <span className={clsx(
                'font-semibold',
                isDebt ? 'text-red-500' : 'text-emerald-500'
            )}>
                {isDebt ? '-' : '+'}{item.currency_symbol}{item.amount.toFixed(2)}
            </span>
        </Link>
    );
}

export default function Dashboard({
    accounts,
    totalBalance,
    recentTransactions,
    monthlyStats,
    lastMonthStats,
    currentMonth,
    lastMonth,
    activeBudgets,
    openDebtsCredits,
    debtsCreditsSummary,
}: DashboardProps) {
    // Calcola trend rispetto al mese precedente
    const incomeTrend =
        lastMonthStats.income > 0
            ? ((monthlyStats.income - lastMonthStats.income) / lastMonthStats.income) * 100
            : monthlyStats.income > 0
            ? 100
            : 0;

    const expensesTrend =
        lastMonthStats.expenses > 0
            ? ((monthlyStats.expenses - lastMonthStats.expenses) / lastMonthStats.expenses) * 100
            : monthlyStats.expenses > 0
            ? 100
            : 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Saldo Totale */}
                    <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                        <h3 className="text-sm font-medium text-slate-300">
                            Saldo Totale
                        </h3>
                        <p className="mt-2 text-4xl font-bold">
                            {formatCurrency(totalBalance)}
                        </p>
                        <p className="mt-1 text-sm text-slate-400">
                            {accounts.length} {accounts.length === 1 ? 'conto attivo' : 'conti attivi'}
                        </p>
                    </div>

                    {/* Statistiche Mensili */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            title="Entrate"
                            value={formatCurrency(monthlyStats.income)}
                            subtitle={currentMonth}
                            trend={incomeTrend >= 0 ? 'up' : 'down'}
                            trendLabel={`${incomeTrend >= 0 ? '+' : ''}${incomeTrend.toFixed(0)}% vs ${lastMonth}`}
                        />
                        <StatCard
                            title="Uscite"
                            value={formatCurrency(monthlyStats.expenses)}
                            subtitle={currentMonth}
                            trend={expensesTrend <= 0 ? 'up' : 'down'}
                            trendLabel={`${expensesTrend >= 0 ? '+' : ''}${expensesTrend.toFixed(0)}% vs ${lastMonth}`}
                        />
                        <StatCard
                            title="Saldo Netto"
                            value={formatCurrency(monthlyStats.net)}
                            subtitle={currentMonth}
                            trend={monthlyStats.net >= 0 ? 'up' : 'down'}
                        />
                        <StatCard
                            title="Transazioni"
                            value={monthlyStats.transaction_count.toString()}
                            subtitle={currentMonth}
                        />
                    </div>

                    {/* Griglia principale */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Conti */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    I tuoi conti
                                </h3>
                                <Link href={route('accounts.index')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                    Vedi tutti
                                </Link>
                            </div>
                            <div className="space-y-3 p-4">
                                {accounts.length > 0 ? (
                                    accounts.map((account) => (
                                        <AccountCard
                                            key={account.id}
                                            account={account}
                                        />
                                    ))
                                ) : (
                                    <EmptyState message="Nessun conto trovato. Crea il tuo primo conto per iniziare!" />
                                )}
                            </div>
                        </div>

                        {/* Transazioni Recenti */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    Ultime transazioni
                                </h3>
                                <Link href={route('transactions.index')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                    Vedi tutte
                                </Link>
                            </div>
                            <div className="p-4">
                                {recentTransactions.length > 0 ? (
                                    recentTransactions.map((transaction) => (
                                        <TransactionRow
                                            key={transaction.id}
                                            transaction={transaction}
                                        />
                                    ))
                                ) : (
                                    <EmptyState message="Nessuna transazione registrata. Aggiungi la tua prima transazione!" />
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Budget e Debiti/Crediti */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Budget Attivi */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    📊 Budget Attivi
                                </h3>
                                <Link href={route('budgets.index')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                    Vedi tutti
                                </Link>
                            </div>
                            <div className="p-4">
                                {activeBudgets.length > 0 ? (
                                    <div className="space-y-3">
                                        {activeBudgets.map((budget) => (
                                            <BudgetCard key={budget.id} budget={budget} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <p className="mb-3 text-gray-500 dark:text-gray-400">
                                            Nessun budget attivo
                                        </p>
                                        <Link
                                            href={route('budgets.create')}
                                            className="text-sm text-emerald-500 hover:text-emerald-600"
                                        >
                                            Crea il tuo primo budget →
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Debiti e Crediti */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    💸 Debiti e Crediti
                                </h3>
                                <Link href={route('debts-credits.index')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                    Vedi tutti
                                </Link>
                            </div>
                            <div className="p-4">
                                {/* Riepilogo */}
                                {(debtsCreditsSummary.total_debts > 0 || debtsCreditsSummary.total_credits > 0) && (
                                    <div className="mb-4 grid grid-cols-2 gap-3">
                                        <div className="rounded-lg bg-red-50 p-3 text-center dark:bg-red-900/20">
                                            <p className="text-xs text-red-600 dark:text-red-400">Debiti</p>
                                            <p className="text-lg font-bold text-red-500">
                                                {formatCurrency(debtsCreditsSummary.total_debts)}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-900/20">
                                            <p className="text-xs text-emerald-600 dark:text-emerald-400">Crediti</p>
                                            <p className="text-lg font-bold text-emerald-500">
                                                {formatCurrency(debtsCreditsSummary.total_credits)}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                
                                {openDebtsCredits.length > 0 ? (
                                    <div>
                                        {openDebtsCredits.map((item) => (
                                            <DebtCreditRow key={item.id} item={item} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <p className="mb-3 text-gray-500 dark:text-gray-400">
                                            Nessun debito o credito aperto
                                        </p>
                                        <Link
                                            href={route('debts-credits.create')}
                                            className="text-sm text-emerald-500 hover:text-emerald-600"
                                        >
                                            Aggiungi il primo →
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Quick Actions */}
                    <div className="overflow-hidden rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Azioni rapide
                        </h3>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <QuickActionCard
                                href={route('transactions.create')}
                                icon={<PlusIcon size={28} />}
                                label="Nuova Transazione"
                            />
                            <QuickActionCard
                                href={route('transfers.create')}
                                icon="🔄"
                                label="Trasferimento"
                            />
                            <QuickActionCard
                                href={route('accounts.create')}
                                icon="🏦"
                                label="Nuovo Conto"
                            />
                            <QuickActionCard
                                href={route('categories.create')}
                                icon="🏷️"
                                label="Nuova Categoria"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
