import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AccountCreateGuided from './AccountCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
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

interface CreateProps {
    accountTypes: AccountTypes;
    currencies: Currency[];
    defaultCurrency: string;
    accountsCount?: number;
    maxAccounts?: number | null;
}

const FEATURED_CURRENCY_CODES = ['EUR', 'USD', 'GBP', 'CHF'] as const;

export default function Create({ accountTypes, currencies, defaultCurrency }: CreateProps) {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features)) {
        return (
            <AuthenticatedLayout
                header={
                    <PageHeader
                        title="Nuovo conto"
                        mobileTitle="Conto"
                        backLink={route('accounts.index')}
                    />
                }
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
        ticket_unit_value: '',
        external_url: '',
        currency_code: defaultCurrency,
        is_private: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('accounts.store'));
    };

    const isSavingsDeposit = data.type === 'savings_deposit';
    const isMealVoucher = data.type === 'meal_voucher';
    const isPensionFund = data.type === 'pension_fund';
    const showExtraTypeField = isSavingsDeposit || isMealVoucher || isPensionFund;

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
                    <SectionCard className="space-y-4">
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
                                    <InputLabel
                                        htmlFor="initial_balance"
                                        value={isPensionFund ? 'Posizione iniziale' : 'Saldo iniziale'}
                                    />
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
                                        {isPensionFund
                                            ? 'Montante attuale dal portale del fondo. Poi lo aggiorni dalla scheda conto.'
                                            : 'Il saldo attuale del conto al momento della creazione'}
                                    </p>
                                    <InputError message={errors.initial_balance} className="mt-2" />
                                </div>

                                <div className={showExtraTypeField ? 'sm:col-span-2' : ''}>
                                    {isSavingsDeposit && (
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
                                                required={isSavingsDeposit}
                                            />
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Usato per la simulazione di crescita del conto deposito.
                                            </p>
                                            <InputError message={errors.interest_rate} className="mt-2" />
                                        </div>
                                    )}

                                    {isMealVoucher && (
                                        <div className="mb-4">
                                            <InputLabel htmlFor="ticket_unit_value" value="Valore di un ticket" />
                                            <TextInput
                                                id="ticket_unit_value"
                                                name="ticket_unit_value"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                className="mt-1 block w-full"
                                                value={data.ticket_unit_value}
                                                onChange={(e) => setData('ticket_unit_value', e.target.value)}
                                                placeholder="es. 8.00"
                                                required={isMealVoucher}
                                            />
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Importo in euro di un singolo buono pasto. I ticket disponibili si calcolano dal saldo.
                                            </p>
                                            <InputError message={errors.ticket_unit_value} className="mt-2" />
                                        </div>
                                    )}

                                    {isPensionFund && (
                                        <div className="mb-4">
                                            <InputLabel htmlFor="external_url" value="Area riservata (opzionale)" />
                                            <TextInput
                                                id="external_url"
                                                name="external_url"
                                                type="url"
                                                className="mt-1 block w-full"
                                                value={data.external_url}
                                                onChange={(e) => setData('external_url', e.target.value)}
                                                placeholder="https://..."
                                            />
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Link al portale del fondo per consultare la posizione. Niente entrate/uscite libere: solo trasferimenti e aggiornamento posizione.
                                            </p>
                                            <InputError message={errors.external_url} className="mt-2" />
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
