import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import SectionBadge from '@/Components/SectionBadge';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
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

function formatMoney(amount: number, currencyCode: string): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currencyCode,
    }).format(amount);
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
        return assets.find((a) => a.id === Number(data.asset_id));
    }, [data.asset_id, assets]);

    const currencyCode = selectedAsset?.currency.code || 'EUR';
    const currencySymbol = selectedAsset?.currency.symbol || '€';

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

    const groupedAssets = useMemo(() => {
        const byType = assets.reduce((acc, asset) => {
            if (!acc[asset.type]) {
                acc[asset.type] = [];
            }
            acc[asset.type].push(asset);
            return acc;
        }, {} as Record<string, Asset[]>);

        const ordered: Record<string, Asset[]> = {};
        for (const type of Object.keys(assetTypes)) {
            if (byType[type]?.length) {
                ordered[type] = byType[type];
            }
        }
        for (const [type, typeAssets] of Object.entries(byType)) {
            if (!ordered[type]) {
                ordered[type] = typeAssets;
            }
        }

        return ordered;
    }, [assets, assetTypes]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Modifica Investimento"
                    backLink={route('investments.index')}
                />
            }
        >
            <Head title="Modifica Investimento" />

            <PageContent maxWidth="3xl">
                {/* Mobile: flat (no card). Desktop: single surface. */}
                <div className="sm:rounded-2xl sm:border sm:border-gray-200/80 sm:bg-white/95 sm:p-5 sm:shadow-sm sm:backdrop-blur-sm dark:sm:border-gray-700 dark:sm:bg-gray-800/95">
                    <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-5">
                        <header className="hidden space-y-1 sm:block">
                            <SectionBadge label="Investimenti" icon={<span className="text-sm leading-none">✏️</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Modifica investimento
                            </h2>
                        </header>

                        <div>
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

                        <section className="space-y-4">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                                Dati acquisto
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
                                    <InputLabel htmlFor="buy_price" value="Prezzo acquisto *" />
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
                                            {currencySymbol}
                                        </span>
                                    </div>
                                    <InputError message={errors.buy_price} className="mt-2" />
                                </div>
                            </div>

                            <div>
                                <InputLabel htmlFor="buy_date" value="Data acquisto *" />
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
                                <p className="text-sm text-gray-600 dark:text-gray-300">
                                    Totale acquisto:{' '}
                                    <span className="font-semibold tabular-nums text-gray-900 dark:text-white">
                                        {formatMoney(totalBuyValue, currencyCode)}
                                    </span>
                                </p>
                            )}
                        </section>

                        <section className="space-y-4 border-t border-gray-200 pt-5 dark:border-gray-700">
                            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                                Dati vendita <span className="font-normal text-gray-500">(opzionale)</span>
                            </h3>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="sell_price" value="Prezzo vendita" />
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
                                            {currencySymbol}
                                        </span>
                                    </div>
                                    <InputError message={errors.sell_price} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="sell_date" value="Data vendita" />
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
                                <p className={clsx(
                                    'text-sm',
                                    profit >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400',
                                )}>
                                    {profit >= 0 ? 'Profitto netto' : 'Perdita netta'}:{' '}
                                    <span className="font-semibold tabular-nums">
                                        {profit >= 0 ? '+' : ''}
                                        {formatMoney(profit, currencyCode)}
                                    </span>
                                </p>
                            )}
                        </section>

                        <section className="space-y-4 border-t border-gray-200 pt-5 dark:border-gray-700">
                            <div>
                                <InputLabel htmlFor="account_id" value="Conto di riferimento" />
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

                            <div>
                                <InputLabel htmlFor="fees" value="Commissioni totali" />
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
                                        {currencySymbol}
                                    </span>
                                </div>
                                <InputError message={errors.fees} className="mt-2" />
                            </div>

                            <div>
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

                            <label className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    className="rounded border-gray-300 text-emerald-500 shadow-sm focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                    checked={data.is_private}
                                    onChange={(e) => setData('is_private', e.target.checked)}
                                />
                                <span className="text-sm text-gray-700 dark:text-gray-300">
                                    Investimento privato
                                </span>
                            </label>
                        </section>

                        <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700 max-sm:pb-[calc(5.5rem+env(safe-area-inset-bottom,0px))] sm:pb-0">
                            <Link
                                href={route('investments.show', investment.id)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Annulla
                            </Link>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Salvataggio...' : 'Salva modifiche'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
