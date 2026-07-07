import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';
import clsx from 'clsx';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface AccountTypes {
    [key: string]: string;
}

interface Account {
    id: number;
    name: string;
    type: string;
    initial_balance: number;
    interest_rate: number | null;
    currency_code: string;
    active: boolean;
    is_private: boolean;
}

interface EditProps {
    account: Account;
    accountTypes: AccountTypes;
    currencies: Currency[];
}

const FEATURED_CURRENCY_CODES = ['EUR', 'USD', 'GBP', 'CHF'] as const;

export default function Edit({ account, accountTypes, currencies }: EditProps) {
    const currenciesByCode = new Map(currencies.map((currency) => [currency.code, currency]));
    const featuredCurrencies = FEATURED_CURRENCY_CODES
        .map((code) => currenciesByCode.get(code))
        .filter((currency): currency is Currency => currency !== undefined);
    const featuredCurrencySet = new Set(featuredCurrencies.map((currency) => currency.code));
    const otherCurrencies = currencies.filter((currency) => !featuredCurrencySet.has(currency.code));

    const { data, setData, patch, processing, errors } = useForm({
        name: account.name,
        type: account.type,
        initial_balance: String(account.initial_balance),
        interest_rate: account.interest_rate !== null ? String(account.interest_rate) : '',
        currency_code: account.currency_code,
        active: account.active,
        is_private: account.is_private,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('accounts.update', account.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Modifica Conto"
                    backLink={route('accounts.index')}
                />

            }
        >
            <Head title="Modifica Conto" />

            <PageContent maxWidth="3xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Conti" icon={<span className="text-sm leading-none">✏️</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Aggiorna conto</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Modifica i dettagli principali del conto e il suo stato.</p>
                        </header>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            {/* Nome */}
                            <div>
                                <InputLabel htmlFor="name" value="Nome del conto" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
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
                                    <InputError message={errors.initial_balance} className="mt-2" />
                                </div>

                                <div className={data.type === 'savings_deposit' ? 'sm:col-span-2' : ''}>
                                    {data.type === 'savings_deposit' && (
                                        <div className="mb-4">
                                            <InputLabel htmlFor="interest_rate" value="Tasso di interesse annuo (%)" />
                                            <TextInput
                                                id="interest_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                className="mt-1 block w-full"
                                                value={data.interest_rate}
                                                onChange={(e) => setData('interest_rate', e.target.value)}
                                                required={data.type === 'savings_deposit'}
                                            />
                                            <InputError message={errors.interest_rate} className="mt-2" />
                                        </div>
                                    )}

                                    <InputLabel htmlFor="currency_code" value="Valuta" />
                                    <select
                                        id="currency_code"
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.currency_code}
                                        onChange={(e) => setData('currency_code', e.target.value)}
                                        required
                                    >
                                        {featuredCurrencies.length > 0 && (
                                            <optgroup label="Valute principali">
                                                {featuredCurrencies.map((currency) => (
                                                    <option key={currency.code} value={currency.code}>
                                                        {currency.code} - {currency.name} ({currency.symbol})
                                                    </option>
                                                ))}
                                            </optgroup>
                                        )}

                                        {otherCurrencies.length > 0 && (
                                            <optgroup label="Altre valute">
                                                {otherCurrencies.map((currency) => (
                                                    <option key={currency.code} value={currency.code}>
                                                        {currency.code} - {currency.name} ({currency.symbol})
                                                    </option>
                                                ))}
                                            </optgroup>
                                        )}
                                    </select>
                                    <InputError message={errors.currency_code} className="mt-2" />
                                </div>
                            </div>

                            {/* Conto Privato */}
                            <div className="flex items-start">
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
                                        Conto privato
                                    </label>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Solo tu potrai vedere questo conto e le sue transazioni.
                                    </p>
                                </div>
                            </div>

                            {/* Attivo/Archivia */}
                            <div className="flex items-start">
                                <div className="flex h-6 items-center">
                                    <input
                                        id="active"
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                        checked={data.active}
                                        onChange={(e) => setData('active', e.target.checked)}
                                    />
                                </div>
                                <div className="ml-3">
                                    <label
                                        htmlFor="active"
                                        className="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        Conto attivo
                                    </label>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Disattivalo per archiviarlo e nasconderlo dalla dashboard.
                                    </p>
                                </div>
                            </div>

                            {/* Azioni */}
                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('accounts.index')}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : 'Salva Modifiche'}
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
