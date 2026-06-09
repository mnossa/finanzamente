import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PlusIcon from '@/Components/Icons/PlusIcon';
import QuickActionCard from '@/Components/QuickActionCard';
import { Head, Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
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
import DashboardWidgetShell, {
    DashboardWidgetSegmentedControl,
    dashboardWidgetEmptyClass as widgetEmptyClass,
    dashboardWidgetHeaderClass as widgetHeaderClass,
    dashboardWidgetListBodyClass as widgetListBodyClass,
} from '@/Components/Dashboard/DashboardWidgetShell';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import { contentPanelHeaderClass } from '@/Components/IndexPageListToolbars';
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
        investedLinked: number;
        patrimonioTotal: number;
    };
    recentTransactions: Transaction[];
    periodStats: MonthlyStats;
    previousPeriodStats: MonthlyStats;
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
    netWorthCashData: NetWorthDataPoint[];
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
    const detail = trendLabel ? (
        <span className="flex items-center">
            {trend ? (
                <span className={clsx('mr-1', trend === 'up' && 'text-green-500', trend === 'down' && 'text-red-500', trend === 'neutral' && 'text-gray-400')}>
                    {trend === 'up' && '↑'}
                    {trend === 'down' && '↓'}
                    {trend === 'neutral' && '→'}
                </span>
            ) : null}
            <span className={clsx(trend === 'up' && 'text-green-500', trend === 'down' && 'text-red-500', trend === 'neutral' && 'text-gray-500')}>
                {trendLabel}
            </span>
        </span>
    ) : subtitle;

    return (
        <IndexKpiCell
            label={title}
            value={value}
            detail={detail}
            className={className}
        />
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
    periodStats,
    previousPeriodStats,
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
    netWorthCashData,
    cashFlowData,
    expenseCategories,
    financialGoals,
    expenseDistributionData,
}: DashboardProps) {
    const [hideModuleMessage, setHideModuleMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [netWorthMode, setNetWorthMode] = useState<'portfolio' | 'cash'>('portfolio');
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
        previousPeriodStats.income > 0
            ? ((periodStats.income - previousPeriodStats.income) / previousPeriodStats.income) * 100
            : periodStats.income > 0 ? 100 : 0;

    const expensesTrend =
        previousPeriodStats.expenses > 0
            ? ((periodStats.expenses - previousPeriodStats.expenses) / previousPeriodStats.expenses) * 100
            : periodStats.expenses > 0 ? 100 : 0;

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
                            <h3 className="text-sm font-medium text-slate-300">Saldo conti</h3>
                            <p className={clsx('mt-1.5 text-3xl font-bold sm:mt-2 sm:text-4xl', moneyTabular)}>
                                {formatCurrency(balanceBreakdown?.total ?? totalBalance)}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">Somma saldi conti attivi (liquidità)</p>
                            <p className="mt-2 text-sm text-slate-400">
                                Investimenti aperti{' '}
                                <span className={moneyTabular}>{formatCurrency(balanceBreakdown?.invested ?? 0)}</span>
                            </p>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Costo di carico · non incluso nel saldo conti
                            </p>
                            <p className="mt-2 border-t border-slate-700/60 pt-2 text-sm text-slate-300">
                                Patrimonio netto{' '}
                                <span className={moneyTabular}>
                                    {formatCurrency(
                                        balanceBreakdown?.patrimonioTotal
                                            ?? (balanceBreakdown?.total ?? totalBalance) + (balanceBreakdown?.investedLinked ?? 0),
                                    )}
                                </span>
                            </p>
                            <p className="mt-0.5 text-xs text-slate-500">
                                Saldo conti + investimenti collegati al ledger (costo di carico)
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
                            value={formatCurrency(periodStats.income)}
                            subtitle={periodLabel}
                            trend={incomeTrend >= 0 ? 'up' : 'down'}
                            trendLabel={`${incomeTrend >= 0 ? '+' : ''}${incomeTrend.toFixed(0)}% vs ${previousPeriodLabel.toLowerCase()}`}
                        />
                        <StatCard
                            title="Uscite"
                            value={formatCurrency(periodStats.expenses)}
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
                    <DashboardWidgetShell
                        title="I tuoi conti"
                        detailHref={route('accounts.index')}
                        detailLabel="Vedi tutti"
                        bodyClassName={widgetListBodyClass}
                    >
                        {accounts.length > 0
                            ? accounts.map((account) => <AccountCard key={account.id} account={account} />)
                            : <EmptyState message="Nessun conto trovato. Crea il tuo primo conto per iniziare!" />}
                    </DashboardWidgetShell>
                );

            case 'recent_transactions':
                return (
                    <DashboardWidgetShell
                        title="Ultime transazioni"
                        detailHref={route('transactions.index')}
                        detailLabel="Vedi tutte"
                        bodyClassName={widgetListBodyClass}
                    >
                        {recentTransactions.length > 0
                            ? recentTransactions.map((transaction) => <TransactionRow key={transaction.id} transaction={transaction} />)
                            : <EmptyState message="Nessuna transazione registrata. Aggiungi la tua prima transazione!" />}
                    </DashboardWidgetShell>
                );

            case 'active_budgets':
                return isModuleEnabled('budgets') ? (
                    <DashboardWidgetShell
                        title="Budget attivi"
                        detailHref={route('budgets.index')}
                        detailLabel="Vedi tutti"
                        bodyClassName={widgetListBodyClass}
                    >
                        {activeBudgets.length > 0 ? (
                            <div className="space-y-2">
                                {activeBudgets.map((budget) => <BudgetCard key={budget.id} budget={budget} />)}
                            </div>
                        ) : (
                            <div className={widgetEmptyClass}>
                                <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun budget attivo</p>
                                <Link href={route('budgets.create')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400">
                                    Crea il tuo primo budget →
                                </Link>
                            </div>
                        )}
                    </DashboardWidgetShell>
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
                    <DashboardWidgetShell
                        title="Debiti e crediti"
                        detailHref={route('debts-credits.index')}
                        detailLabel="Vedi tutti"
                        bodyClassName={widgetListBodyClass}
                    >
                        {(debtsCreditsSummary.total_debts > 0 || debtsCreditsSummary.total_credits > 0) ? (
                            <IndexKpiStrip columns={2} className="mb-3">
                                <div className="rounded-lg bg-red-50 p-3 text-center dark:bg-red-900/20">
                                    <p className="text-xs text-red-600 dark:text-red-400">Debiti</p>
                                    <p className={clsx('text-lg font-bold text-red-500', moneyTabular)}>{formatCurrency(debtsCreditsSummary.total_debts)}</p>
                                </div>
                                <div className="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-900/20">
                                    <p className="text-xs text-emerald-600 dark:text-emerald-400">Crediti</p>
                                    <p className={clsx('text-lg font-bold text-emerald-500', moneyTabular)}>{formatCurrency(debtsCreditsSummary.total_credits)}</p>
                                </div>
                            </IndexKpiStrip>
                        ) : null}
                        {openDebtsCredits.length > 0 ? (
                            <div>{openDebtsCredits.map((item) => <DebtCreditRow key={item.id} item={item} />)}</div>
                        ) : (
                            <div className={widgetEmptyClass}>
                                <p className="mb-3 text-gray-500 dark:text-gray-400">Nessun debito o credito aperto</p>
                                <Link href={route('debts-credits.create')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400">
                                    Aggiungi il primo →
                                </Link>
                            </div>
                        )}
                    </DashboardWidgetShell>
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
                const actionItems = [
                    { href: route('transactions.create'), icon: <PlusIcon size={24} />, label: 'Nuova transazione' },
                    { href: route('transactions.quick-session'), icon: '⚡', label: 'Sessione rapida' },
                    { href: route('transfers.create'), icon: '🔄', label: 'Trasferimento' },
                    { href: route('accounts.create'), icon: '🏦', label: 'Nuovo conto' },
                    { href: route('categories.create'), icon: '🏷️', label: 'Nuova categoria' },
                ] as const;

                return (
                    <DashboardWidgetShell title="Azioni rapide">
                        <div className="flex gap-2 overflow-x-auto pb-1 [-webkit-overflow-scrolling:touch] lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0 xl:grid-cols-5 lg:gap-3">
                            {actionItems.map((action) => (
                                <QuickActionCard
                                    key={action.href}
                                    href={action.href}
                                    icon={action.icon}
                                    label={action.label}
                                    compact={compact}
                                    className="min-w-[6.75rem] shrink-0 snap-start p-2.5 lg:min-w-0 lg:shrink lg:snap-align-none lg:p-3 xl:p-4"
                                />
                            ))}
                        </div>
                    </DashboardWidgetShell>
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
                    <DashboardWidgetShell
                        title="Asset allocation"
                        subtitle="Conti + investimenti inclusi nel calcolo allocazione"
                        detailHref={route('asset-allocation.index')}
                    >
                        {total_value > 0 ? (
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500 dark:text-gray-400">Base allocazione</span>
                                    <span className={clsx('text-lg font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {formatCurrency(total_value)}
                                    </span>
                                </div>
                                <div
                                    className="flex h-2 overflow-hidden rounded-full gap-px"
                                    role="img"
                                    aria-label={allocation.map((a) => `${a.label} ${a.percentage.toFixed(1)}%`).join(', ')}
                                >
                                    {allocation.map((a) => (
                                        <div
                                            key={a.asset_class}
                                            style={{ width: `${a.percentage}%`, backgroundColor: a.color }}
                                            title={`${a.label}: ${a.percentage.toFixed(1)}%`}
                                        />
                                    ))}
                                </div>
                                <div className="flex flex-wrap gap-x-3 gap-y-1">
                                    {allocation.map((a) => (
                                        <span key={a.asset_class} className="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                            <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: a.color }} />
                                            {a.label} {a.percentage.toFixed(0)}%
                                        </span>
                                    ))}
                                </div>
                                <div className="flex items-center justify-between border-t border-gray-100 pt-1 dark:border-gray-700">
                                    <span className="text-xs text-gray-500 dark:text-gray-400">Indice di rischio</span>
                                    <span className={`text-sm font-semibold ${riskColor}`}>
                                        {risk_label} · {risk_index.toFixed(1)}/7
                                    </span>
                                </div>
                            </div>
                        ) : (
                            <div className="py-4 text-center">
                                <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                    Nessuna posizione trovata
                                </p>
                                <Link href={route('investments.create')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400">
                                    Aggiungi il primo →
                                </Link>
                            </div>
                        )}
                    </DashboardWidgetShell>
                ) : (
                    <LockedModuleCard
                        moduleId="investments"
                        showHideButton
                        onHideModule={() => hideLockedWidget(['asset_allocation'])}
                        isHiding={isSaving}
                    />
                );
            }

            case 'net_worth': {
                const netWorthSubtitle = netWorthMode === 'cash'
                    ? 'Saldo conti (tutti i conti attivi)'
                    : 'Saldo conti + investimenti collegati al ledger (costo di carico)';
                const netWorthModeOptions = [
                    { value: 'portfolio' as const, label: 'Patrimonio' },
                    { value: 'cash' as const, label: 'Solo liquidità' },
                ];

                return (
                    <DashboardWidgetShell
                        bodyClassName={widgetListBodyClass}
                        header={(
                            <div className={contentPanelHeaderClass}>
                                <div className="space-y-2.5 sm:hidden">
                                    <div className="flex items-center justify-between gap-2">
                                        <h3 className="truncate text-base font-semibold text-gray-900 dark:text-white">
                                            Patrimonio nel tempo
                                        </h3>
                                        <Link
                                            href={route('analytics.net-worth')}
                                            className="shrink-0 text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                                        >
                                            Dettagli →
                                        </Link>
                                    </div>
                                    <DashboardWidgetSegmentedControl
                                        value={netWorthMode}
                                        options={netWorthModeOptions}
                                        onChange={setNetWorthMode}
                                        ariaLabel="Vista patrimonio"
                                    />
                                    <p className="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {netWorthSubtitle}
                                    </p>
                                </div>
                                <div className={`${widgetHeaderClass} hidden border-0 p-0 sm:flex`}>
                                    <div className="min-w-0">
                                        <h3 className="font-semibold text-gray-900 dark:text-white">Patrimonio nel tempo</h3>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{netWorthSubtitle}</p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <DashboardWidgetSegmentedControl
                                            value={netWorthMode}
                                            options={netWorthModeOptions}
                                            onChange={setNetWorthMode}
                                            ariaLabel="Vista patrimonio"
                                            className="w-auto sm:inline-grid sm:grid-cols-2"
                                        />
                                        <Link
                                            href={route('analytics.net-worth')}
                                            className="whitespace-nowrap text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                                        >
                                            Dettagli →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        )}
                    >
                        <NetWorthChart
                            embedded
                            data={netWorthMode === 'cash' ? netWorthCashData : netWorthData}
                            title={netWorthMode === 'cash' ? 'Liquidità nel tempo' : 'Patrimonio nel tempo'}
                            subtitle={netWorthSubtitle}
                        />
                    </DashboardWidgetShell>
                );
            }

            case 'cash_flow':
                return (
                    <DashboardWidgetShell
                        title="Panoramica cashflow"
                        subtitle="Entrate, uscite e risparmio mensile"
                        detailHref={route('analytics.cash-flow')}
                    >
                        <CashFlowChart embedded data={cashFlowData} />
                    </DashboardWidgetShell>
                );

            case 'expense_treemap':
                return (
                    <DashboardWidgetShell
                        title="Spese per categoria"
                        subtitle="Mese corrente"
                        detailHref={route('analytics.expenses-by-category', { month: new Date().toISOString().slice(0, 7) })}
                    >
                        <ExpenseTreemap
                            embedded
                            data={expenseCategories}
                            month={new Date().toISOString().slice(0, 7)}
                        />
                    </DashboardWidgetShell>
                );

            case 'financial_goals':
                return isModuleEnabled('financial_goals') ? (
                    <DashboardWidgetShell
                        title="Obiettivi finanziari"
                        detailHref={route('financial-goals.index')}
                        detailLabel="Vedi tutti"
                        bodyClassName={widgetListBodyClass}
                    >
                        {financialGoals.length > 0 ? (
                            <div className="space-y-2">
                                {financialGoals.map((goal) => (
                                    <Link
                                        key={goal.id}
                                        href={route('financial-goals.show', goal.id)}
                                        className="block rounded-lg bg-gray-50 p-3 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700"
                                    >
                                        <div className="mb-2 flex items-center justify-between gap-2">
                                            <div className="flex min-w-0 items-center gap-2">
                                                <span aria-hidden>{goal.icon || '🎯'}</span>
                                                <span className="truncate font-medium text-gray-900 dark:text-white">{goal.name}</span>
                                            </div>
                                            <span className="shrink-0 text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                                {goal.percentage}%
                                            </span>
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
                                <Link href={route('financial-goals.create')} className="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400">
                                    Crea il tuo primo obiettivo →
                                </Link>
                            </div>
                        )}
                    </DashboardWidgetShell>
                ) : (
                    <LockedModuleCard
                        moduleId="financial_goals"
                        showHideButton
                        onHideModule={() => hideLockedWidget(['financial_goals'])}
                        isHiding={isSaving}
                    />
                );

            case 'expense_distribution':
                return <ExpenseDistributionWidget embedded data={expenseDistributionData} />;

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
                                            className={clsx(
                                                widget.id === 'quick_actions' && !isEditing && 'hidden lg:flex',
                                            )}
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
