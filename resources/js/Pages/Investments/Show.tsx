import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { Head, Link, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler, useState } from 'react';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';
import { moneyKpiGrid2, moneyTabular } from '@/utils/moneyGridClasses';

interface Currency {
    code: string;
    symbol: string;
}

interface Asset {
    id: number;
    name: string;
    symbol: string | null;
    isin: string | null;
    type: string;
    type_label: string;
    type_icon: string;
    currency: Currency;
    coupon_frequency: string | null;
    next_coupon_date: string | null;
    coupon_rate_percent: number | null;
}

interface Account {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface CouponRow {
    id: number;
    date: string;
    amount: number;
    description: string | null;
    account_id: number;
}

interface CouponSchedule {
    next_dates: string[];
    frequency: string | null;
    rate_percent: number | null;
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
    total_cost: number;
    gross_profit: number | null;
    net_profit: number | null;
    profit_percentage: number | null;
    current_price: number | null;
    current_value: number | null;
    unrealized_profit: number | null;
    coupons_total: number;
    capital_profit: number | null;
    total_return: number | null;
    is_sold: boolean;
    is_private: boolean;
    notes: string | null;
    created_at: string;
    user: User;
}

interface ShowProps {
    investment: Investment;
    coupons: CouponRow[];
    couponSchedule: CouponSchedule;
    accounts: Account[];
    valuationNote: string;
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
        month: 'long',
        year: 'numeric',
    });
}

function SellModal({
    investment,
    onClose,
}: {
    investment: Investment;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        sell_price: '',
        sell_date: new Date().toISOString().split('T')[0],
        fees: '',
    });

    const estimatedProfit = (() => {
        const sellPrice = parseFloat(data.sell_price) || 0;
        const fees = parseFloat(data.fees) || 0;
        if (sellPrice === 0) return null;
        const sellValue = investment.quantity * sellPrice;
        return sellValue - investment.total_buy_value - fees - (investment.fees || 0);
    })();

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('investments.sell', investment.id), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    📤 Registra Vendita
                </h3>
                <form onSubmit={submit}>
                    <div className="mb-4">
                        <InputLabel htmlFor="sell_price" value="Prezzo di Vendita *" />
                        <div className="relative mt-2">
                            <TextInput
                                id="sell_price"
                                type="number"
                                step="0.00000001"
                                min="0"
                                className="w-full pr-12"
                                value={data.sell_price}
                                onChange={(e) => setData('sell_price', e.target.value)}
                                autoFocus
                                required
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                {investment.asset.currency.symbol}
                            </span>
                        </div>
                        <InputError message={errors.sell_price} className="mt-2" />
                    </div>

                    <div className="mb-4">
                        <InputLabel htmlFor="sell_date" value="Data di Vendita *" />
                        <TextInput
                            id="sell_date"
                            type="date"
                            className="mt-2 w-full"
                            value={data.sell_date}
                            onChange={(e) => setData('sell_date', e.target.value)}
                            required
                        />
                        <InputError message={errors.sell_date} className="mt-2" />
                    </div>

                    <div className="mb-4">
                        <InputLabel htmlFor="fees" value="Commissioni Vendita" />
                        <div className="relative mt-2">
                            <TextInput
                                id="fees"
                                type="number"
                                step="0.01"
                                min="0"
                                className="w-full pr-12"
                                value={data.fees}
                                onChange={(e) => setData('fees', e.target.value)}
                                placeholder="0.00"
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                {investment.asset.currency.symbol}
                            </span>
                        </div>
                        <InputError message={errors.fees} className="mt-2" />
                    </div>

                    {estimatedProfit !== null && (
                        <div className={clsx(
                            'mb-4 rounded-lg p-3',
                            estimatedProfit >= 0
                                ? 'bg-green-50 dark:bg-green-900/20'
                                : 'bg-red-50 dark:bg-red-900/20'
                        )}>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {estimatedProfit >= 0 ? 'Profitto Stimato' : 'Perdita Stimata'}
                            </p>
                            <p className={clsx(
                                'text-xl font-bold',
                                moneyTabular,
                                estimatedProfit >= 0 ? 'text-green-600' : 'text-red-600'
                            )}>
                                {estimatedProfit >= 0 ? '+' : ''}
                                {formatCurrency(estimatedProfit, investment.asset.currency.code)}
                            </p>
                        </div>
                    )}

                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Annulla
                        </button>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Registrazione...' : '📤 Registra Vendita'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Show({ investment, coupons, couponSchedule, accounts, valuationNote }: ShowProps) {
    const [showSellModal, setShowSellModal] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const currencyCode = investment.asset.currency.code;

    const couponForm = useForm({
        amount: '',
        date: new Date().toISOString().split('T')[0],
        description: '',
        account_id: investment.account?.id?.toString() ?? (accounts[0]?.id?.toString() ?? ''),
    });

    const scheduleForm = useForm({
        coupon_frequency: investment.asset.coupon_frequency ?? '',
        next_coupon_date: investment.asset.next_coupon_date ?? '',
        coupon_rate_percent: investment.asset.coupon_rate_percent?.toString() ?? '',
    });

    const frequencyLabel = (value: string | null) => {
        switch (value) {
            case 'monthly':
                return 'Mensile';
            case 'quarterly':
                return 'Trimestrale';
            case 'semi_annual':
                return 'Semestrale';
            case 'annual':
                return 'Annuale';
            default:
                return 'Non impostata';
        }
    };

    const submitCoupon: FormEventHandler = (e) => {
        e.preventDefault();
        couponForm.post(route('investments.coupons.store', investment.id), {
            preserveScroll: true,
            onSuccess: () => couponForm.reset('amount', 'description'),
        });
    };

    const submitSchedule: FormEventHandler = (e) => {
        e.preventDefault();
        scheduleForm.put(route('investments.coupons.schedule', investment.id), {
            preserveScroll: true,
        });
    };

    const handleDeleteCoupon = (couponId: number) => {
        router.delete(route('investments.coupons.destroy', [investment.id, couponId]), {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        router.delete(route('investments.destroy', investment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${investment.asset.name} - Investimento`}
                    backLink={route('investments.index')}
                />
            }
        >
            <Head title={`${investment.asset.name} - Investimento`} />

            <PageContent maxWidth="4xl">
                    {/* Stato e Azioni */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="p-6">
                            <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-3xl dark:bg-gray-700">
                                        {investment.asset.type_icon}
                                    </div>
                                    <div>
                                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">
                                            {investment.asset.name}
                                        </h1>
                                        <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                            <span>{investment.asset.type_label}</span>
                                            <span>•</span>
                                            <span>{formatNumber(investment.quantity)} unità</span>
                                            {investment.is_private && (
                                                <>
                                                    <span>•</span>
                                                    <span>🔒 Privato</span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className={clsx(
                                    'rounded-full px-4 py-2 text-sm font-medium',
                                    investment.is_sold
                                        ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                        : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                )}>
                                    {investment.is_sold ? '⚪ Chiuso' : '🟢 Aperto'}
                                </div>
                            </div>

                            {!investment.is_sold && (
                                <div className="mt-6">
                                    <button
                                        onClick={() => setShowSellModal(true)}
                                        className="w-full rounded-lg bg-emerald-500 px-4 py-3 font-medium text-white shadow-accent transition-all hover:bg-emerald-600 active:scale-95 sm:w-auto"
                                    >
                                        📤 Registra Vendita
                                    </button>
                                </div>
                            )}
                        </div>
                    </CardBox>

                    {/* Dettagli Acquisto/Vendita */}
                    <div className={moneyKpiGrid2}>
                        {/* Acquisto */}
                        <CardBox className="overflow-hidden shadow-sm">
                            <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    📥 Acquisto
                                </h3>
                            </div>
                            <div className="p-6">
                                <div className="mb-4">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Valore Totale</p>
                                    <p className={clsx('text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {formatCurrency(investment.total_buy_value, currencyCode)}
                                    </p>
                                </div>
                                <div className={clsx(moneyKpiGrid2, 'text-sm')}>
                                    <div>
                                        <p className="text-gray-500 dark:text-gray-400">Prezzo unitario</p>
                                        <p className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                            {formatCurrency(investment.buy_price, currencyCode)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500 dark:text-gray-400">Quantità</p>
                                        <p className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                            {formatNumber(investment.quantity)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500 dark:text-gray-400">Data</p>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {formatDate(investment.buy_date)}
                                        </p>
                                    </div>
                                    {investment.fees !== null && investment.fees > 0 && (
                                        <div>
                                            <p className="text-gray-500 dark:text-gray-400">Commissioni</p>
                                            <p className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                                {formatCurrency(investment.fees, currencyCode)}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardBox>

                        {/* Vendita o Posizione */}
                        <CardBox className="overflow-hidden shadow-sm">
                            <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    {investment.is_sold ? '📤 Vendita' : '📊 Posizione'}
                                </h3>
                            </div>
                            <div className="p-6">
                                {investment.is_sold ? (
                                    <>
                                        <div className="mb-4">
                                            <p className="text-sm text-gray-500 dark:text-gray-400">Valore Totale</p>
                                            <p className={clsx('text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                                {formatCurrency(investment.total_sell_value!, currencyCode)}
                                            </p>
                                        </div>
                                        <div className={clsx(moneyKpiGrid2, 'text-sm')}>
                                            <div>
                                                <p className="text-gray-500 dark:text-gray-400">Prezzo Unitario</p>
                                                <p className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                                    {formatCurrency(investment.sell_price!, currencyCode)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-gray-500 dark:text-gray-400">Data</p>
                                                <p className="font-medium text-gray-900 dark:text-white">
                                                    {formatDate(investment.sell_date!)}
                                                </p>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        {investment.current_value !== null && investment.unrealized_profit !== null ? (
                                            <>
                                                <div className="mb-4">
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">Valore attuale</p>
                                                    <p className={clsx('text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                                        {formatCurrency(investment.current_value, currencyCode)}
                                                    </p>
                                                    {investment.current_price !== null && (
                                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                            Prezzo di mercato {formatCurrency(investment.current_price, currencyCode)}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className={clsx(moneyKpiGrid2, 'text-sm')}>
                                                    <div>
                                                        <p className="text-gray-500 dark:text-gray-400">Profitto non realizzato</p>
                                                        <p className={clsx(
                                                            'font-semibold',
                                                            moneyTabular,
                                                            investment.unrealized_profit >= 0
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : 'text-red-600 dark:text-red-400',
                                                        )}>
                                                            {investment.unrealized_profit >= 0 ? '+' : ''}
                                                            {formatCurrency(investment.unrealized_profit, currencyCode)}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p className="text-gray-500 dark:text-gray-400">Costo totale</p>
                                                        <p className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                                            {formatCurrency(investment.total_cost, currencyCode)}
                                                        </p>
                                                    </div>
                                                </div>
                                                <p className="mt-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                                    {valuationNote}
                                                </p>
                                            </>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center py-4 text-center">
                                                <div className="mb-2 text-4xl">📈</div>
                                                <p className="text-gray-500 dark:text-gray-400">
                                                    Posizione ancora aperta
                                                </p>
                                                <p className="mt-1 text-sm text-gray-400 dark:text-gray-500">
                                                    Prezzo di mercato non disponibile al momento
                                                </p>
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        </CardBox>
                    </div>

                    {/* Risultato (se venduto) */}
                    {investment.is_sold && (
                        <div className={clsx(
                            'overflow-hidden rounded-xl shadow-sm',
                            investment.net_profit !== null && investment.net_profit >= 0
                                ? 'bg-green-50 dark:bg-green-900/20'
                                : 'bg-red-50 dark:bg-red-900/20'
                        )}>
                            <div className="p-6">
                                <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            {investment.net_profit !== null && investment.net_profit >= 0
                                                ? '🎉 Profitto Netto'
                                                : '📉 Perdita Netta'}
                                        </p>
                                        <p className={clsx(
                                            'text-3xl font-bold',
                                            moneyTabular,
                                            investment.net_profit !== null && investment.net_profit >= 0
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        )}>
                                            {investment.net_profit !== null && investment.net_profit >= 0 ? '+' : ''}
                                            {formatCurrency(investment.net_profit!, currencyCode)}
                                        </p>
                                    </div>
                                    <div className={clsx(
                                        'rounded-full px-6 py-3 text-2xl font-bold tabular-nums',
                                        investment.net_profit !== null && investment.net_profit >= 0
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                    )}>
                                        {investment.profit_percentage !== null && investment.profit_percentage >= 0 ? '+' : ''}
                                        {investment.profit_percentage?.toFixed(2)}%
                                    </div>
                                </div>
                                {investment.fees && investment.fees > 0 && (
                                    <p className={clsx('mt-2 text-sm text-gray-500 dark:text-gray-400', moneyTabular)}>
                                        Commissioni totali: {formatCurrency(investment.fees, currencyCode)}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Cedole / dividendi */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Cedole e dividendi
                            </h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Registra gli stacchi collegati a questa posizione. Il ritorno totale
                                somma P/L di capitale e cedole.
                            </p>
                        </div>
                        <div className="space-y-4 p-6">
                            <div className={clsx(moneyKpiGrid2, 'gap-4')}>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Totale cedole</p>
                                    <p className={clsx('text-xl font-bold text-emerald-600 dark:text-emerald-400', moneyTabular)}>
                                        {formatCurrency(investment.coupons_total, currencyCode)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Ritorno complessivo</p>
                                    <p className={clsx(
                                        'text-xl font-bold',
                                        moneyTabular,
                                        (investment.total_return ?? 0) >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-red-600 dark:text-red-400',
                                    )}>
                                        {investment.total_return !== null
                                            ? `${investment.total_return >= 0 ? '+' : ''}${formatCurrency(investment.total_return, currencyCode)}`
                                            : '—'}
                                    </p>
                                    {investment.capital_profit !== null && (
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Capitale {investment.capital_profit >= 0 ? '+' : ''}
                                            {formatCurrency(investment.capital_profit, currencyCode)}
                                            {' + '}cedole {formatCurrency(investment.coupons_total, currencyCode)}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {investment.asset.isin && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    ISIN {investment.asset.isin}: calendario automatico non disponibile
                                    senza servizi a pagamento. Imposta frequenza e prossima data qui sotto.
                                </p>
                            )}

                            <form onSubmit={submitSchedule} className="grid gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40 sm:grid-cols-3">
                                <div>
                                    <InputLabel htmlFor="coupon_frequency" value="Frequenza" />
                                    <select
                                        id="coupon_frequency"
                                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        value={scheduleForm.data.coupon_frequency}
                                        onChange={(e) => scheduleForm.setData('coupon_frequency', e.target.value)}
                                    >
                                        <option value="">—</option>
                                        <option value="annual">Annuale</option>
                                        <option value="semi_annual">Semestrale</option>
                                        <option value="quarterly">Trimestrale</option>
                                        <option value="monthly">Mensile</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel htmlFor="next_coupon_date" value="Prossima cedola" />
                                    <TextInput
                                        id="next_coupon_date"
                                        type="date"
                                        className="mt-1 w-full"
                                        value={scheduleForm.data.next_coupon_date}
                                        onChange={(e) => scheduleForm.setData('next_coupon_date', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <InputLabel htmlFor="coupon_rate_percent" value="Tasso % (opz.)" />
                                    <TextInput
                                        id="coupon_rate_percent"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className="mt-1 w-full"
                                        value={scheduleForm.data.coupon_rate_percent}
                                        onChange={(e) => scheduleForm.setData('coupon_rate_percent', e.target.value)}
                                    />
                                </div>
                                <div className="sm:col-span-3">
                                    <PrimaryButton disabled={scheduleForm.processing}>
                                        Salva calendario
                                    </PrimaryButton>
                                    {couponSchedule.next_dates.length > 0 && (
                                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                            Prossime stime ({frequencyLabel(couponSchedule.frequency)}):{' '}
                                            {couponSchedule.next_dates.map((d) => formatDate(d)).join(' · ')}
                                        </p>
                                    )}
                                </div>
                            </form>

                            <form onSubmit={submitCoupon} className="grid gap-3 border-t border-gray-100 pt-4 dark:border-gray-700 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="coupon_amount" value="Importo stacco *" />
                                    <TextInput
                                        id="coupon_amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        className="mt-1 w-full"
                                        value={couponForm.data.amount}
                                        onChange={(e) => couponForm.setData('amount', e.target.value)}
                                        required
                                    />
                                    <InputError message={couponForm.errors.amount} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="coupon_date" value="Data *" />
                                    <TextInput
                                        id="coupon_date"
                                        type="date"
                                        className="mt-1 w-full"
                                        value={couponForm.data.date}
                                        onChange={(e) => couponForm.setData('date', e.target.value)}
                                        required
                                    />
                                    <InputError message={couponForm.errors.date} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="coupon_account" value="Conto accredito" />
                                    <select
                                        id="coupon_account"
                                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        value={couponForm.data.account_id}
                                        onChange={(e) => couponForm.setData('account_id', e.target.value)}
                                    >
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={couponForm.errors.account_id} className="mt-1" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="coupon_description" value="Nota" />
                                    <TextInput
                                        id="coupon_description"
                                        className="mt-1 w-full"
                                        value={couponForm.data.description}
                                        onChange={(e) => couponForm.setData('description', e.target.value)}
                                        placeholder="Es. cedola maggio"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <PrimaryButton disabled={couponForm.processing || accounts.length === 0}>
                                        Registra cedola
                                    </PrimaryButton>
                                </div>
                            </form>

                            {coupons.length === 0 ? (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Nessuna cedola registrata.
                                </p>
                            ) : (
                                <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {coupons.map((coupon) => (
                                        <li key={coupon.id} className="flex items-center justify-between gap-3 py-3">
                                            <div>
                                                <p className="font-medium text-gray-900 dark:text-white">
                                                    {formatDate(coupon.date)}
                                                </p>
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    {coupon.description || 'Cedola'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className={clsx('font-semibold text-emerald-600 dark:text-emerald-400', moneyTabular)}>
                                                    {formatCurrency(coupon.amount, currencyCode)}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDeleteCoupon(coupon.id)}
                                                    className="text-sm text-red-600 hover:underline"
                                                >
                                                    Elimina
                                                </button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </CardBox>

                    {/* Info aggiuntive */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Dettagli
                            </h3>
                        </div>
                        <div className="divide-y divide-gray-200 dark:divide-gray-700">
                            {investment.account && (
                                <div className="flex items-center justify-between px-6 py-4">
                                    <span className="text-gray-600 dark:text-gray-400">Conto</span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {investment.account.name}
                                    </span>
                                </div>
                            )}
                            {investment.fees && investment.fees > 0 && (
                                <div className="flex items-center justify-between px-6 py-4">
                                    <span className="text-gray-600 dark:text-gray-400">Commissioni</span>
                                    <span className={clsx('font-medium text-gray-900 dark:text-white', moneyTabular)}>
                                        {formatCurrency(investment.fees, currencyCode)}
                                    </span>
                                </div>
                            )}
                            <div className="flex items-center justify-between px-6 py-4">
                                <span className="text-gray-600 dark:text-gray-400">Creato da</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {investment.user.name}
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-6 py-4">
                                <span className="text-gray-600 dark:text-gray-400">Data Registrazione</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {investment.created_at}
                                </span>
                            </div>
                            {investment.notes && (
                                <div className="px-6 py-4">
                                    <p className="text-sm text-gray-600 dark:text-gray-400">Note</p>
                                    <p className="mt-1 text-gray-900 dark:text-white">{investment.notes}</p>
                                </div>
                            )}
                        </div>
                    </CardBox>

                    {/* Azioni */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">Azioni</h3>
                        </div>
                        <div className="divide-y divide-gray-200 dark:divide-gray-700">
                            <Link
                                href={route('investments.edit', investment.id)}
                                className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-white">Modifica Investimento</p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Modifica quantità, prezzi o altri dettagli
                                    </p>
                                </div>
                                <span className="text-gray-400">→</span>
                            </Link>
                            <div className="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p className="font-medium text-red-600">Elimina Investimento</p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Questa azione non può essere annullata
                                    </p>
                                </div>
                                {showDeleteConfirm ? (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => setShowDeleteConfirm(false)}
                                            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                                        >
                                            Annulla
                                        </button>
                                        <button
                                            onClick={handleDelete}
                                            className="rounded-lg bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700"
                                        >
                                            Conferma
                                        </button>
                                    </div>
                                ) : (
                                    <button
                                        onClick={() => setShowDeleteConfirm(true)}
                                        className="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20"
                                    >
                                        <TrashIcon size={18} /> Elimina
                                    </button>
                                )}
                            </div>
                        </div>
                    </CardBox>
            </PageContent>

            {/* Sell Modal */}
            {showSellModal && (
                <SellModal
                    investment={investment}
                    onClose={() => setShowSellModal(false)}
                />
            )}
        </AuthenticatedLayout>
    );
}
