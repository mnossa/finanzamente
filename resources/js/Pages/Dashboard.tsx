import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PlusIcon from '@/Components/Icons/PlusIcon';
import QuickActionCard from '@/Components/QuickActionCard';
import { Head, Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { ProgressBar } from '@/Components/ProgressBar';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import { LockedModuleCard } from '@/Components/ModuleAccess';
import { useModules } from '@/hooks/useModules';
import RevenueProgressCard from '@/Components/RevenueProgressCard';
import TaxThermometer from '@/Components/TaxThermometer';
import LifestyleWidget, { LifestyleWidgetData } from '@/Components/LifestyleWidget';
import CashFlowChart, { CashFlowDataPoint } from '@/Components/Charts/CashFlowChart';
import NetWorthChart, { NetWorthDataPoint } from '@/Components/Charts/NetWorthChart';
import ExpenseTreemap, { ExpenseCategory } from '@/Components/Charts/ExpenseTreemap';
import ExpenseDistributionWidget, { ExpenseDistributionData } from '@/Components/ExpenseDistributionWidget';
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
import { useState } from 'react';
import { moneyKpiGrid2, moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency, formatDateShort } from '@/utils/format';

/** Padding widget dashboard: compatto su mobile, come le altre sezioni. */
const widgetHeaderClass =
    'flex items-center justify-between border-b border-gray-100 px-3 py-2.5 sm:px-4 sm:py-3 dark:border-gray-700';
const widgetBodyClass = 'p-3 sm:p-4';
const widgetListBodyClass = 'space-y-1.5 px-3 py-2 sm:p-3';
const widgetEmptyClass = 'py-4 text-center sm:py-6';

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
    currency_code: string;
    currency_symbol: string;
}

interface OpenDebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    type: string;
    status: string;
    due_date: string | null;
    currency_code: string;
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

interface FinancialGoal {
    id: number;
    name: string;
    icon: string | null;
    color: string | null;
    target_amount: number;
    current_amount: number;
    currency_code: string;
    target_date: string | null;
    percentage: number;
}

interface FinancialGoal {
    id: number;
    name: string;
    icon: string | null;
    color: string | null;
    target_amount: number;
    current_amount: number;
    currency_code: string;
    target_date: string | null;
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
    balanceBreakdown: {
        total: number;
        invested: number;
    };
    recentTransactions: Transaction[];
    monthlyStats: MonthlyStats;
    lastMonthStats: MonthlyStats;
    periodLabel: string;
    previousPeriodLabel: string;
    activeBudgets: ActiveBudget[];
    openDebtsCredits: OpenDebtCredit[];
    debtsCreditsSummary: DebtsCreditsSummary;
    annualRevenueData: AnnualRevenueData;
    taxThermometerData: TaxThermometerData;
    lifestyleWidgetData: LifestyleWidgetData;
    dashboardLayout: DashboardLayoutConfig;
    assetAllocationData: AssetAllocationData;
    netWorthData: NetWorthDataPoint[];
    cashFlowData: CashFlowDataPoint[];
    expenseCategories: ExpenseCategory[];
    financialGoals: FinancialGoal[];
    expenseDistributionData: ExpenseDistributionData;
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
        <CardBox className={clsx('!p-3 sm:!p-4', className)}>
            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</h3>
            <p className={clsx('mt-1.5 text-xl font-bold text-gray-900 dark:text-white sm:mt-2 sm:text-2xl', moneyTabular)}>{value}</p>
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
        <div className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-700/50">
            <div className="flex items-center gap-2">
                <span className="text-lg">{getAccountTypeIcon(account.type)}</span>
                <div>
                    <p className="text-sm font-medium text-gray-900 dark:text-white leading-tight">
                        {account.name}
                        {account.is_private && <span className="ml-1 text-xs text-gray-400">🔒</span>}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{getAccountTypeLabel(account.type)}</p>
                </div>
            </div>
            <p className={clsx('text-sm font-semibold', moneyTabular, account.current_balance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-500')}>
                {formatCurrency(account.current_balance, account.currency_code)}
            </p>
        </div>
    );
}

function TransactionRow({ transaction }: { transaction: Transaction }) {
    const isIncome = transaction.amount > 0;
    return (
        <Link
            href={route('transactions.show', transaction.id)}
            className="flex items-center gap-2 rounded-lg px-1 py-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50 sm:px-2"
        >
            <div
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm"
                style={{
                    backgroundColor: transaction.category?.color
                        ? `${transaction.category.color}20`
                        : isIncome ? '#22c55e20' : '#ef444420',
                }}
            >
                {transaction.category?.icon || (isIncome ? '💰' : '💸')}
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-gray-900 dark:text-white">
                    {transaction.description || transaction.category?.name || 'Transazione'}
                </p>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    {transaction.account.name} · {formatDateShort(transaction.date)}
                </p>
            </div>
            <p className={clsx('text-sm font-semibold shrink-0', moneyTabular, isIncome ? 'text-green-500' : 'text-red-500')}>
                {isIncome ? '+' : ''}{formatCurrency(transaction.amount)}
            </p>
        </Link>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-4 text-center sm:py-6">
            <div className="mb-2 text-2xl">📊</div>
            <p className="text-sm text-gray-500 dark:text-gray-400">{message}</p>
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
                <span className={moneyTabular}>{formatCurrency(budget.spent, budget.currency_code)}</span>
                <span className={moneyTabular}>{budget.percentage}%</span>
                <span className={moneyTabular}>{formatCurrency(budget.amount, budget.currency_code)}</span>
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
                            Scadenza: {formatDateShort(item.due_date)}
                        </p>
                    )}
                </div>
            </div>
            <span className={clsx('font-semibold', moneyTabular, isDebt ? 'text-red-500' : 'text-emerald-500')}>
                {isDebt ? '-' : '+'}{formatCurrency(item.amount, item.currency_code)}
            </span>
        </Link>
    );
}

export default function Dashboard({
    accounts,
    totalBalance,
    balanceBreakdown,
    recentTransactions,
    monthlyStats,
    lastMonthStats,
    periodLabel,
    previousPeriodLabel,
    activeBudgets,
    openDebtsCredits,
    debtsCreditsSummary,
    annualRevenueData,
    taxThermometerData,
    lifestyleWidgetData,
    dashboardLayout,
    assetAllocationData,
    netWorthData,
    cashFlowData,
    expenseCategories,
    financialGoals,
    expenseDistributionData,
}: DashboardProps) {
    const [hideModuleMessage, setHideModuleMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
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
        hideWidgetsAndSave,
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

    async function hideLockedWidget(widgetIds: WidgetId[]): Promise<void> {
        try {
            await hideWidgetsAndSave(widgetIds);
            setHideModuleMessage({
                type: 'success',
                text: 'Modulo nascosto dalla dashboard. Preferenza salvata.',
            });
        } catch {
            setHideModuleMessage({
                type: 'error',
                text: 'Non sono riuscito a nascondere il modulo. Riprova.',
            });
        }
    }

    function renderWidgetContent(widgetId: WidgetId, size: string): React.ReactNode {
        switch (widgetId) {
            case 'total_balance':
                return (
                    <Link href={route('patrimonio.index')} className="block">
                        <div className="overflow-hidden rounded-2xl bg-linear-to-br from-slate-800 to-slate-900 p-4 text-white shadow-lg transition-shadow hover:shadow-xl sm:p-5">
                            <h3 className="text-sm font-medium text-slate-300">Saldo Totale</h3>
                            <p className={clsx('mt-1.5 text-3xl font-bold sm:mt-2 sm:text-4xl', moneyTabular)}>
                                {formatCurrency(balanceBreakdown?.total ?? totalBalance)}
                            </p>
                            <p className="mt-1 text-sm text-slate-400">
                                Di cui investimenti {formatCurrency(balanceBreakdown?.invested ?? 0)}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {accounts.length} {accounts.length === 1 ? 'conto attivo' : 'conti attivi'} · Dettaglio patrimonio
                            </p>
                        </div>
                    </Link>
                );

            case 'monthly_stats':
                return (
                    <div className={moneyKpiGrid2}>
                        <StatCard
                            title="Entrate"
                            value={formatCurrency(monthlyStats.income)}
                            subtitle={periodLabel}
                            trend={incomeTrend >= 0 ? 'up' : 'down'}
                            trendLabel={`${incomeTrend >= 0 ? '+' : ''}${incomeTrend.toFixed(0)}% vs ${previousPeriodLabel.toLowerCase()}`}
                        />
                        <StatCard
                            title="Uscite"
                            value={formatCurrency(monthlyStats.expenses)}
                            subtitle={periodLabel}
                            trend={expensesTrend <= 0 ? 'up' : 'down'}
                            trendLabel={`${expensesTrend >= 0 ? '+' : ''}${expensesTrend.toFixed(0)}% vs ${previousPeriodLabel.toLowerCase()}`}
                        />
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
                return isModuleEnabled('lifestyle_score')
                    ? <LifestyleWidget data={lifestyleWidgetData} />
                    : (
                        <LockedModuleCard
                            moduleId="lifestyle_score"
                            showHideButton
                            onHideModule={() => hideLockedWidget(['lifestyle_widget'])}
                            isHiding={isSaving}
                        />
                    );

            case 'accounts':
                return (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">I tuoi conti</h3>
                            <Link href={route('accounts.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className={widgetListBodyClass}>
                            {accounts.length > 0
                                ? accounts.map((account) => <AccountCard key={account.id} account={account} />)
                                : <EmptyState message="Nessun conto trovato. Crea il tuo primo conto per iniziare!" />}
                        </div>
                    </div>
                );

            case 'recent_transactions':
                return (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">Ultime transazioni</h3>
                            <Link href={route('transactions.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutte</Link>
                        </div>
                        <div className={widgetListBodyClass}>
                            {recentTransactions.length > 0
                                ? recentTransactions.map((transaction) => <TransactionRow key={transaction.id} transaction={transaction} />)
                                : <EmptyState message="Nessuna transazione registrata. Aggiungi la tua prima transazione!" />}
                        </div>
                    </div>
                );

            case 'active_budgets':
                return isModuleEnabled('budgets') ? (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">📊 Budget Attivi</h3>
                            <Link href={route('budgets.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className={widgetBodyClass}>
                            {activeBudgets.length > 0 ? (
                                <div className="space-y-3">
                                    {activeBudgets.map((budget) => <BudgetCard key={budget.id} budget={budget} />)}
                                </div>
                            ) : (
                                <div className={widgetEmptyClass}>
                                    <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun budget attivo</p>
                                    <Link href={route('budgets.create')} className="text-sm text-emerald-500 hover:text-emerald-600">Crea il tuo primo budget →</Link>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <LockedModuleCard
                        moduleId="budgets"
                        showHideButton
                        onHideModule={() => hideLockedWidget(['active_budgets'])}
                        isHiding={isSaving}
                    />
                );

            case 'debts_credits':
                return isModuleEnabled('debts_credits') ? (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">💸 Debiti e Crediti</h3>
                            <Link href={route('debts-credits.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className={widgetBodyClass}>
                            {(debtsCreditsSummary.total_debts > 0 || debtsCreditsSummary.total_credits > 0) && (
                                <div className={clsx('mb-4', moneyKpiGrid2)}>
                                    <div className="rounded-lg bg-red-50 p-3 text-center dark:bg-red-900/20">
                                        <p className="text-xs text-red-600 dark:text-red-400">Debiti</p>
                                        <p className={clsx('text-lg font-bold text-red-500', moneyTabular)}>{formatCurrency(debtsCreditsSummary.total_debts)}</p>
                                    </div>
                                    <div className="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-900/20">
                                        <p className="text-xs text-emerald-600 dark:text-emerald-400">Crediti</p>
                                        <p className={clsx('text-lg font-bold text-emerald-500', moneyTabular)}>{formatCurrency(debtsCreditsSummary.total_credits)}</p>
                                    </div>
                                </div>
                            )}
                            {openDebtsCredits.length > 0 ? (
                                <div>{openDebtsCredits.map((item) => <DebtCreditRow key={item.id} item={item} />)}</div>
                            ) : (
                                <div className={widgetEmptyClass}>
                                    <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun debito o credito aperto</p>
                                    <Link href={route('debts-credits.create')} className="text-sm text-emerald-500 hover:text-emerald-600">Aggiungi il primo →</Link>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <LockedModuleCard
                        moduleId="debts_credits"
                        showHideButton
                        onHideModule={() => hideLockedWidget(['debts_credits'])}
                        isHiding={isSaving}
                    />
                );

            case 'quick_actions': {
                const compact = size === 'sm';
                return (
                    <div className="hidden lg:block">
                        <div className="overflow-hidden rounded-xl bg-white p-3 shadow-sm dark:bg-gray-800 sm:p-4">
                            <h3 className="mb-2 font-semibold text-gray-900 dark:text-white sm:mb-3">Azioni rapide</h3>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                                <QuickActionCard href={route('transactions.create')} icon={<PlusIcon size={28} />} label="Nuova Transazione" compact={compact} />
                                <QuickActionCard href={route('transactions.quick-session')} icon="⚡" label="Sessione Rapida" compact={compact} />
                                <QuickActionCard href={route('transfers.create')} icon="🔄" label="Trasferimento" compact={compact} />
                                <QuickActionCard href={route('accounts.create')} icon="🏦" label="Nuovo Conto" compact={compact} />
                                <QuickActionCard href={route('categories.create')} icon="🏷️" label="Nuova Categoria" compact={compact} />
                            </div>
                        </div>
                    </div>
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
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">📊 Asset Allocation</h3>
                            <Link href={route('asset-allocation.index')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                Dettaglio →
                            </Link>
                        </div>
                        <div className={clsx(widgetBodyClass, 'space-y-3')}>
                            {total_value > 0 ? (
                                <>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-gray-500 dark:text-gray-400">Patrimonio totale</span>
                                        <span className={clsx('text-lg font-bold text-gray-900 dark:text-white', moneyTabular)}>
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
                                                <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: a.color }} />
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
                    </div>
                ) : (
                    <LockedModuleCard
                        moduleId="investments"
                        showHideButton
                        onHideModule={() => hideLockedWidget(['asset_allocation'])}
                        isHiding={isSaving}
                    />
                );
            }

            case 'net_worth':
                return (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">📈 Patrimonio nel Tempo</h3>
                            <Link href={route('analytics.net-worth')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                Dettaglio →
                            </Link>
                        </div>
                        <div className={widgetBodyClass}>
                            <NetWorthChart data={netWorthData} />
                        </div>
                    </div>
                );

            case 'cash_flow':
                return (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">💰 Panoramica Cashflow</h3>
                            <Link href={route('analytics.cash-flow')} className="text-sm text-emerald-500 hover:text-emerald-600">
                                Dettaglio →
                            </Link>
                        </div>
                        <div className={widgetBodyClass}>
                            <CashFlowChart data={cashFlowData} />
                        </div>
                    </div>
                );

            case 'expense_treemap':
                return (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">🏷️ Spese per Categoria</h3>
                            <Link
                                href={route('analytics.expenses-by-category', { month: new Date().toISOString().slice(0, 7) })}
                                className="text-sm text-emerald-500 hover:text-emerald-600"
                            >
                                Dettaglio →
                            </Link>
                        </div>
                        <div className={widgetBodyClass}>
                            <ExpenseTreemap data={expenseCategories} />
                        </div>
                    </div>
                );

            case 'financial_goals':
                return isModuleEnabled('financial_goals') ? (
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div className={widgetHeaderClass}>
                            <h3 className="font-semibold text-gray-900 dark:text-white">🎯 Obiettivi Finanziari</h3>
                            <Link href={route('financial-goals.index')} className="text-sm text-emerald-500 hover:text-emerald-600">Vedi tutti</Link>
                        </div>
                        <div className={widgetBodyClass}>
                            {financialGoals.length > 0 ? (
                                <div className="space-y-3">
                                    {financialGoals.map((goal) => (
                                        <Link
                                            key={goal.id}
                                            href={route('financial-goals.show', goal.id)}
                                            className="block rounded-lg bg-gray-50 p-3 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                                        >
                                            <div className="mb-2 flex items-center justify-between">
                                                <div className="flex items-center space-x-2">
                                                    <span>{goal.icon || '🎯'}</span>
                                                    <span className="font-medium text-gray-900 dark:text-white">{goal.name}</span>
                                                </div>
                                                <span className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">{goal.percentage}%</span>
                                            </div>
                                            <ProgressBar percentage={goal.percentage} isExceeded={false} height="0.5rem" />
                                            <div className="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                                <span className={moneyTabular}>{formatCurrency(goal.current_amount, goal.currency_code)}</span>
                                                <span className={moneyTabular}>{formatCurrency(goal.target_amount, goal.currency_code)}</span>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <div className={widgetEmptyClass}>
                                    <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun obiettivo attivo</p>
                                    <Link href={route('financial-goals.create')} className="text-sm text-emerald-500 hover:text-emerald-600">Crea il tuo primo obiettivo →</Link>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <LockedModuleCard
                        moduleId="financial_goals"
                        showHideButton
                        onHideModule={() => hideLockedWidget(['financial_goals'])}
                        isHiding={isSaving}
                    />
                );

            case 'expense_distribution':
                return <ExpenseDistributionWidget data={expenseDistributionData} />;

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

    function isWidgetVisible(widgetId: WidgetId): boolean {
        const widget = sortedWidgets.find((item) => item.id === widgetId);
        return widget?.visible ?? false;
    }

    const shouldShowInvestmentsUpsell =
        !isModuleEnabled('investments') &&
        isModuleLocked('investments') &&
        isWidgetVisible('asset_allocation');

    const shouldShowVatUpsell =
        !isModuleEnabled('vat_management') &&
        isModuleLocked('vat_management') &&
        (isWidgetVisible('annual_revenue') || isWidgetVisible('tax_thermometer'));

    return (
        <AuthenticatedLayout header={<PageHeader title="Dashboard" />}>
            <Head title="Dashboard" />
            {hideModuleMessage && (
                <div className="fixed right-4 top-4 z-50 max-w-sm">
                    <div
                        className={clsx(
                            'rounded-lg border px-4 py-3 text-sm shadow-lg',
                            hideModuleMessage.type === 'success'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200'
                                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-200'
                        )}
                        role="status"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <span>{hideModuleMessage.text}</span>
                            <button
                                type="button"
                                onClick={() => setHideModuleMessage(null)}
                                className="text-xs font-semibold opacity-70 hover:opacity-100"
                                aria-label="Chiudi notifica"
                            >
                                Chiudi
                            </button>
                        </div>
                    </div>
                </div>
            )}

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

            <PageContent maxWidth="7xl">
                    {/* Barra personalizzazione dashboard — solo in editing */}
                    {isEditing && (
                        <div
                            className="flex flex-wrap items-center justify-between gap-2 rounded-xl border-2 border-dashed border-emerald-400 bg-emerald-50 px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3 dark:border-emerald-600 dark:bg-emerald-900/20"
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
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4 xl:grid-cols-6 xl:gap-6">
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
                    {(shouldShowInvestmentsUpsell || shouldShowVatUpsell) && !isEditing && (
                        <div>
                            <h3 className="mb-2 text-base font-semibold text-gray-900 dark:text-white sm:mb-4 sm:text-lg">✨ Sblocca Nuove Funzionalità</h3>
                            <div className="grid gap-3 sm:gap-4 lg:grid-cols-2 lg:gap-6">
                                {shouldShowInvestmentsUpsell && (
                                    <LockedModuleCard
                                        moduleId="investments"
                                        showHideButton
                                        onHideModule={() => hideLockedWidget(['asset_allocation'])}
                                        isHiding={isSaving}
                                    />
                                )}
                                {shouldShowVatUpsell && (
                                    <LockedModuleCard
                                        moduleId="vat_management"
                                        showHideButton
                                        onHideModule={() => hideLockedWidget(['annual_revenue', 'tax_thermometer'])}
                                        isHiding={isSaving}
                                    />
                                )}
                            </div>
                        </div>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
