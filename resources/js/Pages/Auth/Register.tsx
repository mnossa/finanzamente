import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { trackEvent } from '../../utils/tracking';

interface PlanData {
    key: string;
    name: string;
    label: string;
    price_monthly: number;
    price_annual_monthly: number;
    price_annual_total: number;
    annual_discount_percent: number;
    currency: string;
    features: string[];
    available: boolean;
}

interface Props {
    selectedPlan?: string;
    billingCycle?: string;
    plans?: Record<string, PlanData>;
    proEnabled?: boolean;
}

function formatPrice(value: number): string {
    return value.toFixed(2).replace('.', ',');
}

function PlanSummary({
    plan,
    billingCycle,
    plans,
}: {
    plan: string;
    billingCycle: string;
    plans: Record<string, PlanData>;
}) {
    const planData = plans[plan];
    if (!planData) return null;

    const isAnnual = billingCycle === 'annual';
    const isBase = plan === 'base';

    const monthlyPrice = isAnnual ? planData.price_annual_monthly : planData.price_monthly;
    const totalPrice = isAnnual ? planData.price_annual_total : planData.price_monthly;

    return (
        <div className="bg-slate-50 rounded-xl border border-slate-200 p-5 space-y-3">
            <h3 className="font-semibold text-slate-800 flex items-center gap-2">
                <svg className="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                Riepilogo ordine
            </h3>

            <div className="space-y-1.5 text-sm">
                <div className="flex justify-between">
                    <span className="text-slate-600">Piano</span>
                    <span className="font-medium text-slate-900">{planData.name}</span>
                </div>
                {!isBase && (
                    <div className="flex justify-between">
                        <span className="text-slate-600">Periodicità</span>
                        <span className="font-medium text-slate-900">{isAnnual ? 'Annuale' : 'Mensile'}</span>
                    </div>
                )}
                {!isBase && isAnnual && (
                    <>
                        <div className="flex justify-between text-slate-500">
                            <span>Prezzo originale</span>
                            <span className="line-through">{formatPrice(planData.price_monthly * 12)} €/anno</span>
                        </div>
                        <div className="flex justify-between text-emerald-600">
                            <span>Sconto annuale (-{planData.annual_discount_percent}%)</span>
                            <span>-{formatPrice(planData.price_monthly * 12 - planData.price_annual_total)} €</span>
                        </div>
                    </>
                )}
            </div>

            <div className="pt-2 border-t border-slate-200">
                {isBase ? (
                    <div className="flex justify-between font-semibold text-slate-900">
                        <span>Totale</span>
                        <span className="text-emerald-600">Gratuito</span>
                    </div>
                ) : (
                    <div className="space-y-0.5">
                        <div className="flex justify-between font-semibold text-slate-900">
                            <span>Totale</span>
                            <span>
                                {isAnnual
                                    ? `${formatPrice(totalPrice)} €/anno`
                                    : `${formatPrice(totalPrice)} €/mese`}
                            </span>
                        </div>
                        {isAnnual && (
                            <div className="text-right text-xs text-slate-500">
                                ({formatPrice(monthlyPrice)} €/mese)
                            </div>
                        )}
                    </div>
                )}
            </div>

            {!isBase && (
                <p className="text-xs text-slate-500 pt-1">
                    Il pagamento verrà richiesto dopo la verifica email, tramite Mollie.
                    Puoi annullare il rinnovo automatico in qualsiasi momento.
                </p>
            )}

            <Link
                href={route('plan.select')}
                className="block text-center text-xs text-emerald-600 hover:text-emerald-700 underline"
            >
                Cambia piano
            </Link>
        </div>
    );
}

export default function Register({ selectedPlan = 'base', billingCycle = 'monthly', plans = {}, proEnabled = true }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        user_type: 'persona' as 'persona' | 'partita_iva',
        fiscal_code: '',
        vat_number: '',
        selected_plan: selectedPlan,
        billing_cycle: billingCycle,
        marketing_email: false,
        analytics_tracking: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => {
                trackEvent('registrazione_effettuata', {
                    user_type: data.user_type,
                    plan: data.selected_plan,
                });
                reset('password', 'password_confirmation');
            },
        });
    };

    const hasPlanSummary = Object.keys(plans).length > 0;

    const sidebar = hasPlanSummary ? (
        <PlanSummary
            plan={data.selected_plan}
            billingCycle={data.billing_cycle}
            plans={plans}
        />
    ) : undefined;

    return (
        <GuestLayout sidebar={sidebar}>
            <Head title="Registrati" />

            <div className="flex flex-col">
                {/* Form di registrazione */}
                <div>
                    <form onSubmit={submit} autoComplete="off">
                        {/* Honeypot fields per laravel-honeypot */}
                        <input type="text" name="my_name" className="hidden" tabIndex={-1} autoComplete="off" aria-hidden="true" />
                        <input type="hidden" name="my_time" value={typeof window !== 'undefined' ? Date.now() : ''} />

                        {/* Campi nascosti piano selezionato */}
                        <input type="hidden" name="selected_plan" value={data.selected_plan} />
                        <input type="hidden" name="billing_cycle" value={data.billing_cycle} />

                        <div>
                            <InputLabel htmlFor="name" value="Nome *" />
                            <TextInput
                                id="name"
                                name="name"
                                value={data.name}
                                className="mt-1 block w-full"
                                autoComplete="name"
                                isFocused={true}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>

                        <div className="mt-4">
                            <InputLabel htmlFor="email" value="Email *" />
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block w-full"
                                autoComplete="username"
                                onChange={(e) => setData('email', e.target.value)}
                                required
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <div className="mt-4">
                            <InputLabel htmlFor="user_type" value="Tipo Utente *" />
                            <select
                                id="user_type"
                                name="user_type"
                                value={data.user_type}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-emerald-600 dark:focus:ring-emerald-600"
                                onChange={(e) => setData('user_type', e.target.value as 'persona' | 'partita_iva')}
                                required
                            >
                                <option value="persona">Persona Fisica</option>
                                <option value="partita_iva">Partita IVA</option>
                            </select>
                            <InputError message={errors.user_type} className="mt-2" />
                        </div>

                        {data.user_type === 'persona' && (
                            <div className="mt-4">
                                <InputLabel htmlFor="fiscal_code" value="Codice Fiscale" />
                                <TextInput
                                    id="fiscal_code"
                                    name="fiscal_code"
                                    value={data.fiscal_code}
                                    className="mt-1 block w-full uppercase"
                                    placeholder="RSSMRA80A01H501U"
                                    maxLength={16}
                                    onChange={(e) => setData('fiscal_code', e.target.value.toUpperCase())}
                                />
                                <InputError message={errors.fiscal_code} className="mt-2" />
                            </div>
                        )}

                        {data.user_type === 'partita_iva' && (
                            <div className="mt-4">
                                <InputLabel htmlFor="vat_number" value="Partita IVA" />
                                <TextInput
                                    id="vat_number"
                                    name="vat_number"
                                    value={data.vat_number}
                                    className="mt-1 block w-full"
                                    placeholder="12345678901"
                                    maxLength={11}
                                    onChange={(e) => setData('vat_number', e.target.value)}
                                />
                                <InputError message={errors.vat_number} className="mt-2" />
                            </div>
                        )}

                        <div className="mt-4">
                            <InputLabel htmlFor="password" value="Password *" />
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="mt-4">
                            <InputLabel htmlFor="password_confirmation" value="Conferma Password *" />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                required
                            />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>

                        <div className="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Preferenze privacy (opzionali)
                            </p>
                            <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Privacy policy e termini vengono accettati durante la registrazione. Qui puoi impostare i consensi opzionali.
                            </p>

                            <label className="mt-3 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.marketing_email}
                                    onChange={(e) => setData('marketing_email', e.target.checked)}
                                    className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span>Ricevi email marketing e aggiornamenti prodotto.</span>
                            </label>

                            <label className="mt-2 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.analytics_tracking}
                                    onChange={(e) => setData('analytics_tracking', e.target.checked)}
                                    className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span>Consenti analytics per miglioramento servizio.</span>
                            </label>

                        </div>

                        <div className="mt-4 flex items-center justify-end">
                            <Link
                                href={route('login')}
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                            >
                                Hai già un account?
                            </Link>

                            <PrimaryButton className="ms-4" disabled={processing}>
                                Registrati
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
