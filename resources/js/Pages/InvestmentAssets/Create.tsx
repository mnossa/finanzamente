import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AssetSearch from '@/Components/AssetSearch';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler } from 'react';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Types {
    [key: string]: string;
}

interface TypeIcons {
    [key: string]: string;
}

interface CreateProps {
    currencies: Currency[];
    types: Types;
    typeIcons: TypeIcons;
}

export default function Create({ currencies, types, typeIcons }: CreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        type: 'stock',
        symbol: '',
        isin: '',
        exchange: '',
        name: '',
        currency_code: 'EUR',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('investment-assets.store'));
    };

    const handleAssetSelect = (asset: { symbol: string; name: string; type: string; currency: string; region?: string }) => {
        setData({
            ...data,
            symbol: asset.symbol || '',
            name: asset.name || '',
            type: asset.type || 'stock',
            currency_code: asset.currency || 'EUR',
            exchange: asset.region || '',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('investment-assets.index')}
                        className="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                        ←
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Nuovo Asset Finanziario
                    </h2>
                </div>
            }
        >
            <Head title="Nuovo Asset" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit}>
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="p-6">
                                {/* Ricerca Asset Online */}
                                <div className="mb-6">
                                    <InputLabel value="🔍 Cerca Asset Online" />
                                    <AssetSearch 
                                        onSelect={handleAssetSelect}
                                        className="mt-2"
                                    />
                                </div>

                                <div className="relative mb-6">
                                    <div className="absolute inset-0 flex items-center">
                                        <div className="w-full border-t border-gray-200 dark:border-gray-700" />
                                    </div>
                                    <div className="relative flex justify-center">
                                        <span className="bg-white px-3 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                            oppure inserisci manualmente
                                        </span>
                                    </div>
                                </div>

                                {/* Tipo Asset */}
                                <div className="mb-6">
                                    <InputLabel value="Tipo di Asset *" />
                                    <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {Object.entries(types).map(([value, label]) => (
                                            <button
                                                key={value}
                                                type="button"
                                                onClick={() => setData('type', value)}
                                                className={clsx(
                                                    'flex flex-col items-center rounded-lg border-2 p-3 transition-colors',
                                                    data.type === value
                                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                                )}
                                            >
                                                <span className="text-2xl">{typeIcons[value]}</span>
                                                <span className="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                                    {label}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={errors.type} className="mt-2" />
                                </div>

                                {/* Nome */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="name" value="Nome Asset *" />
                                    <TextInput
                                        id="name"
                                        type="text"
                                        className="mt-2 w-full"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Es. Apple Inc., Bitcoin, Vanguard S&P 500..."
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                {/* Simbolo */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="symbol" value="Simbolo / Ticker" />
                                    <TextInput
                                        id="symbol"
                                        type="text"
                                        className="mt-2 w-full"
                                        value={data.symbol}
                                        onChange={(e) => setData('symbol', e.target.value.toUpperCase())}
                                        placeholder="Es. AAPL, BTC, VOO..."
                                        maxLength={20}
                                    />
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Opzionale: il codice identificativo dell'asset
                                    </p>
                                    <InputError message={errors.symbol} className="mt-2" />
                                </div>

                                {/* ISIN e Exchange */}
                                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="isin" value="Codice ISIN" />
                                        <TextInput
                                            id="isin"
                                            type="text"
                                            className="mt-2 w-full font-mono"
                                            value={data.isin}
                                            onChange={(e) => setData('isin', e.target.value.toUpperCase())}
                                            placeholder="Es. US0378331005"
                                            maxLength={12}
                                        />
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Identificativo internazionale (12 caratteri)
                                        </p>
                                        <InputError message={errors.isin} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="exchange" value="Borsa" />
                                        <TextInput
                                            id="exchange"
                                            type="text"
                                            className="mt-2 w-full"
                                            value={data.exchange}
                                            onChange={(e) => setData('exchange', e.target.value)}
                                            placeholder="Es. United States, Germany, Italy..."
                                            maxLength={50}
                                        />
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Mercato di riferimento
                                        </p>
                                        <InputError message={errors.exchange} className="mt-2" />
                                    </div>
                                </div>

                                {/* Valuta */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="currency_code" value="Valuta *" />
                                    <select
                                        id="currency_code"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.currency_code}
                                        onChange={(e) => setData('currency_code', e.target.value)}
                                    >
                                        {currencies.map((currency) => (
                                            <option key={currency.code} value={currency.code}>
                                                {currency.symbol} - {currency.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.currency_code} className="mt-2" />
                                </div>
                            </div>

                            {/* Footer */}
                            <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                                <Link
                                    href={route('investment-assets.index')}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Creazione...' : '💼 Crea Asset'}
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
