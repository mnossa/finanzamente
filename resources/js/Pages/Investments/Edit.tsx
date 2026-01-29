import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler, useMemo } from 'react';
import PageHeader from '@/Components/PageHeader';

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
    type: string;
}

interface Investment {
    id: number;
    account_id: number | null;
    asset_id: number;
    quantity: number;
    buy_price: number;
    buy_date: string;
    sell_price: number | null;
    sell_date: string | null;
    fees: number | null;
    notes: string | null;
    is_private: boolean;
}

interface AssetTypes {
    [key: string]: string;
}

interface EditProps {
    investment: Investment;
    accounts: Account[];
    assets: Asset[];
    assetTypes: AssetTypes;
}

export default function Edit({ investment, accounts, assets, assetTypes }: EditProps) {
    const { data, setData, put, processing, errors } = useForm({
        asset_id: investment.asset_id.toString(),
        account_id: investment.account_id?.toString() || '',
        quantity: investment.quantity.toString(),
        buy_price: investment.buy_price.toString(),
        buy_date: investment.buy_date,
        sell_price: investment.sell_price?.toString() || '',
        sell_date: investment.sell_date || '',
        fees: investment.fees?.toString() || '',
        notes: investment.notes || '',
        is_private: investment.is_private,
    });

    const selectedAsset = useMemo(() => {
        return assets.find(a => a.id === Number(data.asset_id));
    }, [data.asset_id, assets]);

    const totalBuyValue = useMemo(() => {
        const qty = parseFloat(data.quantity) || 0;
        const price = parseFloat(data.buy_price) || 0;
        return qty * price;
    }, [data.quantity, data.buy_price]);

    const totalSellValue = useMemo(() => {
        const qty = parseFloat(data.quantity) || 0;
        const price = parseFloat(data.sell_price) || 0;
        return price > 0 ? qty * price : null;
    }, [data.quantity, data.sell_price]);

    const profit = useMemo(() => {
        if (totalSellValue === null) return null;
        const fees = parseFloat(data.fees) || 0;
        return totalSellValue - totalBuyValue - fees;
    }, [totalBuyValue, totalSellValue, data.fees]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('investments.update', investment.id));
    };

    // Raggruppa asset per tipo
    const groupedAssets = useMemo(() => {
        return assets.reduce((acc, asset) => {
            if (!acc[asset.type]) {
                acc[asset.type] = [];
            }
            acc[asset.type].push(asset);
            return acc;
        }, {} as Record<string, Asset[]>);
    }, [assets]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Modifica Investimento`}
                    backLink={route('investments.index')}
                />
            }
        >
            <Head title="Modifica Investimento" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit}>
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="p-6">
                                {/* Asset */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="asset_id" value="Asset *" />
                                    <select
                                        id="asset_id"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.asset_id}
                                        onChange={(e) => setData('asset_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Seleziona un asset...</option>
                                        {Object.entries(groupedAssets).map(([type, typeAssets]) => (
                                            <optgroup key={type} label={assetTypes[type] || type}>
                                                {typeAssets.map((asset) => (
                                                    <option key={asset.id} value={asset.id}>
                                                        {asset.type_icon} {asset.name}
                                                        {asset.symbol && ` (${asset.symbol})`}
                                                        {` - ${asset.currency.code}`}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        ))}
                                    </select>
                                    <InputError message={errors.asset_id} className="mt-2" />
                                </div>

                                {/* Sezione Acquisto */}
                                <div className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                        📥 Dati Acquisto
                                    </h3>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="quantity" value="Quantità *" />
                                            <TextInput
                                                id="quantity"
                                                type="number"
                                                step="0.00000001"
                                                min="0"
                                                className="mt-2 w-full"
                                                value={data.quantity}
                                                onChange={(e) => setData('quantity', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.quantity} className="mt-2" />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="buy_price" value="Prezzo Acquisto *" />
                                            <div className="relative mt-2">
                                                <TextInput
                                                    id="buy_price"
                                                    type="number"
                                                    step="0.00000001"
                                                    min="0"
                                                    className="w-full pr-12"
                                                    value={data.buy_price}
                                                    onChange={(e) => setData('buy_price', e.target.value)}
                                                    required
                                                />
                                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                    {selectedAsset?.currency.symbol || '€'}
                                                </span>
                                            </div>
                                            <InputError message={errors.buy_price} className="mt-2" />
                                        </div>
                                    </div>

                                    <div className="mt-4">
                                        <InputLabel htmlFor="buy_date" value="Data Acquisto *" />
                                        <TextInput
                                            id="buy_date"
                                            type="date"
                                            className="mt-2 w-full"
                                            value={data.buy_date}
                                            onChange={(e) => setData('buy_date', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.buy_date} className="mt-2" />
                                    </div>

                                    {totalBuyValue > 0 && (
                                        <div className="mt-4 rounded bg-gray-50 p-3 dark:bg-gray-700/50">
                                            <p className="text-sm text-gray-500 dark:text-gray-400">Totale Acquisto</p>
                                            <p className="text-lg font-bold text-gray-900 dark:text-white">
                                                {new Intl.NumberFormat('it-IT', {
                                                    style: 'currency',
                                                    currency: selectedAsset?.currency.code || 'EUR',
                                                }).format(totalBuyValue)}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Sezione Vendita */}
                                <div className="mb-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                        📤 Dati Vendita (opzionale)
                                    </h3>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel htmlFor="sell_price" value="Prezzo Vendita" />
                                            <div className="relative mt-2">
                                                <TextInput
                                                    id="sell_price"
                                                    type="number"
                                                    step="0.00000001"
                                                    min="0"
                                                    className="w-full pr-12"
                                                    value={data.sell_price}
                                                    onChange={(e) => setData('sell_price', e.target.value)}
                                                />
                                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                    {selectedAsset?.currency.symbol || '€'}
                                                </span>
                                            </div>
                                            <InputError message={errors.sell_price} className="mt-2" />
                                        </div>

                                        <div>
                                            <InputLabel htmlFor="sell_date" value="Data Vendita" />
                                            <TextInput
                                                id="sell_date"
                                                type="date"
                                                className="mt-2 w-full"
                                                value={data.sell_date}
                                                onChange={(e) => setData('sell_date', e.target.value)}
                                            />
                                            <InputError message={errors.sell_date} className="mt-2" />
                                        </div>
                                    </div>

                                    {profit !== null && (
                                        <div className={clsx(
                                            'mt-4 rounded p-3',
                                            profit >= 0
                                                ? 'bg-green-50 dark:bg-green-900/20'
                                                : 'bg-red-50 dark:bg-red-900/20'
                                        )}>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                {profit >= 0 ? 'Profitto Netto' : 'Perdita Netta'}
                                            </p>
                                            <p className={clsx(
                                                'text-lg font-bold',
                                                profit >= 0 ? 'text-green-600' : 'text-red-600'
                                            )}>
                                                {profit >= 0 ? '+' : ''}
                                                {new Intl.NumberFormat('it-IT', {
                                                    style: 'currency',
                                                    currency: selectedAsset?.currency.code || 'EUR',
                                                }).format(profit)}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Altri dettagli */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="account_id" value="Conto di Riferimento" />
                                    <select
                                        id="account_id"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.account_id}
                                        onChange={(e) => setData('account_id', e.target.value)}
                                    >
                                        <option value="">Nessun conto collegato</option>
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.account_id} className="mt-2" />
                                </div>

                                <div className="mb-6">
                                    <InputLabel htmlFor="fees" value="Commissioni Totali" />
                                    <div className="relative mt-2">
                                        <TextInput
                                            id="fees"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="w-full pr-12"
                                            value={data.fees}
                                            onChange={(e) => setData('fees', e.target.value)}
                                        />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                            {selectedAsset?.currency.symbol || '€'}
                                        </span>
                                    </div>
                                    <InputError message={errors.fees} className="mt-2" />
                                </div>

                                <div className="mb-6">
                                    <InputLabel htmlFor="notes" value="Note" />
                                    <textarea
                                        id="notes"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        rows={3}
                                    />
                                    <InputError message={errors.notes} className="mt-2" />
                                </div>

                                <div className="mb-6">
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            className="rounded border-gray-300 text-emerald-500 shadow-sm focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                            checked={data.is_private}
                                            onChange={(e) => setData('is_private', e.target.checked)}
                                        />
                                        <span className="text-sm text-gray-700 dark:text-gray-300">
                                            🔒 Investimento privato
                                        </span>
                                    </label>
                                </div>
                            </div>

                            {/* Footer */}
                            <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                                <Link
                                    href={route('investments.show', investment.id)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : '💾 Salva Modifiche'}
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
