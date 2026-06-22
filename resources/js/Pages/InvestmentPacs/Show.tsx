import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import LinkButton from '@/Components/LinkButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { formatCurrency, formatDate } from '@/utils/format';
import clsx from 'clsx';
import { FormEventHandler, useMemo, useState } from 'react';

interface Pac {
    id: number;
    amount: number;
    adjust_for_inflation: boolean;
    inflation_rate_annual: number | null;
    currency_code: string;
    frequency: string;
    start_date: string;
    end_date: string | null;
    last_executed_at: string | null;
    next_execution_date?: string | null;
    status: 'active' | 'paused';
    notes: string | null;
    fees: number | null;
    asset: { id: number; name: string; symbol: string; isin: string | null; currency_code: string };
    account: { id: number; name: string; currency_code: string } | null;
}

interface PacInvestment {
    id: number;
    buy_date: string;
    buy_price: number;
    quantity: number;
    total_buy_value: number;
    sell_date: string | null;
    sell_price: number | null;
    total_sell_value: number | null;
    net_profit: number | null;
    is_sold: boolean;
    fees: number | null;
    current_price: number | null;
    current_value: number | null;
    unrealized_profit: number | null;
}

interface ShowProps {
    pac: Pac;
    investments: PacInvestment[];
    stats: {
        executions_count: number;
        open_count: number;
        closed_count: number;
        invested_total: number;
        average_buy_price: number | null;
        realized_total: number;
        unrealized_total: number | null;
        current_price: number | null;
    };
}

function SellFromPacModal({
    investment,
    onClose,
}: {
    investment: PacInvestment;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        sell_price: '',
        sell_date: new Date().toISOString().split('T')[0],
        fees: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('investments.sell', investment.id), {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    Registra vendita movimento
                </h3>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <InputLabel htmlFor="sell_price" value="Prezzo di vendita" />
                        <TextInput
                            id="sell_price"
                            type="number"
                            min="0"
                            step="0.00000001"
                            className="mt-1 block w-full"
                            value={data.sell_price}
                            onChange={(e) => setData('sell_price', e.target.value)}
                            required
                            autoFocus
                        />
                        <InputError message={errors.sell_price} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="sell_date" value="Data vendita" />
                        <TextInput
                            id="sell_date"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.sell_date}
                            onChange={(e) => setData('sell_date', e.target.value)}
                            required
                        />
                        <InputError message={errors.sell_date} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="fees" value="Commissioni vendita (opz.)" />
                        <TextInput
                            id="fees"
                            type="number"
                            min="0"
                            step="0.01"
                            className="mt-1 block w-full"
                            value={data.fees}
                            onChange={(e) => setData('fees', e.target.value)}
                        />
                        <InputError message={errors.fees} className="mt-1" />
                    </div>
                    <div className="flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Annulla
                        </button>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Salvataggio...' : 'Conferma vendita'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function InvestmentPacShow({ pac, investments, stats }: ShowProps) {
    const [sellTarget, setSellTarget] = useState<PacInvestment | null>(null);

    const realizedLabel = stats.realized_total >= 0 ? 'Plusvalenza realizzata' : 'Minusvalenza realizzata';
    const openInvestments = useMemo(() => investments.filter((investment) => !investment.is_sold), [investments]);

    return (
        <AuthenticatedLayout
            header={<PageHeader title={`PAC ${pac.asset.name}`} backLink={route('investment-pacs.index')} />}
        >
            <Head title={`PAC ${pac.asset.name}`} />
            <PageContent >
                <CardBox className="p-4 sm:p-5">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    {pac.asset.name} ({pac.asset.isin ?? 'ISIN n/d'})
                                </h2>
                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${pac.status === 'active'
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                }`}
                                >
                                    {pac.status === 'active' ? 'Attivo' : 'In pausa'}
                                </span>
                            </div>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Importo periodico: {formatCurrency(pac.amount, pac.currency_code)}
                                {pac.fees !== null ? ` · Commissioni ${formatCurrency(pac.fees, pac.currency_code)}/acquisto` : ''}
                                {pac.last_executed_at ? ` · Ultimo acquisto ${formatDate(pac.last_executed_at)}` : ''}
                                {pac.next_execution_date ? ` · Prossimo acquisto ${formatDate(pac.next_execution_date)}` : ''}
                            </p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Dal {formatDate(pac.start_date)}{pac.end_date ? ` al ${formatDate(pac.end_date)}` : ''}
                                {pac.account ? ` · Conto ${pac.account.name}` : ''}
                                {pac.adjust_for_inflation && pac.inflation_rate_annual !== null
                                    ? ` · Inflazione +${pac.inflation_rate_annual.toFixed(1)}%`
                                    : ''}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <LinkButton href={route('investment-pacs.edit', pac.id)}>Modifica PAC</LinkButton>
                            <LinkButton href={route('investment-pacs.index')} variant="secondary">Torna alla lista</LinkButton>
                        </div>
                    </div>
                </CardBox>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Acquisti generati</p>
                        <p className="text-lg font-semibold text-gray-900 dark:text-white">{stats.executions_count}</p>
                    </CardBox>
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Posizioni aperte</p>
                        <p className="text-lg font-semibold text-gray-900 dark:text-white">{stats.open_count}</p>
                    </CardBox>
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Posizioni chiuse</p>
                        <p className="text-lg font-semibold text-gray-900 dark:text-white">{stats.closed_count}</p>
                    </CardBox>
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Totale investito</p>
                        <p className="text-lg font-semibold text-gray-900 dark:text-white">{formatCurrency(stats.invested_total, pac.currency_code)}</p>
                    </CardBox>
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Prezzo medio acquisto</p>
                        {stats.average_buy_price !== null ? (
                            <p className="text-lg font-semibold text-gray-900 dark:text-white">
                                {formatCurrency(stats.average_buy_price, pac.asset.currency_code)}
                            </p>
                        ) : (
                            <p className="text-lg font-semibold text-gray-400 dark:text-gray-500">—</p>
                        )}
                        <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Posizioni aperte</p>
                    </CardBox>
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">Profitto non realizzato</p>
                        {stats.unrealized_total !== null ? (
                            <p className={clsx(
                                'text-lg font-semibold',
                                stats.unrealized_total >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
                            )}>
                                {stats.unrealized_total >= 0 ? '+' : ''}
                                {formatCurrency(stats.unrealized_total, pac.currency_code)}
                            </p>
                        ) : (
                            <p className="text-lg font-semibold text-gray-400 dark:text-gray-500">Prezzi n/d</p>
                        )}
                        {stats.current_price !== null && (
                            <p className="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                Prezzo corrente: {formatCurrency(stats.current_price, pac.asset.currency_code)}
                            </p>
                        )}
                    </CardBox>
                    <CardBox className="p-3">
                        <p className="text-xs text-gray-500 dark:text-gray-400">{realizedLabel}</p>
                        <p className={clsx(
                            'text-lg font-semibold',
                            stats.realized_total >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
                        )}
                        >
                            {formatCurrency(stats.realized_total, pac.currency_code)}
                        </p>
                    </CardBox>
                </div>

                <CardBox className="overflow-hidden">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h3 className="font-medium text-gray-900 dark:text-white">Movimenti generati dal PAC</h3>
                    </div>
                    {investments.length === 0 ? (
                        <div className="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
                            Nessun movimento generato ancora. Dalla lista PAC puoi usare "Esegui ora" per creare il primo acquisto.
                        </div>
                    ) : (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {investments.map((investment) => (
                                <div key={investment.id} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900 dark:text-white">
                                            Acquisto del {formatDate(investment.buy_date)}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            Prezzo unitario {formatCurrency(investment.buy_price, pac.asset.currency_code)}
                                            {' · '}Quantità {investment.quantity}
                                            {' · '}Costo {formatCurrency(investment.total_buy_value + (investment.fees ?? 0), pac.currency_code)}
                                            {investment.is_sold && investment.sell_date ? ` · venduto il ${formatDate(investment.sell_date)}` : ' · aperto'}
                                        </p>
                                        {investment.is_sold && investment.net_profit !== null && (
                                            <p className={clsx(
                                                'text-xs font-medium',
                                                investment.net_profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
                                            )}
                                            >
                                                {investment.net_profit >= 0 ? 'Plusvalenza' : 'Minusvalenza'}: {formatCurrency(investment.net_profit, pac.currency_code)}
                                            </p>
                                        )}
                                        {!investment.is_sold && investment.unrealized_profit !== null && (
                                            <p className={clsx(
                                                'text-xs font-medium',
                                                investment.unrealized_profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
                                            )}>
                                                Non realizzato: {investment.unrealized_profit >= 0 ? '+' : ''}{formatCurrency(investment.unrealized_profit, pac.currency_code)}
                                                {' '}@ {formatCurrency(investment.current_price!, pac.asset.currency_code)}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {!investment.is_sold && (
                                            <button
                                                type="button"
                                                onClick={() => setSellTarget(investment)}
                                                className="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                                            >
                                                Registra vendita
                                            </button>
                                        )}
                                        <Link
                                            href={route('investments.show', investment.id)}
                                            className="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Apri dettaglio
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardBox>

                {openInvestments.length > 0 && (
                    <CardBox className="p-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Hai {openInvestments.length} acquisti aperti da questo PAC. Puoi registrarne la vendita qui sopra o dal dettaglio investimento.
                        </p>
                    </CardBox>
                )}
            </PageContent>

            {sellTarget && <SellFromPacModal investment={sellTarget} onClose={() => setSellTarget(null)} />}
        </AuthenticatedLayout>
    );
}
