import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import { MiniAllocationBar } from '@/Pages/AssetAllocation/Index';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';
import type { ReactNode } from 'react';
import { formatCurrency, formatDate } from '@/utils/format';
import { moneyKpiGrid2, moneyKpiGrid4, moneyTabular } from '@/utils/moneyGridClasses';

interface AllocationEntry {
    asset_class: string;
    label: string;
    color: string;
    value: number;
    percentage: number;
}

interface AccountRow {
    id: number;
    name: string;
    type: string;
    type_label: string;
    balance: number;
    currency_code: string;
    portfolio_percentage: number;
}

interface Position {
    id: number;
    name: string;
    symbol: string | null;
    value: number;
    buy_date: string | null;
    portfolio_percentage: number;
    account: { id: number; name: string } | null;
    currency: { code: string; symbol: string };
}

interface Props {
    totalValue: number;
    liquidValue: number;
    investedValue: number;
    riskIndex: number;
    riskLabel: string;
    allocation: AllocationEntry[];
    accounts: AccountRow[];
    positions: Position[];
}

function KpiHint({ children }: { children: ReactNode }) {
    return (
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{children}</p>
    );
}

export default function PatrimonioIndex({
    totalValue,
    liquidValue,
    investedValue,
    riskIndex,
    riskLabel,
    allocation,
    accounts,
    positions,
}: Props) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Patrimonio" backLink={route('dashboard')} />}>
            <Head title="Patrimonio" />
            <PageContent maxWidth="7xl">
                <div className={moneyKpiGrid4}>
                    <CardBox className="p-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Patrimonio totale</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                            {formatCurrency(totalValue)}
                        </p>
                        <KpiHint>Liquidità sui conti + investimenti registrati (costo di carico).</KpiHint>
                    </CardBox>
                    <CardBox className="p-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Liquidità</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-cyan-600 dark:text-cyan-400', moneyTabular)}>
                            {formatCurrency(liquidValue)}
                        </p>
                        <KpiHint>Somma saldi dei conti attivi.</KpiHint>
                    </CardBox>
                    <CardBox className="p-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Investimenti</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400', moneyTabular)}>
                            {formatCurrency(investedValue)}
                        </p>
                        <KpiHint>Costo di carico delle posizioni aperte, commissioni incluse.</KpiHint>
                    </CardBox>
                    <CardBox className="p-4">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Rischio portafoglio</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                            {riskIndex.toFixed(1)}/7
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{riskLabel}</p>
                    </CardBox>
                </div>

                <CardBox className="p-4 sm:p-5">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-white">Allocazione per classe</h2>
                    <div className="mt-3">
                        <MiniAllocationBar allocation={allocation} />
                    </div>
                    <div className={clsx(moneyKpiGrid2, 'mt-4 sm:grid-cols-3')}>
                        {allocation.map((entry) => (
                            <div key={entry.asset_class} className="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                                <div className="flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: entry.color }} />
                                    <span className="text-sm font-medium text-gray-900 dark:text-white">{entry.label}</span>
                                </div>
                                <p className={clsx('mt-1 text-sm font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                    {formatCurrency(entry.value)}
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">{entry.percentage.toFixed(1)}%</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4">
                        <Link href={route('asset-allocation.index')} className="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Dettaglio allocazione asset →
                        </Link>
                    </div>
                </CardBox>

                <CardBox className="overflow-hidden">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h2 className="font-medium text-gray-900 dark:text-white">Conti ({accounts.length})</h2>
                    </div>
                    {accounts.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Nessun conto con saldo positivo.</p>
                    ) : (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {accounts.map((account) => (
                                <div key={account.id} className="flex items-center justify-between px-4 py-3">
                                    <div>
                                        <p className="font-medium text-gray-900 dark:text-white">{account.name}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{account.type_label}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className={clsx('font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                            {formatCurrency(account.balance, account.currency_code)}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{account.portfolio_percentage.toFixed(1)}%</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardBox>

                <CardBox className="overflow-hidden">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h2 className="font-medium text-gray-900 dark:text-white">Posizioni investimento ({positions.length})</h2>
                    </div>
                    {positions.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Nessuna posizione aperta.</p>
                    ) : (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {positions.map((position) => (
                                <div key={position.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                    <div className="min-w-0">
                                        <Link href={route('investments.show', position.id)} className="font-medium text-gray-900 hover:text-emerald-600 dark:text-white dark:hover:text-emerald-400">
                                            {position.name}
                                            {position.symbol ? ` (${position.symbol})` : ''}
                                        </Link>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {position.account ? position.account.name : 'Senza conto'}
                                            {position.buy_date ? ` · ${formatDate(position.buy_date)}` : ''}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className={clsx('font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                            {formatCurrency(position.value, position.currency.code)}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{position.portfolio_percentage.toFixed(1)}%</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    <div className="border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        <Link href={route('investments.index')} className="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                            Vai agli investimenti →
                        </Link>
                    </div>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
