import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import InlineSuccessBadge from '@/Components/InlineSuccessBadge';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface CurrencyOption {
    code: string;
    name: string;
    symbol: string | null;
}

interface CohortSelectOption {
    value: string;
    label: string;
}

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    currencies = [],
    cohortProfileHelp = '',
    cohortIncomeBands = [],
    cohortMacroRegions = [],
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    currencies?: CurrencyOption[];
    cohortProfileHelp?: string;
    cohortIncomeBands?: CohortSelectOption[];
    cohortMacroRegions?: CohortSelectOption[];
    className?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            default_currency_code: user.default_currency_code ?? '',
            income_band: user.income_band ?? '',
            macro_region: user.macro_region ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                Informazioni profilo
            </h2>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Nome" />

                    <TextInput
                        id="name"
                        name="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        name="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="default_currency_code" value="Valuta predefinita" />
                    <select
                        id="default_currency_code"
                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        value={data.default_currency_code ?? ''}
                        onChange={(e) => setData('default_currency_code', e.target.value)}
                    >
                        <option value="">Euro (predefinita)</option>
                        {currencies.map((c) => (
                            <option key={c.code} value={c.code}>
                                {c.code} — {c.name}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Usata come valuta di default per le spese inserite via Telegram quando non la specifichi nel messaggio.
                    </p>
                    <InputError className="mt-2" message={errors.default_currency_code} />
                </div>

                {cohortIncomeBands.length > 0 && (
                    <div className="rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                        <InputLabel htmlFor="income_band" value="Fascia di reddito (facoltativa)" />
                        <select
                            id="income_band"
                            name="income_band"
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.income_band}
                            onChange={(e) => setData('income_band', e.target.value)}
                        >
                            <option value="">Non specificata</option>
                            {cohortIncomeBands.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-2" message={errors.income_band} />

                        <div className="mt-4">
                            <InputLabel htmlFor="macro_region" value="Macro-area (facoltativa)" />
                            <select
                                id="macro_region"
                                name="macro_region"
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.macro_region}
                                onChange={(e) => setData('macro_region', e.target.value)}
                            >
                                <option value="">Non specificata</option>
                                {cohortMacroRegions.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </select>
                            <InputError className="mt-2" message={errors.macro_region} />
                        </div>

                        {cohortProfileHelp && (
                            <p className="mt-3 text-xs text-gray-600 dark:text-gray-400">
                                {cohortProfileHelp}
                            </p>
                        )}
                    </div>
                )}

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <p className="text-sm text-amber-900 dark:text-amber-200">
                            Il tuo indirizzo email non è verificato.
                            {' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm font-medium text-amber-900 underline decoration-amber-400 underline-offset-2 hover:text-amber-950 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-amber-200 dark:hover:text-amber-100 dark:focus:ring-offset-gray-800"
                            >
                                Clicca qui per inviare nuovamente l'email di verifica.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                Un nuovo link di verifica è stato inviato al tuo
                                indirizzo email.
                            </div>
                        )}
                    </div>
                )}

                <FormActionsBar sticky={false}>
                    <PrimaryButton disabled={processing}>Salva</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <InlineSuccessBadge />
                    </Transition>
                </FormActionsBar>
            </form>
        </section>
    );
}
