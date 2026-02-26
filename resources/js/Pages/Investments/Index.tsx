import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate, formatNumber } from '@/utils/format';
import CardBox from '@/Components/CardBox';

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
    is_sold: boolean;
    is_private: boolean;
    notes: string | null;
    user: User;
}

interface Stats {
    total_investments: number;
    open_count: number;
    closed_count: number;
    total_invested: number;
    total_realized_profit: number;
    total_fees: number;
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
    return (
        <CardBox className="overflow-hidden p-4 shadow-sm transition-shadow hover:shadow-md">
            <Link
                href={route('investments.show', investment.id)}
                className="block"
            >
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-xl dark:bg-gray-700">
                        {investment.asset.type_icon}
                    </div>
                    <div>
                        <h3 className="font-semibold text-gray-900 dark:text-white">
                            {investment.asset.name}
                            {investment.asset.symbol && (
                                <span className="ml-1 text-sm text-gray-500 dark:text-gray-400">
                                    ({investment.asset.symbol})
                                </span>
                            )}
                        </h3>
                        <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                            <span>{formatNumber(investment.quantity)} unità</span>
                            {investment.is_private && <span>🔒</span>}
                        </div>
                    </div>
                </div>
                {investment.is_sold && (
                    <ProfitBadge profit={investment.net_profit} percentage={investment.profit_percentage} />
                )}
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Acquisto</p>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {formatCurrency(investment.total_buy_value, investment.asset.currency.code)}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        {formatDate(investment.buy_date)}
                    </p>
                </div>
                {investment.is_sold ? (
                    <div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Vendita</p>
                        <p className={clsx(
                            'font-medium',
                            investment.net_profit !== null && investment.net_profit >= 0
                                ? 'text-green-600'
                                : 'text-red-600'
                        )}>
                            {formatCurrency(investment.total_sell_value!, investment.asset.currency.code)}
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {formatDate(investment.sell_date!)}
                        </p>
                    </div>
                ) : (
                    <div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Stato</p>
                        <p className="font-medium text-blue-600">
                            🟢 Aperto
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            @ {formatCurrency(investment.buy_price, investment.asset.currency.code)}/u
                        </p>
                    </div>
                )}
            </div>
            </Link>
        </CardBox>
    );
}

export default function Index({
    investments,
    openInvestments,
    closedInvestments,
    stats,
    assetTypes,
    assetTypeIcons
}: IndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Investimenti"
                    actions={

                        <LinkButton
                            href={route('investments.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo Investimento
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Investimenti" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

                    {/* Statistiche */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Posizioni Aperte
                            </p>
                            <p className="mt-1 text-3xl font-bold text-blue-600">
                                {stats.open_count}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Totale Investito
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {formatCurrency(stats.total_invested)}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Profitto Realizzato
                            </p>
                            <p className={clsx(
                                'mt-1 text-2xl font-bold',
                                stats.total_realized_profit >= 0 ? 'text-green-600' : 'text-red-600'
                            )}>
                                {stats.total_realized_profit >= 0 ? '+' : ''}
                                {formatCurrency(stats.total_realized_profit)}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Commissioni Pagate
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {formatCurrency(stats.total_fees || 0)}
                            </p>
                        </CardBox>
                    </div>

                    {/* Investimenti Aperti */}
                    {openInvestments.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                🟢 Posizioni Aperte ({openInvestments.length})
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {openInvestments.map((investment) => (
                                    <InvestmentCard key={investment.id} investment={investment} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Investimenti Chiusi */}
                    {closedInvestments.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-500 dark:text-gray-400">
                                ⚪ Posizioni Chiuse ({closedInvestments.length})
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {closedInvestments.map((investment) => (
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
