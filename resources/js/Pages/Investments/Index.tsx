import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions, IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { moneyCardGrid3, moneyKpiGrid2, moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency, formatDate, formatNumber } from '@/utils/format';
import CardBox from '@/Components/CardBox';
import TradingViewMarketOverview from '@/Components/TradingViewMarketOverview';
import TradingViewEconomicCalendar from '@/Components/TradingViewEconomicCalendar';
import { useMemo, useState } from 'react';

interface Currency {
    code: string;
    symbol: string;
}

interface Asset {
    id: number;
    name: string;
    symbol: string | null;
    type: string;
    type_label: string;
    type_icon: string;
    currency: Currency;
}

interface Account {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface Investment {
    id: number;
    asset: Asset;
    account: Account | null;
    quantity: number;
    buy_price: number;
    buy_date: string;
    sell_price: number | null;
    sell_date: string | null;
    fees: number | null;
    total_buy_value: number;
    total_sell_value: number | null;
    net_profit: number | null;
    profit_percentage: number | null;
    current_price: number | null;
    current_value: number | null;
    unrealized_profit: number | null;
    is_sold: boolean;
    is_private: boolean;
    notes: string | null;
    investment_pac: {
        id: number;
        status: 'active' | 'paused';
        asset_name: string | null;
        asset_symbol: string | null;
    } | null;
    user: User;
}

interface Stats {
    total_investments: number;
    open_count: number;
    closed_count: number;
    total_invested: number;
    total_realized_profit: number;
    total_fees: number;
    total_unrealized_profit: number;
    has_price_data: boolean;
}

interface AssetTypes {
    [key: string]: string;
}

interface AssetTypeIcons {
    [key: string]: string;
}

interface IndexProps {
    investments: Investment[];
    openInvestments: Investment[];
    closedInvestments: Investment[];
    stats: Stats;
    assetTypes: AssetTypes;
    assetTypeIcons: AssetTypeIcons;
    valuationNote: string;
}

// function formatCurrency(amount: number, currency: string = 'EUR'): string {
//     return new Intl.NumberFormat('it-IT', {
//         style: 'currency',
//         currency: currency,
//     }).format(amount);
// }


function ProfitBadge({ profit, percentage }: { profit: number | null; percentage: number | null }) {
    if (profit === null) return null;

    const isPositive = profit >= 0;

    return (
        <div className={clsx(
            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
            isPositive
                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        )}>
            {isPositive ? '📈' : '📉'}
            {isPositive ? '+' : ''}{percentage?.toFixed(2)}%
        </div>
    );
}

function InvestmentCard({ investment }: { investment: Investment }) {
    const profitLabel = investment.is_sold
        ? 'Realizzato'
        : investment.unrealized_profit !== null
          ? 'Non realizzato'
          : null;
    const profitValue = investment.is_sold
        ? investment.net_profit
        : investment.unrealized_profit;

    return (
        <CardBox className="overflow-hidden p-3 shadow-sm transition-shadow hover:shadow-md sm:p-4">
            <Link
                href={route('investments.show', investment.id)}
                className="flex min-h-[5.5rem] flex-col justify-between"
            >
                <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xl dark:bg-gray-700">
                        {investment.asset.type_icon}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-start justify-between gap-2">
                            <h3 className="truncate font-semibold text-gray-900 dark:text-white">
                                {investment.asset.name}
                                {investment.asset.symbol && (
                                    <span className="ml-1 text-sm font-normal text-gray-500 dark:text-gray-400">
                                        ({investment.asset.symbol})
                                    </span>
                                )}
                            </h3>
                            {investment.is_sold && (
                                <ProfitBadge profit={investment.net_profit} percentage={investment.profit_percentage} />
                            )}
                        </div>
                        <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                            {formatNumber(investment.quantity)} unità · {formatDate(investment.buy_date)}
                            {investment.is_private && ' · 🔒'}
                        </p>
                    </div>
                </div>

                <div className="mt-3 grid grid-cols-2 gap-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                    <div className="min-w-0">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Investito</p>
                        <p className={clsx('truncate text-sm font-medium text-gray-900 dark:text-white', moneyTabular)}>
                            {formatCurrency(investment.total_buy_value, investment.asset.currency.code)}
                        </p>
                    </div>
                    <div className="min-w-0 text-right">
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {investment.is_sold ? 'Vendita' : profitLabel ?? 'Stato'}
                        </p>
                        {investment.is_sold ? (
                            <p className={clsx(
                                'truncate text-sm font-medium',
                                moneyTabular,
                                investment.net_profit !== null && investment.net_profit >= 0
                                    ? 'text-green-600'
                                    : 'text-red-600',
                            )}>
                                {formatCurrency(investment.total_sell_value!, investment.asset.currency.code)}
                            </p>
                        ) : profitValue !== null ? (
                            <p className={clsx(
                                'truncate text-sm font-medium',
                                moneyTabular,
                                profitValue >= 0 ? 'text-emerald-600' : 'text-red-600',
                            )}>
                                {profitValue >= 0 ? '+' : ''}
                                {formatCurrency(profitValue, investment.asset.currency.code)}
                            </p>
                        ) : (
                            <p className="text-sm font-medium text-blue-600">Prezzi n/d</p>
                        )}
                    </div>
                </div>
            </Link>
        </CardBox>
    );
}

interface PacGroup {
    pacId: number;
    pacStatus: 'active' | 'paused';
    label: string;
    movements: Investment[];
}

function PacGroupCard({ group }: { group: PacGroup }) {
    const [expanded, setExpanded] = useState(false);
    const currencyCode = group.movements[0]?.asset.currency.code ?? 'EUR';
    const totalInvested = group.movements.reduce(
        (sum, movement) => sum + movement.total_buy_value + (movement.fees ?? 0),
        0,
    );
    const totalFees = group.movements.reduce((sum, movement) => sum + (movement.fees ?? 0), 0);
    const openMovements = group.movements.filter((movement) => !movement.is_sold);
    const openQuantity = openMovements.reduce((sum, movement) => sum + movement.quantity, 0);
    const openTotalCost = openMovements.reduce(
        (sum, movement) => sum + movement.total_buy_value + (movement.fees ?? 0),
        0,
    );
    const averageBuyPrice = openQuantity > 0 ? openTotalCost / openQuantity : null;
    const realizedProfit = group.movements
        .filter((movement) => movement.is_sold)
        .reduce((sum, movement) => sum + (movement.net_profit ?? 0), 0);
    const openCount = group.movements.filter((movement) => !movement.is_sold).length;
    const closedCount = group.movements.length - openCount;
    const openMovementsWithPrice = group.movements.filter(
        (movement) => !movement.is_sold && movement.unrealized_profit !== null,
    );
    const unrealizedProfit = openMovementsWithPrice.length > 0
        ? openMovementsWithPrice.reduce((sum, movement) => sum + (movement.unrealized_profit ?? 0), 0)
        : null;

    return (
        <CardBox className="overflow-hidden p-4 shadow-sm">
            <button
                type="button"
                onClick={() => setExpanded((value) => !value)}
                className="flex w-full items-start justify-between gap-3 text-left"
            >
                <div>
                    <div className="flex items-center gap-2">
                        <h3 className="font-semibold text-gray-900 dark:text-white">{group.label}</h3>
                        <span className={clsx(
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            group.pacStatus === 'active'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                        )}
                        >
                            {group.pacStatus === 'active' ? 'PAC attivo' : 'PAC in pausa'}
                        </span>
                    </div>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {group.movements.length} movimenti · {openCount} aperti · {closedCount} chiusi
                    </p>
                </div>
                <span className="text-sm text-gray-500 dark:text-gray-400">{expanded ? 'Nascondi dettagli' : 'Mostra dettagli'}</span>
            </button>

            <div className={clsx(moneyKpiGrid2, 'mt-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5')}>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Totale investito</p>
                    <p className={clsx('font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                        {formatCurrency(totalInvested, currencyCode)}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                        Comm. {formatCurrency(totalFees, currencyCode)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Prezzo medio acquisto</p>
                    {averageBuyPrice !== null ? (
                        <p className={clsx('font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                            {formatCurrency(averageBuyPrice, currencyCode)}
                        </p>
                    ) : (
                        <p className={clsx('font-semibold text-gray-400 dark:text-gray-500', moneyTabular)}>—</p>
                    )}
                    <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Posizioni aperte</p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Profitto non realizzato</p>
                    {unrealizedProfit !== null ? (
                        <p className={clsx(
                            'font-semibold',
                            moneyTabular,
                            unrealizedProfit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                        )}>
                            {unrealizedProfit >= 0 ? '+' : ''}{formatCurrency(unrealizedProfit, currencyCode)}
                        </p>
                    ) : (
                        <p className={clsx('font-semibold text-gray-400 dark:text-gray-500', moneyTabular)}>
                            Prezzi n/d
                        </p>
                    )}
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Profitto realizzato</p>
                    <p className={clsx(
                        'font-semibold',
                        moneyTabular,
                        realizedProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                    )}
                    >
                        {realizedProfit >= 0 ? '+' : ''}{formatCurrency(realizedProfit, currencyCode)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Andamento</p>
                    <Link
                        href={route('investment-pacs.show', group.pacId)}
                        className="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        Dettaglio PAC →
                    </Link>
                </div>
            </div>

            {expanded && (
                <div className="mt-4 space-y-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                    {group.movements.map((movement) => (
                        <div key={movement.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                        Acquisto {formatDate(movement.buy_date)} · {formatCurrency(movement.total_buy_value, movement.asset.currency.code)}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Prezzo unitario {formatCurrency(movement.buy_price, movement.asset.currency.code)}
                                        {' · '}Quantità {formatNumber(movement.quantity, 4)}
                                        {movement.is_sold && movement.sell_date
                                            ? ` · Venduto ${formatDate(movement.sell_date)}`
                                            : ' · Aperto'}
                                        {movement.fees ? ` · Comm. ${formatCurrency(movement.fees, movement.asset.currency.code)}` : ''}
                                    </p>
                                    {!movement.is_sold && (
                                        <p className={clsx(
                                            'mt-1 text-xs font-medium',
                                            movement.unrealized_profit === null
                                                ? 'text-gray-400 dark:text-gray-500'
                                                : movement.unrealized_profit >= 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-red-600 dark:text-red-400',
                                        )}>
                                            {movement.unrealized_profit !== null
                                                ? `Non realizzato: ${movement.unrealized_profit >= 0 ? '+' : ''}${formatCurrency(movement.unrealized_profit, movement.asset.currency.code)}`
                                                : 'Prezzi n/d'}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Link
                                        href={route('investments.show', movement.id)}
                                        className="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Dettaglio
                                    </Link>
                                    <Link
                                        href={route('investments.edit', movement.id)}
                                        className="rounded-md border border-blue-200 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-900/20"
                                    >
                                        Modifica
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.confirm('Eliminare questo movimento investimento?')) {
                                                router.delete(route('investments.destroy', movement.id));
                                            }
                                        }}
                                        className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-900/20"
                                    >
                                        Elimina
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </CardBox>
    );
}

export default function Index({
    investments,
    openInvestments,
    closedInvestments,
    stats,
    assetTypes,
    assetTypeIcons,
    valuationNote,
}: IndexProps) {
    const pacGroups = useMemo<PacGroup[]>(() => {
        const grouped = new Map<number, PacGroup>();

        investments
            .filter((investment) => investment.investment_pac !== null)
            .forEach((investment) => {
                const pac = investment.investment_pac!;
                const existing = grouped.get(pac.id);
                if (existing) {
                    existing.movements.push(investment);
                    return;
                }

                grouped.set(pac.id, {
                    pacId: pac.id,
                    pacStatus: pac.status,
                    label: `PAC ${pac.asset_name ?? investment.asset.name}${pac.asset_symbol ? ` (${pac.asset_symbol})` : ''}`,
                    movements: [investment],
                });
            });

        return Array.from(grouped.values()).sort((a, b) => {
            const aLatest = a.movements[0]?.buy_date ?? '';
            const bLatest = b.movements[0]?.buy_date ?? '';
            return bLatest.localeCompare(aLatest);
        });
    }, [investments]);

    const standaloneOpenInvestments = useMemo(
        () => openInvestments.filter((investment) => investment.investment_pac === null),
        [openInvestments],
    );
    const standaloneClosedInvestments = useMemo(
        () => closedInvestments.filter((investment) => investment.investment_pac === null),
        [closedInvestments],
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Investimenti"
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('investment-pacs.index')} variant="secondary">
                                PAC
                            </LinkButton>
                            <LinkButton href={route('investments.import')}>📥 Importa CSV</LinkButton>
                            <LinkButton href={route('investments.create')} icon={<PlusIcon />}>
                                Nuovo Investimento
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Investimenti" />

            <PageContent maxWidth="7xl">
                    <IndexIntroSection
                        label="Portafoglio investimenti"
                        icon={<span className="text-sm leading-none">📊</span>}
                        description="Monitora posizioni aperte e chiuse con metriche di rendimento e costi."
                        extra={
                            <p className="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{valuationNote}</p>
                        }
                    />

                    <IndexPageMobileToolbar>
                        <LinkButton
                            href={route('investment-pacs.index')}
                            variant="secondary"
                            size="sm"
                        >
                            PAC
                        </LinkButton>
                        <LinkButton
                            href={route('investments.import')}
                            variant="secondary"
                            size="sm"
                        >
                            📥 Importa CSV
                        </LinkButton>
                    </IndexPageMobileToolbar>

                    <IndexKpiStrip>
                        <IndexKpiCell
                            label="Posizioni Aperte"
                            value={stats.open_count}
                            valueClassName="text-blue-600"
                        />
                        <IndexKpiCell
                            label="Totale Investito"
                            value={formatCurrency(stats.total_invested)}
                            detail={`Commissioni: ${formatCurrency(stats.total_fees || 0)}`}
                        />
                        <IndexKpiCell
                            label="Profitto Realizzato"
                            value={`${stats.total_realized_profit >= 0 ? '+' : ''}${formatCurrency(stats.total_realized_profit)}`}
                            valueClassName={stats.total_realized_profit >= 0 ? 'text-green-600' : 'text-red-600'}
                        />
                        <IndexKpiCell
                            label="Profitto Non Realizzato"
                            value={
                                stats.has_price_data
                                    ? `${stats.total_unrealized_profit >= 0 ? '+' : ''}${formatCurrency(stats.total_unrealized_profit)}`
                                    : 'Prezzi n/d'
                            }
                            valueClassName={
                                stats.has_price_data
                                    ? stats.total_unrealized_profit >= 0
                                        ? 'text-emerald-600'
                                        : 'text-red-600'
                                    : 'text-base text-gray-400 dark:text-gray-500 sm:text-base'
                            }
                        />
                    </IndexKpiStrip>


                    {/* Investimenti Aperti */}
                    {standaloneOpenInvestments.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                🟢 Posizioni Aperte ({standaloneOpenInvestments.length})
                            </h3>
                            <div className={moneyCardGrid3}>
                                {standaloneOpenInvestments.map((investment) => (
                                    <InvestmentCard key={investment.id} investment={investment} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* PAC raggruppati */}
                    {pacGroups.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                🔁 Piani PAC ({pacGroups.length})
                            </h3>
                            <div className="space-y-3">
                                {pacGroups.map((group) => (
                                    <PacGroupCard key={group.pacId} group={group} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Investimenti Chiusi */}
                    {standaloneClosedInvestments.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-500 dark:text-gray-400">
                                ⚪ Posizioni Chiuse ({standaloneClosedInvestments.length})
                            </h3>
                            <div className={moneyCardGrid3}>
                                {standaloneClosedInvestments.map((investment) => (
                                    <InvestmentCard key={investment.id} investment={investment} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Empty State */}
                    {investments.length === 0 && (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="📊"
                                title="Nessun investimento registrato"
                                description="Inizia a tracciare i tuoi investimenti in azioni, ETF, crypto e altro. Prima crea gli asset, poi registra gli acquisti."
                            >
                                <div className="flex gap-3">
                                    <Link
                                        href={route('investment-assets.create')}
                                        className="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2.5 text-slate-600 hover:bg-slate-50 transition-colors"
                                    >
                                        💼 Crea Asset
                                    </Link>
                                    <LinkButton
                                        href={route('investments.create')}
                                        icon={<PlusIcon />}
                                    >
                                        Nuovo Investimento
                                    </LinkButton>
                                </div>
                            </EmptyState>
                        </CardBox>
                    )}

                    {/* Widget Mercati */}
                    <div className="grid gap-6 lg:grid-cols-2">
                        <TradingViewMarketOverview />
                        <TradingViewEconomicCalendar />
                    </div>

            </PageContent>
        </AuthenticatedLayout>
    );
}
