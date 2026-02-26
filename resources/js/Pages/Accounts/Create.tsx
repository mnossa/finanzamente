import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface AccountTypes {
    [key: string]: string;
}

interface CreateProps {
    accountTypes: AccountTypes;
    currencies: Currency[];
    defaultCurrency: string;
}

export default function Create({ accountTypes, currencies, defaultCurrency }: CreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'bank',
        initial_balance: '0',
        currency_code: defaultCurrency,
        is_private: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('accounts.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuovo Conto"
                    backLink={route('accounts.index')}
                />
            }
        >
            <Head title="Nuovo Conto" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <CardBox>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Nome */}
                            <div>
                                <InputLabel htmlFor="name" value="Nome del conto" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="es. Conto Corrente, Portafoglio, ecc."
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            {/* Tipo */}
                            <div>
                                <InputLabel htmlFor="type" value="Tipo di conto" />
                                <div className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    {Object.entries(accountTypes).map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => setData('type', value)}
                                            className={clsx(
                                                'flex items-center space-x-2 rounded-lg border-2 p-3 text-left transition-colors',
                                                data.type === value
                                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                            )}
                                        >
                                            <span className="text-2xl">
                                                {getAccountTypeIcon(value)}
                                            </span>
                                            <span className="text-sm font-medium text-gray-900 dark:text-white">
                                                {label}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                <InputError message={errors.type} className="mt-2" />
                            </div>

                            {/* Saldo Iniziale e Valuta */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="initial_balance" value="Saldo iniziale" />
                                    <TextInput
                                        id="initial_balance"
                                        type="number"
                                        step="0.01"
                                        className="mt-1 block w-full"
                                        value={data.initial_balance}
                                        onChange={(e) => setData('initial_balance', e.target.value)}
                                        required
                                    />
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Il saldo attuale del conto al momento della creazione
                                    </p>
                                    <InputError message={errors.initial_balance} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="currency_code" value="Valuta" />
                                    <select
                                        id="currency_code"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.currency_code}
                                        onChange={(e) => setData('currency_code', e.target.value)}
                                        required
                                    >
                                        {currencies.map((currency) => (
                                            <option key={currency.code} value={currency.code}>
                                                {currency.code} - {currency.name} ({currency.symbol})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.currency_code} className="mt-2" />
                                </div>
                            </div>

                            {/* Conto Privato */}
                            <div className="flex items-start rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div className="flex h-6 items-center">
                                    <input
                                        id="is_private"
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                        checked={data.is_private}
                                        onChange={(e) => setData('is_private', e.target.checked)}
                                    />
                                </div>
                                <div className="ml-3">
                                    <label
                                        htmlFor="is_private"
                                        className="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        🔒 Conto privato
                                    </label>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Solo tu potrai vedere questo conto e le sue transazioni. Gli altri membri della household non avranno accesso.
                                    </p>
                                </div>
                            </div>

                            {/* Azioni */}
                            <div className="flex items-center justify-end space-x-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                                <Link
                                    href={route('accounts.index')}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : 'Crea Conto'}
                                </PrimaryButton>
                            </div>
                        </form>
                    </CardBox>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
