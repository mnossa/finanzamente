import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

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

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatNumber(value: number, decimals: number = 8): string {
    return new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    }).format(value);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

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
        <Link
            href={route('investments.show', investment.id)}
            className="block overflow-hidden rounded-xl bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
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
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Investimenti
                    </h2>
                    <div className="flex gap-2">
                        <Link
                            href={route('investment-assets.index')}
                            className="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            💼 Gestisci Asset
                        </Link>
                        <Link
                            href={route('investments.create')}
                            className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <span className="mr-2">➕</span>
                            Nuovo Investimento
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Investimenti" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Statistiche */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Posizioni Aperte
                            </p>
                            <p className="mt-1 text-3xl font-bold text-blue-600">
                                {stats.open_count}
                            </p>
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Totale Investito
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {formatCurrency(stats.total_invested)}
                            </p>
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
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
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Commissioni Pagate
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {formatCurrency(stats.total_fees || 0)}
                            </p>
                        </div>
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
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="mb-4 text-6xl">📊</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessun investimento registrato
                                </h3>
                                <p className="mb-6 max-w-md text-gray-500 dark:text-gray-400">
                                    Inizia a tracciare i tuoi investimenti in azioni, ETF, crypto e altro.
                                    Prima crea gli asset, poi registra gli acquisti.
                                </p>
                                <div className="flex gap-3">
                                    <Link
                                        href={route('investment-assets.create')}
                                        className="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        💼 Crea Asset
                                    </Link>
                                    <Link
                                        href={route('investments.create')}
                                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
                                    >
                                        <span className="mr-2">➕</span>
                                        Nuovo Investimento
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
