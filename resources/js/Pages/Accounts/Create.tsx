import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import ProBadge from '@/Components/ProBadge';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AccountCreateGuided from './AccountCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { accs } from '@/utils/analytics';
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

interface CreateProps {
    accountTypes: AccountTypes;
    currencies: Currency[];
    defaultCurrency: string;
    accountsCount: number;
    maxAccounts: number | null;
}

const FEATURED_CURRENCY_CODES = ['EUR', 'USD', 'GBP', 'CHF'] as const;

export default function Create({ accountTypes, currencies, defaultCurrency, accountsCount, maxAccounts }: CreateProps) {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features)) {
        return (
            <AuthenticatedLayout
                header={<PageHeader title="Nuovo conto" backLink={route('accounts.index')} />}
            >
                <Head title="Nuovo Conto" />
                <PageContent maxWidth="3xl">
                    <AccountCreateGuided accountTypes={accountTypes} defaultCurrency={defaultCurrency} />
                </PageContent>
            </AuthenticatedLayout>
        );
    }

    const currenciesByCode = new Map(currencies.map((currency) => [currency.code, currency]));
    const featuredCurrencies = FEATURED_CURRENCY_CODES
        .map((code) => currenciesByCode.get(code))
        .filter((currency): currency is Currency => currency !== undefined);
    const featuredCurrencySet = new Set(featuredCurrencies.map((currency) => currency.code));
    const otherCurrencies = currencies.filter((currency) => !featuredCurrencySet.has(currency.code));

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'bank',
        initial_balance: '0',
        interest_rate: '',
        currency_code: defaultCurrency,
        is_private: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('accounts.store'), {
            onSuccess: () => accs.created(data.type, data.currency_code),
        });
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

            <PageContent maxWidth="3xl">
                    {/* Banner limite piano Base */}
                    {maxAccounts !== null && (
                        <div className={clsx(
                            'mb-4 flex items-start gap-3 rounded-xl border p-4 text-sm',
                            accountsCount >= maxAccounts
                                ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300'
                                : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300'
                        )}>
                            <div className="flex-1">
                                {accountsCount >= maxAccounts ? (
                                    <>
                                        <p className="font-semibold">Limite conti raggiunto ({accountsCount}/{maxAccounts})</p>
                                        <p className="mt-1">Il piano Base permette un massimo di {maxAccounts} conti. Passa al piano Pro per aggiungerne altri.</p>
                                    </>
                                ) : (
                                    <p>
                                        Stai usando <strong>{accountsCount}/{maxAccounts}</strong> conti disponibili nel piano Base.{' '}
                                        <Link href={route('profile.subscription')} className="underline font-medium">
                                            Passa al Pro
                                        </Link>{' '}
                                        per conti illimitati.
                                    </p>
                                )}
                            </div>
                            <ProBadge size="sm" />
                        </div>
                    )}
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge
                                label="Conti"
                                icon={<span className="text-sm leading-none">🏦</span>}
                            />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Crea un nuovo conto
                            </h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Imposta il conto iniziale per tracciare saldo e movimenti.
                            </p>
                        </header>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            {/* Nome */}
                            <div>
                                <InputLabel htmlFor="name" value="Nome del conto" />
                                <TextInput
                                    id="name"
                                    name="name"
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
                                        name="initial_balance"
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

                                <div className={data.type === 'savings_deposit' ? 'sm:col-span-2' : ''}>
                                    {data.type === 'savings_deposit' && (
                                        <div className="mb-4">
                                            <InputLabel htmlFor="interest_rate" value="Tasso di interesse annuo (%)" />
                                            <TextInput
                                                id="interest_rate"
                                                name="interest_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                className="mt-1 block w-full"
                                                value={data.interest_rate}
                                                onChange={(e) => setData('interest_rate', e.target.value)}
                                                placeholder="es. 2.50"
                                                required={data.type === 'savings_deposit'}
                                            />
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Usato per la simulazione di crescita del conto deposito.
                                            </p>
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
                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('accounts.index')}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : 'Crea Conto'}
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
