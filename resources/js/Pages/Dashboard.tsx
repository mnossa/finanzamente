import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PlusIcon from '@/Components/Icons/PlusIcon';
import QuickActionCard from '@/Components/QuickActionCard';
import { Head, Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { ProgressBar } from '@/Components/ProgressBar';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';
import PageHeader from '@/Components/PageHeader';
import { ModuleAccessInfo, LockedModuleCard } from '@/Components/ModuleAccess';
import { useModules } from '@/hooks/useModules';
import RevenueProgressCard from '@/Components/RevenueProgressCard';
import TaxThermometer from '@/Components/TaxThermometer';
import LifestyleWidget, { LifestyleWidgetData } from '@/Components/LifestyleWidget';
import { PageProps } from '@/types';
import { DashboardLayoutConfig, WidgetId, WidgetSize } from '@/types/dashboard';
import { useDashboardLayout } from '@/hooks/useDashboardLayout';
import DashboardWidgetCard from '@/Components/DashboardWidgetCard';
import {
    DndContext,
    closestCenter,
    PointerSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
    DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    rectSortingStrategy,
} from '@dnd-kit/sortable';

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

interface AnnualRevenueData {
    visible: boolean;
    has_vat: boolean;
    revenue_tracking_enabled: boolean;
    annual_revenue: number;
    revenue_threshold: number;
    revenue_percentage: number;
}

interface TaxThermometerData {
    visible: boolean;
    has_vat: boolean;
    gross_income: number;
    tax_rate: number;
    inps_rate: number;
}

interface AssetAllocationEntry {
    asset_class: string;
    label: string;
    color: string;
    value: number;
    percentage: number;
}

interface AssetAllocationData {
    total_value: number;
    risk_index: number;
    risk_label: string;
    allocation: AssetAllocationEntry[];
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
    annualRevenueData: AnnualRevenueData;
    taxThermometerData: TaxThermometerData;
    lifestyleWidgetData: LifestyleWidgetData;
    dashboardLayout: DashboardLayoutConfig;
    assetAllocationData: AssetAllocationData;
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
        <CardBox className={className}>
            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</h3>
            <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{value}</p>
            {(subtitle || trendLabel) && (
                <div className="mt-2 flex items-center text-sm">
                    {trend && (
                        <span className={clsx('mr-1', trend === 'up' && 'text-green-500', trend === 'down' && 'text-red-500', trend === 'neutral' && 'text-gray-400')}>
                            {trend === 'up' && '↑'}
                            {trend === 'down' && '↓'}
                            {trend === 'neutral' && '→'}
                        </span>
                    )}
                    {trendLabel && (
                        <span className={clsx(trend === 'up' && 'text-green-500', trend === 'down' && 'text-red-500', trend === 'neutral' && 'text-gray-500')}>
                            {trendLabel}
                        </span>
                    )}
                    {subtitle && !trendLabel && (
                        <span className="text-gray-500 dark:text-gray-400">{subtitle}</span>
                    )}
                </div>
            )}
        </CardBox>
    );
}

function AccountCard({ account }: { account: Account }) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
            <div className="flex items-center space-x-3">
                <span className="text-2xl">{getAccountTypeIcon(account.type)}</span>
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {account.name}
                        {account.is_private && <span className="ml-2 text-xs text-gray-400">🔒</span>}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">{getAccountTypeLabel(account.type)}</p>
                </div>
            </div>
            <div className="text-right">
                <p className={clsx('text-lg font-semibold', account.current_balance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-500')}>
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
                            : isIncome ? '#22c55e20' : '#ef444420',
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
            <p className={clsx('font-semibold', isIncome ? 'text-green-500' : 'text-red-500')}>
                {isIncome ? '+' : ''}{formatCurrency(transaction.amount)}
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

function BudgetCard({ budget }: { budget: ActiveBudget }) {
    return (
        <Link
            href={route('budgets.show', budget.id)}
            className="block rounded-lg bg-gray-50 p-3 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
        >
            <div className="mb-2 flex items-center justify-between">
                <div className="flex items-center space-x-2">
                    <span>{budget.category_icon || '📁'}</span>
                    <span className="font-medium text-gray-900 dark:text-white">{budget.category_name}</span>
                </div>
                {budget.is_exceeded && <span className="text-red-500">⚠️</span>}
            </div>
            <ProgressBar percentage={budget.percentage} isExceeded={budget.is_exceeded} height="0.5rem" />
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
                <span className={clsx('flex h-8 w-8 items-center justify-center rounded-full text-sm', isDebt ? 'bg-red-100 dark:bg-red-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30')}>
                    {isDebt ? '📤' : '📥'}
                </span>
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {item.counterparty}
                        {isOverdue && <span className="ml-1 text-red-500">⚠️</span>}
                    </p>
                    {item.due_date && (
                        <p className={clsx('text-xs', isOverdue ? 'text-red-500' : 'text-gray-500 dark:text-gray-400')}>
                            Scadenza: {formatDate(item.due_date)}
                        </p>
                    )}
                </div>
            </div>
            <span className={clsx('font-semibold', isDebt ? 'text-red-500' : 'text-emerald-500')}>
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
    annualRevenueData,
    taxThermometerData,
    lifestyleWidgetData,
    dashboardLayout,
    assetAllocationData,
}: DashboardProps) {
    const { isModuleEnabled, isModuleLocked } = useModules();
    const { auth } = usePage<PageProps>().props;
    const hasVat = auth.user.user_type === 'partita_iva';

    const {
        sortedWidgets,
        isEditing,
        isSaving,
        saveError,
        toggleEditing,
        cancelEditing,
        toggleWidgetVisibility,
        setWidgetSize,
        moveWidget,
        saveLayout,
        resetLayout,
    } = useDashboardLayout(dashboardLayout);

    const incomeTrend =
        lastMonthStats.income > 0
            ? ((monthlyStats.income - lastMonthStats.income) / lastMonthStats.income) * 100
            : monthlyStats.income > 0 ? 100 : 0;

    const expensesTrend =
        lastMonthStats.expenses > 0
            ? ((monthlyStats.expenses - lastMonthStats.expenses) / lastMonthStats.expenses) * 100
            : monthlyStats.expenses > 0 ? 100 : 0;

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 3 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
    );

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;
        if (!over || active.id === over.id) return;
        const oldIndex = sortedWidgets.findIndex((w) => w.id === active.id);
        const newIndex = sortedWidgets.findIndex((w) => w.id === over.id);
        if (oldIndex !== -1 && newIndex !== -1) moveWidget(oldIndex, newIndex);
    }

    function renderWidgetContent(widgetId: WidgetId, size: string): React.ReactNode {
        switch (widgetId) {
            case 'total_balance':
                return (
                    <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                        <h3 className="text-sm font-medium text-slate-300">Saldo Totale</h3>
                        <p className="mt-2 text-4xl font-bold">{formatCurrency(totalBalance)}</p>
                        <p className="mt-1 text-sm text-slate-400">
                            {accounts.length} {accounts.length === 1 ? 'conto attivo' : 'conti attivi'}
                        </p>
                    </div>
                );

            case 'monthly_stats':
                return (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard title="Entrate" value={formatCurrency(monthlyStats.income)} subtitle={currentMonth} trend={incomeTrend >= 0 ? 'up' : 'down'} trendLabel={`${incomeTrend >= 0 ? '+' : ''}${incomeTrend.toFixed(0)}% vs ${lastMonth}`} />
                        <StatCard title="Uscite" value={formatCurrency(monthlyStats.expenses)} subtitle={currentMonth} trend={expensesTrend <= 0 ? 'up' : 'down'} trendLabel={`${expensesTrend >= 0 ? '+' : ''}${expensesTrend.toFixed(0)}% vs ${lastMonth}`} />
                        <StatCard title="Saldo Netto" value={formatCurrency(monthlyStats.net)} subtitle={currentMonth} trend={monthlyStats.net >= 0 ? 'up' : 'down'} />
                        <StatCard title="Transazioni" value={monthlyStats.transaction_count.toString()} subtitle={currentMonth} />
                    </div>
                );

            case 'annual_revenue':
                if (!annualRevenueData.visible) return null;
                return (
                    <RevenueProgressCard
                        currentRevenue={annualRevenueData.annual_revenue}
                        threshold={annualRevenueData.revenue_threshold}
                        percentage={annualRevenueData.revenue_percentage}
                        year={new Date().getFullYear()}
                    />
                );

            case 'tax_thermometer':
                if (!taxThermometerData.visible) return null;
                return (
                    <TaxThermometer
                        grossIncome={taxThermometerData.gross_income}
                        taxRate={taxThermometerData.tax_rate}
                        inpsRate={taxThermometerData.inps_rate}
                    />
                );

            case 'lifestyle_widget':
                return <LifestyleWidget data={lifestyleWidgetData} />;

            case 'accounts':
                return (
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">I tuoi conti</h3>
                            <Link href={route('accounts.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className="space-y-3 p-4">
                            {accounts.length > 0
                                ? accounts.map((account) => <AccountCard key={account.id} account={account} />)
                                : <EmptyState message="Nessun conto trovato. Crea il tuo primo conto per iniziare!" />}
                        </div>
                    </CardBox>
                );

            case 'recent_transactions':
                return (
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">Ultime transazioni</h3>
                            <Link href={route('transactions.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutte</Link>
                        </div>
                        <div className="p-4">
                            {recentTransactions.length > 0
                                ? recentTransactions.map((transaction) => <TransactionRow key={transaction.id} transaction={transaction} />)
                                : <EmptyState message="Nessuna transazione registrata. Aggiungi la tua prima transazione!" />}
                        </div>
                    </CardBox>
                );

            case 'active_budgets':
                return isModuleEnabled('budgets') ? (
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">📊 Budget Attivi</h3>
                            <Link href={route('budgets.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className="p-4">
                            {activeBudgets.length > 0 ? (
                                <div className="space-y-3">
                                    {activeBudgets.map((budget) => <BudgetCard key={budget.id} budget={budget} />)}
                                </div>
                            ) : (
                                <div className="py-8 text-center">
                                    <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun budget attivo</p>
                                    <Link href={route('budgets.create')} className="text-sm text-emerald-500 hover:text-emerald-600">Crea il tuo primo budget →</Link>
                                </div>
                            )}
                        </div>
                    </CardBox>
                ) : <LockedModuleCard moduleId="budgets" />;

            case 'debts_credits':
                return isModuleEnabled('debts_credits') ? (
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">💸 Debiti e Crediti</h3>
                            <Link href={route('debts-credits.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className="p-4">
                            {(debtsCreditsSummary.total_debts > 0 || debtsCreditsSummary.total_credits > 0) && (
                                <div className="mb-4 grid grid-cols-2 gap-3">
                                    <div className="rounded-lg bg-red-50 p-3 text-center dark:bg-red-900/20">
                                        <p className="text-xs text-red-600 dark:text-red-400">Debiti</p>
                                        <p className="text-lg font-bold text-red-500">{formatCurrency(debtsCreditsSummary.total_debts)}</p>
                                    </div>
                                    <div className="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-900/20">
                                        <p className="text-xs text-emerald-600 dark:text-emerald-400">Crediti</p>
                                        <p className="text-lg font-bold text-emerald-500">{formatCurrency(debtsCreditsSummary.total_credits)}</p>
                                    </div>
                                </div>
                            )}
                            {openDebtsCredits.length > 0 ? (
                                <div>{openDebtsCredits.map((item) => <DebtCreditRow key={item.id} item={item} />)}</div>
                            ) : (
                                <div className="py-8 text-center">
                                    <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun debito o credito aperto</p>
                                    <Link href={route('debts-credits.create')} className="text-sm text-emerald-500 hover:text-emerald-600">Aggiungi il primo →</Link>
                                </div>
                            )}
                        </div>
                    </CardBox>
                ) : <LockedModuleCard moduleId="debts_credits" />;

            case 'quick_actions': {
                const compact = size === 'sm';
                return (
                    <CardBox className="overflow-hidden p-4 shadow-sm">
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">Azioni rapide</h3>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <QuickActionCard href={route('transactions.create')} icon={<PlusIcon size={28} />} label="Nuova Transazione" compact={compact} />
                            <QuickActionCard href={route('transactions.quick-session')} icon="⚡" label="Sessione Rapida" compact={compact} />
                            <QuickActionCard href={route('transfers.create')} icon="🔄" label="Trasferimento" compact={compact} />
                            <QuickActionCard href={route('accounts.create')} icon="🏦" label="Nuovo Conto" compact={compact} />
                            <QuickActionCard href={route('categories.create')} icon="🏷️" label="Nuova Categoria" compact={compact} />
                        </div>
                    </CardBox>
                );
            }

            case 'asset_allocation': {
                const { allocation, total_value, risk_index, risk_label } = assetAllocationData;
                const riskColor = risk_index <= 2
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : risk_index <= 4
                        ? 'text-amber-600 dark:text-amber-400'
                        : 'text-red-600 dark:text-red-400';

                return isModuleEnabled('investments') ? (
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">📊 Asset Allocation</h3>
                            <Link href={route('asset-allocation.index')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                Dettaglio →
                            </Link>
                        </div>
                        <div className="p-4 space-y-3">
                            {total_value > 0 ? (
                                <>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-gray-500 dark:text-gray-400">Patrimonio totale</span>
                                        <span className="text-lg font-bold text-gray-900 dark:text-white">
                                            {formatCurrency(total_value)}
                                        </span>
                                    </div>
                                    {/* Mini allocation bar */}
                                    <div className="flex h-2 rounded-full overflow-hidden gap-px">
                                        {allocation.map(a => (
                                            <div
                                                key={a.asset_class}
                                                style={{ width: `${a.percentage}%`, backgroundColor: a.color }}
                                                title={`${a.label}: ${a.percentage.toFixed(1)}%`}
                                            />
                                        ))}
                                    </div>
                                    {/* Legend */}
                                    <div className="flex flex-wrap gap-x-3 gap-y-1">
                                        {allocation.map(a => (
                                            <span key={a.asset_class} className="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                                <span className="h-2 w-2 rounded-full flex-shrink-0" style={{ backgroundColor: a.color }} />
                                                {a.label} {a.percentage.toFixed(0)}%
                                            </span>
                                        ))}
                                    </div>
                                    <div className="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-gray-700">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">Rischio</span>
                                        <span className={`text-sm font-semibold ${riskColor}`}>
                                            {risk_label} — {risk_index.toFixed(1)}/7
                                        </span>
                                    </div>
                                </>
                            ) : (
                                <div className="py-4 text-center">
                                    <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                        Nessuna posizione trovata
                                    </p>
                                    <Link href={route('investments.create')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                        Aggiungi il primo →
                                    </Link>
                                </div>
                            )}
                        </div>
                    </CardBox>
                ) : <LockedModuleCard moduleId="investments" />;
            }

            default:
                return null;
        }
    }

    function isWidgetRenderable(widgetId: WidgetId): boolean {
        switch (widgetId) {
            case 'annual_revenue':
                return annualRevenueData.visible || hasVat;
            case 'tax_thermometer':
                return taxThermometerData.visible || hasVat;
            default:
                return true;
        }
    }

    return (
        <AuthenticatedLayout header={<PageHeader title="Dashboard" />}>
            <Head title="Dashboard" />

            {/* FAB personalizzazione — non editing */}
            {!isEditing && (
                <button
                    type="button"
                    onClick={toggleEditing}
                    className="fixed bottom-6 right-6 z-40 hidden h-12 w-12 items-center justify-center rounded-full bg-white shadow-lg ring-1 ring-gray-200 transition-all hover:bg-gray-50 hover:shadow-xl sm:flex dark:bg-gray-800 dark:ring-gray-700 dark:hover:bg-gray-700"
                    aria-label="Personalizza la dashboard"
                    title="Personalizza dashboard"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5 text-gray-600 dark:text-gray-300" aria-hidden="true">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                    </svg>
                </button>
            )}

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    <ModuleAccessInfo />

                    {/* Barra personalizzazione dashboard — solo in editing */}
                    {isEditing && (
                        <div
                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border-2 border-dashed border-emerald-400 bg-emerald-50 px-4 py-3 dark:border-emerald-600 dark:bg-emerald-900/20"
                            role="region"
                            aria-label="Modalità personalizzazione dashboard"
                        >
                            <div className="flex items-center gap-2">
                                <span className="text-lg" aria-hidden="true">✏️</span>
                                <span className="font-semibold text-emerald-800 dark:text-emerald-200">Modalità personalizzazione</span>
                                <span className="hidden text-sm text-emerald-600 dark:text-emerald-400 sm:inline">
                                    — Afferra l'icona ⠿ per riordinare o usa i selettori S/M/L per cambiare dimensione.
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {saveError && (
                                    <span className="text-sm text-red-600 dark:text-red-400" role="alert">{saveError}</span>
                                )}
                                <button
                                    type="button"
                                    onClick={resetLayout}
                                    className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Ripristina default
                                </button>
                                <button
                                    type="button"
                                    onClick={cancelEditing}
                                    className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Annulla
                                </button>
                                <button
                                    type="button"
                                    onClick={saveLayout}
                                    disabled={isSaving}
                                    className="rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-600 disabled:opacity-60"
                                >
                                    {isSaving ? 'Salvataggio…' : 'Salva layout'}
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Griglia widget con DnD */}
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={handleDragEnd}
                    >
                        <SortableContext
                            items={sortedWidgets.filter((w) => isWidgetRenderable(w.id)).map((w) => w.id)}
                            strategy={rectSortingStrategy}
                        >
                            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-6">
                                {sortedWidgets.map((widget) => {
                                    const renderable = isWidgetRenderable(widget.id);
                                    const content = renderWidgetContent(widget.id, widget.size);

                                    if (!renderable) return null;
                                    if (!widget.visible && !isEditing) return null;
                                    if (content === null && !isEditing) return null;

                                    return (
                                        <DashboardWidgetCard
                                            key={widget.id}
                                            widget={widget}
                                            isEditing={isEditing}
                                            onToggleVisibility={() => toggleWidgetVisibility(widget.id)}
                                            onChangeSize={(size: WidgetSize) => setWidgetSize(widget.id, size)}
                                        >
                                            {content}
                                        </DashboardWidgetCard>
                                    );
                                })}
                            </div>
                        </SortableContext>
                    </DndContext>

                    {/* Moduli Suggeriti (se bloccati) */}
                    {(isModuleEnabled('investments') === false && isModuleLocked('investments') || isModuleEnabled('vat_management') === false && isModuleLocked('vat_management')) && !isEditing && (
                        <div>
                            <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">✨ Sblocca Nuove Funzionalità</h3>
                            <div className="grid gap-6 lg:grid-cols-2">
                                {!isModuleEnabled('investments') && isModuleLocked('investments') && <LockedModuleCard moduleId="investments" />}
                                {!isModuleEnabled('vat_management') && isModuleLocked('vat_management') && <LockedModuleCard moduleId="vat_management" />}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
