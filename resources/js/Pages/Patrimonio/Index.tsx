import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import PatrimonioHubNav from '@/Components/PatrimonioHubNav';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import CardBox from '@/Components/CardBox';
import { MiniAllocationBar } from '@/Pages/AssetAllocation/Index';
import { Head, Link } from '@inertiajs/react';
import clsx from 'clsx';
import { useState, type ReactNode } from 'react';
import { formatCurrency, formatDate } from '@/utils/format';
import { moneyKpiGrid2, moneyTabular } from '@/utils/moneyGridClasses';

interface AllocationInstrument {
    key?: string;
    name: string;
    symbol: string | null;
    value: number;
    detail: string | null;
}

interface AllocationEntry {
    asset_class: string;
    label: string;
    color: string;
    value: number;
    percentage: number;
    instruments?: AllocationInstrument[];
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

interface PositionCurrency {
    code: string;
    symbol: string;
}

interface StandalonePositionGroup {
    kind: 'standalone';
    key: string;
    id: number;
    name: string;
    symbol: string | null;
    value: number;
    buy_date: string | null;
    portfolio_percentage: number;
    account: { id: number; name: string } | null;
    currency: PositionCurrency;
}

interface PacMovement {
    id: number;
    value: number;
    portfolio_percentage: number;
    buy_date: string | null;
    account: { id: number; name: string } | null;
    currency: PositionCurrency;
}

interface PacPositionGroup {
    kind: 'pac';
    key: string;
    pac_id: number;
    name: string;
    symbol: string | null;
    value: number;
    portfolio_percentage: number;
    movement_count: number;
    buy_date_from: string | null;
    buy_date_to: string | null;
    account: { id: number; name: string } | null;
    currency: PositionCurrency;
    pac_status: 'active' | 'paused';
    movements: PacMovement[];
}

type PositionGroup = StandalonePositionGroup | PacPositionGroup;

interface Props {
    totalValue: number;
    liquidValue: number;
    investedValue: number;
    investedUnlinkedValue: number;
    riskIndex: number;
    riskLabel: string;
    allocation: AllocationEntry[];
    accounts: AccountRow[];
    positionGroups: PositionGroup[];
    positionMovementCount: number;
}

function KpiHint({ children }: { children: ReactNode }) {
    return (
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{children}</p>
    );
}

function PositionValueCell({
    value,
    percentage,
    currencyCode,
}: {
    value: number;
    percentage: number;
    currencyCode: string;
}) {
    return (
        <div className="text-right">
            <p className={clsx('font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                {formatCurrency(value, currencyCode)}
            </p>
            <p className="text-xs text-gray-500 dark:text-gray-400">{percentage.toFixed(1)}%</p>
        </div>
    );
}

function PacPositionRow({ group }: { group: PacPositionGroup }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="border-b border-gray-100 last:border-0 dark:border-gray-700">
            <div className="flex items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('investment-pacs.show', group.pac_id)}
                            className="font-medium text-gray-900 hover:text-emerald-600 dark:text-white dark:hover:text-emerald-400"
                        >
                            {group.name}
                            {group.symbol ? ` (${group.symbol})` : ''}
                        </Link>
                        <span className={clsx(
                            'rounded-full px-2 py-0.5 text-[10px] font-medium',
                            group.pac_status === 'active'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        )}>
                            PAC
                        </span>
                    </div>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {group.movement_count} movimenti
                        {group.account ? ` · ${group.account.name}` : ''}
                        {group.buy_date_from && group.buy_date_to
                            ? ` · ${formatDate(group.buy_date_from)} – ${formatDate(group.buy_date_to)}`
                            : ''}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    <PositionValueCell
                        value={group.value}
                        percentage={group.portfolio_percentage}
                        currencyCode={group.currency.code}
                    />
                    <button
                        type="button"
                        onClick={() => setExpanded((value) => !value)}
                        className="rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        aria-expanded={expanded}
                    >
                        {expanded ? 'Nascondi' : 'Dettaglio'}
                    </button>
                </div>
            </div>
            {expanded && (
                <div className="max-h-48 overflow-y-auto border-t border-gray-100 bg-gray-50/70 dark:border-gray-700 dark:bg-gray-800/40">
                    {group.movements.map((movement) => (
                        <div key={movement.id} className="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                            <div className="min-w-0">
                                <Link
                                    href={route('investments.show', movement.id)}
                                    className="text-gray-800 hover:text-emerald-600 dark:text-gray-200 dark:hover:text-emerald-400"
                                >
                                    Acquisto {movement.buy_date ? formatDate(movement.buy_date) : '—'}
                                </Link>
                                {movement.account && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">{movement.account.name}</p>
                                )}
                            </div>
                            <PositionValueCell
                                value={movement.value}
                                percentage={movement.portfolio_percentage}
                                currencyCode={movement.currency.code}
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function PatrimonioIndex({
    totalValue,
    liquidValue,
    investedValue,
    investedUnlinkedValue,
    riskIndex,
    riskLabel,
    allocation,
    accounts,
    positionGroups,
    positionMovementCount,
}: Props) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Patrimonio" backLink={route('dashboard')} />}>
            <Head title="Patrimonio" />
            <PageContent maxWidth="7xl">
                <PatrimonioHubNav active="patrimonio" />
                <IndexKpiStrip>
                    <IndexKpiCell
                        label="Patrimonio netto"
                        value={formatCurrency(totalValue)}
                        detail={<KpiHint>Saldo conti + investimenti collegati al ledger (costo di carico).</KpiHint>}
                    />
                    <IndexKpiCell
                        label="Saldo conti"
                        value={formatCurrency(liquidValue)}
                        valueClassName="text-cyan-600 dark:text-cyan-400"
                        detail={<KpiHint>Conti liquidi attivi (negativi inclusi; esclusi deposito e previdenza).</KpiHint>}
                    />
                    <IndexKpiCell
                        label="Investimenti"
                        value={formatCurrency(investedValue)}
                        valueClassName="text-emerald-600 dark:text-emerald-400"
                        detail={<KpiHint>Costo di carico delle posizioni aperte, commissioni incluse.</KpiHint>}
                    />
                    {investedUnlinkedValue > 0 ? (
                        <IndexKpiCell
                            label="Investito non collegato"
                            value={formatCurrency(investedUnlinkedValue)}
                            valueClassName="text-amber-600 dark:text-amber-400"
                            detail={<KpiHint>PAC o posizioni senza movimento sul conto: non inclusi nel patrimonio netto.</KpiHint>}
                        />
                    ) : null}
                    <IndexKpiCell
                        label="Rischio portafoglio"
                        value={`${riskIndex.toFixed(1)}/7`}
                        detail={riskLabel}
                    />
                </IndexKpiStrip>

                <CardBox className="sm:p-5">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-white">Allocazione per classe</h2>
                    <p className="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        Gli ETF non sono tutti azionari: la classe deriva da nome e simbolo (es. obbligazionario, commodity).
                        Puoi correggerla in Asset → Modifica.
                    </p>
                    <div className="mt-3">
                        <MiniAllocationBar allocation={allocation} />
                    </div>
                    <div className="mt-4 space-y-3">
                        {allocation.map((entry) => (
                            <div key={entry.asset_class} className="rounded-lg border border-gray-100 p-3 dark:border-gray-700">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: entry.color }} />
                                        <span className="text-sm font-medium text-gray-900 dark:text-white">{entry.label}</span>
                                    </div>
                                    <div className="text-right">
                                        <p className={clsx('text-sm font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                                            {formatCurrency(entry.value)}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{entry.percentage.toFixed(1)}%</p>
                                    </div>
                                </div>
                                {entry.instruments && entry.instruments.length > 0 && (
                                    <ul className="mt-2 space-y-1 border-t border-gray-100 pt-2 dark:border-gray-700">
                                        {entry.instruments.map((instrument) => (
                                            <li
                                                key={instrument.key ?? `${entry.asset_class}-${instrument.name}-${instrument.value}`}
                                                className="flex items-start justify-between gap-2 text-xs"
                                            >
                                                <span className="min-w-0 text-gray-600 dark:text-gray-300">
                                                    <span className="font-medium text-gray-800 dark:text-gray-200">
                                                        {instrument.name}
                                                        {instrument.symbol ? ` (${instrument.symbol})` : ''}
                                                    </span>
                                                    {instrument.detail && (
                                                        <span className="block text-gray-500 dark:text-gray-400">{instrument.detail}</span>
                                                    )}
                                                </span>
                                                <span className={clsx('shrink-0 font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                                    {formatCurrency(instrument.value)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
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
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-medium text-gray-900 dark:text-white">Conti ({accounts.length})</h2>
                            <Link href={route('accounts.index')} className="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">
                                Gestisci →
                            </Link>
                        </div>
                    </div>
                    {accounts.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Nessun conto attivo.</p>
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
                        <h2 className="font-medium text-gray-900 dark:text-white">
                            Posizioni investimento ({positionGroups.length})
                        </h2>
                        {positionMovementCount > positionGroups.length && (
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {positionMovementCount} movimenti · PAC raggruppati per piano
                            </p>
                        )}
                    </div>
                    {positionGroups.length === 0 ? (
                        <p className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">Nessuna posizione aperta.</p>
                    ) : (
                        <div>
                            {positionGroups.map((group) => (
                                group.kind === 'pac' ? (
                                    <PacPositionRow key={group.key} group={group} />
                                ) : (
                                    <div key={group.key} className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 last:border-0 dark:border-gray-700">
                                        <div className="min-w-0">
                                            <Link href={route('investments.show', group.id)} className="font-medium text-gray-900 hover:text-emerald-600 dark:text-white dark:hover:text-emerald-400">
                                                {group.name}
                                                {group.symbol ? ` (${group.symbol})` : ''}
                                            </Link>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {group.account ? group.account.name : 'Senza conto'}
                                                {group.buy_date ? ` · ${formatDate(group.buy_date)}` : ''}
                                            </p>
                                        </div>
                                        <PositionValueCell
                                            value={group.value}
                                            percentage={group.portfolio_percentage}
                                            currencyCode={group.currency.code}
                                        />
                                    </div>
                                )
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
