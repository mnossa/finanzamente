import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler, useState } from 'react';

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
    gross_profit: number | null;
    net_profit: number | null;
    profit_percentage: number | null;
    is_sold: boolean;
    is_private: boolean;
    notes: string | null;
    created_at: string;
    user: User;
}

interface ShowProps {
    investment: Investment;
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

export default function Show({ investment }: ShowProps) {
    const [showSellModal, setShowSellModal] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const handleDelete = () => {
        router.delete(route('investments.destroy', investment.id));
    };

    const currencyCode = investment.asset.currency.code;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('investments.index')}
                        className="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                        ←
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {investment.asset.type_icon} {investment.asset.name}
                        {investment.asset.symbol && (
                            <span className="ml-2 text-base text-gray-500 dark:text-gray-400">
                                ({investment.asset.symbol})
                            </span>
                        )}
                    </h2>
                </div>
            }
        >
            <Head title={`${investment.asset.name} - Investimento`} />

            <div className="py-6">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Stato e Azioni */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
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
                    </div>

                    {/* Dettagli Acquisto/Vendita */}
                    <div className="grid gap-6 sm:grid-cols-2">
                        {/* Acquisto */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    📥 Acquisto
                                </h3>
                            </div>
                            <div className="p-6">
                                <div className="mb-4">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Valore Totale</p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                        {formatCurrency(investment.total_buy_value, currencyCode)}
                                    </p>
                                </div>
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-gray-500 dark:text-gray-400">Prezzo Unitario</p>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(investment.buy_price, currencyCode)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-500 dark:text-gray-400">Data</p>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {formatDate(investment.buy_date)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Vendita o Posizione */}
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
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
                                            <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                                {formatCurrency(investment.total_sell_value!, currencyCode)}
                                            </p>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p className="text-gray-500 dark:text-gray-400">Prezzo Unitario</p>
                                                <p className="font-medium text-gray-900 dark:text-white">
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
                                    <div className="flex flex-col items-center justify-center py-4 text-center">
                                        <div className="mb-2 text-4xl">📈</div>
                                        <p className="text-gray-500 dark:text-gray-400">
                                            Posizione ancora aperta
                                        </p>
                                        <p className="mt-1 text-sm text-gray-400 dark:text-gray-500">
                                            Registra una vendita per chiudere
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
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
                                            investment.net_profit !== null && investment.net_profit >= 0
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        )}>
                                            {investment.net_profit !== null && investment.net_profit >= 0 ? '+' : ''}
                                            {formatCurrency(investment.net_profit!, currencyCode)}
                                        </p>
                                    </div>
                                    <div className={clsx(
                                        'rounded-full px-6 py-3 text-2xl font-bold',
                                        investment.net_profit !== null && investment.net_profit >= 0
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                    )}>
                                        {investment.profit_percentage !== null && investment.profit_percentage >= 0 ? '+' : ''}
                                        {investment.profit_percentage?.toFixed(2)}%
                                    </div>
                                </div>
                                {investment.fees && investment.fees > 0 && (
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Commissioni totali: {formatCurrency(investment.fees, currencyCode)}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Info aggiuntive */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
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
                                    <span className="font-medium text-gray-900 dark:text-white">
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
                    </div>

                    {/* Azioni */}
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
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
                                        className="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20"
                                    >
                                        🗑️ Elimina
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
