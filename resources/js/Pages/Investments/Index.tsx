import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InvestmentHubNav from '@/Components/InvestmentHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions, IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EmptyState from '@/Components/EmptyState';
import IndexEntityCard, {
    IndexEntityCardFooterLink,
} from '@/Components/Index/IndexEntityCard';
import EyeIcon from '@/Components/Icons/EyeIcon';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { moneyListCardGrid, moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency, formatDate, formatNumber } from '@/utils/format';
import CardBox from '@/Components/CardBox';
import TradingViewMarketOverview from '@/Components/TradingViewMarketOverview';
import TradingViewEconomicCalendar from '@/Components/TradingViewEconomicCalendar';
import { useMemo, useState, type ReactNode } from 'react';

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
    const profitValue = investment.is_sold
        ? investment.net_profit
        : investment.unrealized_profit;
    const currencyCode = investment.asset.currency.code;

    return (
        <IndexEntityCard
            href={route('investments.show', investment.id)}
            icon={<span>{investment.asset.type_icon}</span>}
            iconClassName="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-xl dark:bg-gray-700"
            title={investment.asset.name}
            subtitle={
                <>
                    {investment.asset.symbol ? (
                        <span>{investment.asset.symbol} · </span>
                    ) : null}
                    {formatNumber(investment.quantity)} unità · {formatDate(investment.buy_date)}
                    {investment.is_private && ' · 🔒'}
                </>
            }
            aside={
                investment.is_sold ? (
                    <ProfitBadge profit={investment.net_profit} percentage={investment.profit_percentage} />
                ) : profitValue !== null ? (
                    <p
                        className={clsx(
                            'text-right text-sm font-bold sm:text-base',
                            moneyTabular,
                            profitValue >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
                        )}
                    >
                        {profitValue >= 0 ? '+' : ''}
                        {formatCurrency(profitValue, currencyCode)}
                    </p>
                ) : undefined
            }
            amount={formatCurrency(investment.total_buy_value, currencyCode)}
            extra={
                investment.is_sold ? (
                    <div className="border-t border-gray-100 pt-2.5 dark:border-gray-700">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Vendita</p>
                        <p
                            className={clsx(
                                'text-sm font-medium',
                                moneyTabular,
                                investment.net_profit !== null && investment.net_profit >= 0
                                    ? 'text-green-600'
                                    : 'text-red-600',
                            )}
                        >
                            {formatCurrency(investment.total_sell_value!, currencyCode)}
                        </p>
                    </div>
                ) : profitValue === null ? (
                    <p className="border-t border-gray-100 pt-2.5 text-sm font-medium text-blue-600 dark:border-gray-700">
                        Prezzi di mercato non disponibili
                    </p>
                ) : undefined
            }
        />
    );
}

interface PacGroup {
    pacId: number;
    pacStatus: 'active' | 'paused';
    assetName: string;
    assetSymbol: string | null;
    movements: Investment[];
}

function PacMetricCell({
    label,
    value,
    sub,
    valueClassName,
    align = 'left',
}: {
    label: ReactNode;
    value: ReactNode;
    sub?: ReactNode;
    valueClassName?: string;
    align?: 'left' | 'right';
}) {
    return (
        <div className={clsx('min-w-0', align === 'right' && 'text-right')}>
            <p className="text-[11px] text-gray-500 sm:text-xs dark:text-gray-400">{label}</p>
            <p className={clsx('text-sm font-semibold text-gray-900 dark:text-white', moneyTabular, valueClassName)}>
                {value}
            </p>
            {sub ? <p className="mt-0.5 text-[10px] text-gray-400 sm:text-xs dark:text-gray-500">{sub}</p> : null}
        </div>
    );
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
    const openCount = openMovements.length;
    const closedCount = group.movements.length - openCount;
    const openMovementsWithPrice = group.movements.filter(
        (movement) => !movement.is_sold && movement.unrealized_profit !== null,
    );
    const unrealizedProfit = openMovementsWithPrice.length > 0
        ? openMovementsWithPrice.reduce((sum, movement) => sum + (movement.unrealized_profit ?? 0), 0)
        : null;
    const showHref = route('investment-pacs.show', group.pacId);

    return (
        <IndexEntityCard
            icon="🔁"
            title={group.assetName}
            subtitle={
                <>
                    {group.assetSymbol ? <span>{group.assetSymbol} · </span> : null}
                    {group.movements.length} mov. · {openCount} aperti · {closedCount} chiusi
                </>
            }
            aside={
                <span
                    className={clsx(
                        'rounded-full px-2 py-0.5 text-[11px] font-medium sm:text-xs',
                        group.pacStatus === 'active'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                    )}
                >
                    {group.pacStatus === 'active' ? 'Attivo' : 'In pausa'}
                </span>
            }
            amount={formatCurrency(totalInvested, currencyCode)}
            amountDetail={
                <span className="text-[11px] text-gray-400 sm:text-xs">
                    Comm. {formatCurrency(totalFees, currencyCode)}
                </span>
            }
            extra={
                <>
                    <div className="grid grid-cols-2 gap-x-3 gap-y-2 border-t border-gray-100 pt-2.5 dark:border-gray-700">
                        <PacMetricCell
                            label={
                                <>
                                    <span className="sm:hidden">PM acq.</span>
                                    <span className="hidden sm:inline">Prezzo medio acquisto</span>
                                </>
                            }
                            value={averageBuyPrice !== null ? formatCurrency(averageBuyPrice, currencyCode) : '—'}
                        />
                        <PacMetricCell
                            label="Non realizzato"
                            align="right"
                            value={
                                unrealizedProfit !== null
                                    ? `${unrealizedProfit >= 0 ? '+' : ''}${formatCurrency(unrealizedProfit, currencyCode)}`
                                    : 'Prezzi n/d'
                            }
                            valueClassName={
                                unrealizedProfit === null
                                    ? 'text-gray-400 dark:text-gray-500'
                                    : unrealizedProfit >= 0
                                      ? 'text-emerald-600 dark:text-emerald-400'
                                      : 'text-red-600 dark:text-red-400'
                            }
                        />
                        <PacMetricCell
                            label="Realizzato"
                            value={`${realizedProfit >= 0 ? '+' : ''}${formatCurrency(realizedProfit, currencyCode)}`}
                            valueClassName={
                                realizedProfit >= 0
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-red-600 dark:text-red-400'
                            }
                        />
                    </div>

                    {expanded && (
                        <div className="mt-2.5 space-y-1.5 border-t border-gray-100 pt-2.5 dark:border-gray-700">
                            {group.movements.map((movement) => (
                                <div
                                    key={movement.id}
                                    className="rounded-lg border border-gray-200 p-2.5 dark:border-gray-700"
                                >
                                    <p className="text-xs font-medium text-gray-900 sm:text-sm dark:text-white">
                                        {formatDate(movement.buy_date)} ·{' '}
                                        {formatCurrency(movement.total_buy_value, movement.asset.currency.code)}
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-gray-500 sm:text-xs dark:text-gray-400">
                                        {formatCurrency(movement.buy_price, movement.asset.currency.code)} ×{' '}
                                        {formatNumber(movement.quantity, 4)}
                                        {movement.is_sold && movement.sell_date
                                            ? ` · Venduto ${formatDate(movement.sell_date)}`
                                            : ' · Aperto'}
                                    </p>
                                    {!movement.is_sold && (
                                        <p
                                            className={clsx(
                                                'mt-0.5 text-[11px] font-medium sm:text-xs',
                                                movement.unrealized_profit === null
                                                    ? 'text-gray-400 dark:text-gray-500'
                                                    : movement.unrealized_profit >= 0
                                                      ? 'text-emerald-600 dark:text-emerald-400'
                                                      : 'text-red-600 dark:text-red-400',
                                            )}
                                        >
                                            {movement.unrealized_profit !== null
                                                ? `Non realizzato: ${movement.unrealized_profit >= 0 ? '+' : ''}${formatCurrency(movement.unrealized_profit, movement.asset.currency.code)}`
                                                : 'Prezzi n/d'}
                                        </p>
                                    )}
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        <Link
                                            href={route('investments.show', movement.id)}
                                            className="rounded-md border border-gray-300 px-2 py-1 text-[11px] font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            Dettaglio
                                        </Link>
                                        <Link
                                            href={route('investments.edit', movement.id)}
                                            className="rounded-md border border-blue-200 px-2 py-1 text-[11px] font-medium text-blue-700 dark:border-blue-900 dark:text-blue-300"
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
                                            className="rounded-md border border-red-200 px-2 py-1 text-[11px] font-medium text-red-700 dark:border-red-900 dark:text-red-300"
                                        >
                                            Elimina
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </>
            }
            footer={
                <>
                    <button
                        type="button"
                        onClick={() => setExpanded((value) => !value)}
                        className="mr-auto rounded-lg px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        {expanded ? 'Nascondi movimenti' : `Mostra ${group.movements.length} movimenti`}
                    </button>
                    <IndexEntityCardFooterLink href={showHref} title="Dettaglio PAC">
                        <EyeIcon size={16} />
                    </IndexEntityCardFooterLink>
                </>
            }
            footerClassName="flex items-center justify-end gap-0.5"
        />
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
                    assetName: pac.asset_name ?? investment.asset.name,
                    assetSymbol: pac.asset_symbol ?? investment.asset.symbol,
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
                    <InvestmentHubNav active="positions" />
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
                            <h3 className="mb-2 text-sm font-semibold text-gray-900 sm:mb-3 sm:text-base dark:text-white">
                                🟢 Posizioni aperte ({standaloneOpenInvestments.length})
                            </h3>
                            <div className={clsx(moneyListCardGrid, 'gap-2 sm:gap-3')}>
                                {standaloneOpenInvestments.map((investment) => (
                                    <InvestmentCard key={investment.id} investment={investment} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* PAC raggruppati */}
                    {pacGroups.length > 0 && (
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-gray-900 sm:mb-3 sm:text-base dark:text-white">
                                🔁 Piani PAC ({pacGroups.length})
                            </h3>
                            <div className="flex flex-col gap-2 sm:gap-3">
                                {pacGroups.map((group) => (
                                    <PacGroupCard key={group.pacId} group={group} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Investimenti Chiusi */}
                    {standaloneClosedInvestments.length > 0 && (
                        <div>
                            <h3 className="mb-2 text-sm font-semibold text-gray-500 sm:mb-3 sm:text-base dark:text-gray-400">
                                ⚪ Posizioni chiuse ({standaloneClosedInvestments.length})
                            </h3>
                            <div className={clsx(moneyListCardGrid, 'gap-2 sm:gap-3')}>
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
