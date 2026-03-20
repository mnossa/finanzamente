import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler } from 'react';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface InvestmentAsset {
    id: number;
    type: string;
    symbol: string | null;
    isin: string | null;
    exchange: string | null;
    name: string;
    currency_code: string;
    extra_data: object | null;
}

interface Types {
    [key: string]: string;
}

interface TypeIcons {
    [key: string]: string;
}

interface EditProps {
    asset: InvestmentAsset;
    currencies: Currency[];
    types: Types;
    typeIcons: TypeIcons;
}

export default function Edit({ asset, currencies, types, typeIcons }: EditProps) {
    const { data, setData, put, processing, errors } = useForm({
        type: asset.type,
        symbol: asset.symbol || '',
        isin: asset.isin || '',
        exchange: asset.exchange || '',
        name: asset.name,
        currency_code: asset.currency_code,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('investment-assets.update', asset.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Modifica ${asset.name}`}
                    backLink={route('investment-assets.index')}
                />
            }
        >
            <Head title={`Modifica ${asset.name}`} />

            <PageContent maxWidth="2xl">
                    <form onSubmit={submit}>
                        <CardBox className="overflow-hidden shadow-sm">
                            <div className="p-6">
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
                                    {processing ? 'Salvataggio...' : '💾 Salva Modifiche'}
                                </PrimaryButton>
                            </div>
                        </CardBox>
                    </form>
            </PageContent>
        </AuthenticatedLayout>
    );
}
