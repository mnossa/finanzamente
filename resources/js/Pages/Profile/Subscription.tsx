import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { PageProps } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { useState, FormEventHandler } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import clsx from 'clsx';

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

interface SubscriptionData {
    id: number;
    plan: string;
    billing_cycle: string | null;
    status: string;
    amount_cents: number;
    formatted_amount: string;
    currency: string;
    next_payment_at: string | null;
    ends_at: string | null;
    billing_name: string | null;
    billing_email: string | null;
    billing_address: string | null;
    billing_city: string | null;
    billing_zip: string | null;
    billing_country: string | null;
    billing_vat: string | null;
    billing_company: string | null;
}

interface Props extends PageProps {
    subscription: SubscriptionData | null;
    currentPlan: string;
    plans: Record<string, PlanData>;
    proEnabled: boolean;
    waitlistEnabled: boolean;
    fromFeature?: string | null;
}

function StatusBadge({ status }: { status: string }) {
    const config: Record<string, { label: string; className: string }> = {
        active: { label: 'Attivo', className: 'bg-emerald-100 text-emerald-700' },
        pending: { label: 'In attesa', className: 'bg-yellow-100 text-yellow-700' },
        cancelled: { label: 'Cancellato', className: 'bg-red-100 text-red-700' },
        past_due: { label: 'Scaduto', className: 'bg-orange-100 text-orange-700' },
        completed: { label: 'Completato', className: 'bg-slate-100 text-slate-700' },
    };
    const { label, className } = config[status] ?? { label: status, className: 'bg-slate-100 text-slate-700' };

    return (
        <span className={clsx('inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', className)}>
            {label}
        </span>
    );
}

function BillingForm({ subscription }: { subscription: SubscriptionData | null }) {
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        billing_name: subscription?.billing_name ?? '',
        billing_email: subscription?.billing_email ?? '',
        billing_address: subscription?.billing_address ?? '',
        billing_city: subscription?.billing_city ?? '',
        billing_zip: subscription?.billing_zip ?? '',
        billing_country: subscription?.billing_country ?? 'IT',
        billing_vat: subscription?.billing_vat ?? '',
        billing_company: subscription?.billing_company ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('subscription.billing.update'));
    };

    if (!subscription) return null;

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <InputLabel htmlFor="billing_name" value="Nome / Ragione sociale *" />
                    <TextInput
                        id="billing_name"
                        value={data.billing_name}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.billing_name} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="billing_email" value="Email fatturazione *" />
                    <TextInput
                        id="billing_email"
                        type="email"
                        value={data.billing_email}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_email', e.target.value)}
                        required
                    />
                    <InputError message={errors.billing_email} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="billing_company" value="Azienda (opzionale)" />
                    <TextInput
                        id="billing_company"
                        value={data.billing_company}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_company', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="billing_vat" value="P.IVA / C.F. (opzionale)" />
                    <TextInput
                        id="billing_vat"
                        value={data.billing_vat}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_vat', e.target.value)}
                    />
                </div>
                <div className="sm:col-span-2">
                    <InputLabel htmlFor="billing_address" value="Indirizzo (opzionale)" />
                    <TextInput
                        id="billing_address"
                        value={data.billing_address}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_address', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="billing_city" value="Città (opzionale)" />
                    <TextInput
                        id="billing_city"
                        value={data.billing_city}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_city', e.target.value)}
                    />
                </div>
                <div>
                    <InputLabel htmlFor="billing_zip" value="CAP (opzionale)" />
                    <TextInput
                        id="billing_zip"
                        value={data.billing_zip}
                        className="mt-1 block w-full"
                        onChange={(e) => setData('billing_zip', e.target.value)}
                    />
                </div>
            </div>

            <div className="flex items-center gap-4">
                <PrimaryButton disabled={processing}>Salva dati fatturazione</PrimaryButton>
                {recentlySuccessful && (
                    <span className="text-sm text-emerald-600">Salvato.</span>
                )}
            </div>
        </form>
    );
}

export default function Subscription({ subscription, currentPlan, plans, proEnabled, waitlistEnabled, fromFeature }: Props) {
    const [showCancelConfirm, setShowCancelConfirm] = useState(false);
    const [showBillingForm, setShowBillingForm] = useState(false);
    const [isAnnual, setIsAnnual] = useState(false);

    const proPlan = plans['pro'];
    const isProUser = currentPlan === 'pro';

    const handleCancelSubscription = () => {
        router.post(route('subscription.cancel'), {}, {
            onSuccess: () => setShowCancelConfirm(false),
        });
    };

    const handleUpdatePaymentMethod = () => {
        router.post(route('subscription.update-payment-method'));
    };

    const handleUpgradeToPro = () => {
        const billingCycle = isAnnual ? 'annual' : 'monthly';
        router.post(route('subscription.checkout'), { billing_cycle: billingCycle });
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="Abbonamento e Fatturazione" />}>
            <Head title="Abbonamento" />

            <PageContent>
                {/* Piano attuale */}
                <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                    <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                        Piano attuale
                    </h2>

                    <div className="flex items-center gap-3 mb-4">
                        <span className={clsx(
                            'px-3 py-1 rounded-full text-sm font-semibold',
                            isProUser
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-700',
                        )}>
                            {isProUser ? '⭐ Pro' : 'Base'}
                        </span>
                        {subscription && <StatusBadge status={subscription.status} />}
                    </div>

                    {subscription && isProUser && (
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-slate-600 dark:text-slate-400">
                            <div>
                                <span className="font-medium text-slate-800 dark:text-slate-200 block">Importo</span>
                                {subscription.formatted_amount}
                                {subscription.billing_cycle && (
                                    <span className="text-slate-500">
                                        {' '}/ {subscription.billing_cycle === 'annual' ? 'anno' : 'mese'}
                                    </span>
                                )}
                            </div>
                            {subscription.next_payment_at && (
                                <div>
                                    <span className="font-medium text-slate-800 dark:text-slate-200 block">Prossimo rinnovo</span>
                                    {subscription.next_payment_at}
                                </div>
                            )}
                            {subscription.ends_at && (
                                <div>
                                    <span className="font-medium text-slate-800 dark:text-slate-200 block">Scade il</span>
                                    {subscription.ends_at}
                                </div>
                            )}
                        </div>
                    )}

                    {!isProUser && (
                        <p className="text-slate-600 dark:text-slate-400 text-sm">
                            Stai utilizzando il piano gratuito. Esplora le funzionalità aggiuntive del piano Pro.
                        </p>
                    )}
                </div>

                {/* Feature contestuale se l'utente è stato reindirizzato da un modulo Pro */}
                {!isProUser && fromFeature && (() => {
                    const featureLabels: Record<string, { name: string; description: string }> = {
                        simulations: { name: 'Simulazioni finanziarie', description: 'proiezioni e scenari per il tuo futuro finanziario' },
                        inter_household_transfers: { name: 'Trasferimenti tra Household', description: 'fondi trasferibili tra le tue diverse household' },
                        inbox: { name: 'Inbox Telegram', description: 'conferma e gestisci transazioni direttamente da Telegram' },
                        tax_refund_730: { name: 'Detrazioni fiscali / 730', description: 'tracciamento spese detraibili e report per la dichiarazione' },
                        investments: { name: 'Investimenti e portafoglio', description: 'monitoraggio di azioni, ETF e fondi nel tuo portafoglio' },
                        asset_allocation: { name: 'Asset Allocation', description: 'analisi della distribuzione del tuo patrimonio' },
                        investment_assets: { name: 'Gestione Asset', description: 'gestione personalizzata dei tuoi strumenti finanziari' },
                        investment_analyses: { name: 'Analisi Investimenti', description: 'dashboard avanzata per analizzare le performance' },
                        lifestyle_score: { name: 'Lifestyle Inflation Score', description: "monitoraggio dell'evoluzione del tuo stile di vita nel tempo" },
                    };
                    const feat = featureLabels[fromFeature];
                    if (!feat) return null;
                    return (
                        <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                            <span className="mt-0.5 text-amber-500">🔒</span>
                            <span className="text-amber-800 dark:text-amber-200">
                                Stavi cercando <strong>{feat.name}</strong> — {feat.description}. Questa funzionalità è disponibile nel piano Pro.
                            </span>
                        </div>
                    );
                })()}

                {/* Upgrade a Pro — modalità waitlist */}
                {!isProUser && waitlistEnabled && proPlan && (
                    <div className="bg-gradient-to-r from-amber-50 to-orange-50 p-6 shadow sm:rounded-lg border border-amber-200">
                        <div className="flex items-start gap-3">
                            <span className="text-2xl">🚀</span>
                            <div className="flex-1">
                                <h2 className="text-lg font-semibold text-amber-900 mb-1">
                                    Piano Pro — In arrivo
                                </h2>
                                <p className="text-amber-800 text-sm mb-4">
                                    Stiamo lavorando per rendere disponibile il piano Pro. Iscriviti alla waitlist per essere tra i primi a scoprirlo e accedere a condizioni speciali early bird.
                                </p>
                                <a
                                    href="/#piani"
                                    className="inline-flex items-center px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl transition-colors"
                                >
                                    🔔 Iscriviti alla waitlist
                                </a>
                            </div>
                        </div>
                    </div>
                )}

                {/* Upgrade a Pro (solo se piano base, Pro abilitato e non in modalità waitlist) */}
                {!isProUser && proEnabled && !waitlistEnabled && proPlan && (
                    <div className="bg-gradient-to-r from-emerald-50 to-emerald-100 p-6 shadow sm:rounded-lg border border-emerald-200">
                        <h2 className="text-lg font-semibold text-emerald-900 mb-2">
                            Passa a Pro
                        </h2>
                        <p className="text-emerald-800 text-sm mb-4">
                            Sblocca tutte le funzionalità avanzate di Finanzamente.
                        </p>

                        {/* Toggle mensile/annuale */}
                        <div className="flex items-center gap-3 mb-5">
                            <span className={clsx('text-sm font-medium', !isAnnual ? 'text-emerald-900' : 'text-emerald-600')}>Mensile</span>
                            <button
                                type="button"
                                role="switch"
                                aria-checked={isAnnual}
                                aria-label={isAnnual ? 'Passa a fatturazione mensile' : 'Passa a fatturazione annuale'}
                                onClick={() => setIsAnnual((v) => !v)}
                                className={clsx(
                                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                                    isAnnual ? 'bg-emerald-500' : 'bg-emerald-200',
                                )}
                            >
                                <span className={clsx(
                                    'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
                                    isAnnual ? 'translate-x-6' : 'translate-x-1',
                                )} />
                            </button>
                            <span className={clsx('text-sm font-medium', isAnnual ? 'text-emerald-900' : 'text-emerald-600')}>
                                Annuale
                                <span className="ml-1.5 bg-emerald-600 text-white text-xs px-1.5 py-0.5 rounded-full">
                                    -{proPlan.annual_discount_percent}%
                                </span>
                            </span>
                        </div>

                        <div className="flex items-baseline gap-1 mb-5">
                            <span className="text-3xl font-bold text-emerald-900">
                                {isAnnual
                                    ? `${proPlan.price_annual_monthly.toFixed(2).replace('.', ',')} €`
                                    : `${proPlan.price_monthly.toFixed(2).replace('.', ',')} €`}
                            </span>
                            <span className="text-emerald-700 text-sm">/mese</span>
                            {isAnnual && (
                                <span className="text-emerald-600 text-sm ml-2">
                                    ({proPlan.price_annual_total.toFixed(2).replace('.', ',')} €/anno)
                                </span>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={handleUpgradeToPro}
                            className="inline-flex items-center px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl transition-colors"
                        >
                            Passa a Pro {isAnnual ? '(annuale)' : '(mensile)'}
                        </button>
                    </div>
                )}

                {/* Gestione abbonamento Pro attivo */}
                {isProUser && subscription && subscription.status === 'active' && (
                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                            Gestione abbonamento
                        </h2>
                        <div className="space-y-3">
                            {/* Aggiorna metodo di pagamento */}
                            <div className="flex items-start justify-between p-4 rounded-lg border border-slate-200 dark:border-gray-700">
                                <div>
                                    <p className="font-medium text-slate-800 dark:text-slate-200 text-sm">Metodo di pagamento</p>
                                    <p className="text-slate-500 dark:text-slate-400 text-xs mt-0.5">
                                        Aggiorna la carta o il metodo di addebito tramite il portale sicuro Mollie.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleUpdatePaymentMethod}
                                    className="ml-4 flex-shrink-0 text-sm text-emerald-600 hover:text-emerald-700 font-medium underline"
                                >
                                    Aggiorna
                                </button>
                            </div>

                            {/* Cancella rinnovo */}
                            <div className="flex items-start justify-between p-4 rounded-lg border border-red-100 dark:border-red-900/30">
                                <div>
                                    <p className="font-medium text-slate-800 dark:text-slate-200 text-sm">Disabilita rinnovo automatico</p>
                                    <p className="text-slate-500 dark:text-slate-400 text-xs mt-0.5">
                                        L'abbonamento rimarrà attivo fino alla scadenza. Non ci saranno altri addebiti.
                                    </p>
                                </div>
                                {!showCancelConfirm ? (
                                    <button
                                        type="button"
                                        onClick={() => setShowCancelConfirm(true)}
                                        className="ml-4 flex-shrink-0 text-sm text-red-600 hover:text-red-700 font-medium underline"
                                    >
                                        Disabilita
                                    </button>
                                ) : (
                                    <div className="ml-4 flex gap-2 flex-shrink-0">
                                        <button
                                            type="button"
                                            onClick={handleCancelSubscription}
                                            className="text-xs px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
                                        >
                                            Conferma
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setShowCancelConfirm(false)}
                                            className="text-xs px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition-colors"
                                        >
                                            Annulla
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Dati fatturazione */}
                {isProUser && subscription && (
                    <div className="bg-white p-6 shadow sm:rounded-lg dark:bg-gray-800">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Dati di fatturazione
                            </h2>
                            {!showBillingForm && (
                                <button
                                    type="button"
                                    onClick={() => setShowBillingForm(true)}
                                    className="text-sm text-emerald-600 hover:text-emerald-700 font-medium underline"
                                >
                                    Modifica
                                </button>
                            )}
                        </div>

                        {!showBillingForm ? (
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                {subscription.billing_name && (
                                    <div>
                                        <dt className="text-slate-500 dark:text-slate-400">Nome / Ragione sociale</dt>
                                        <dd className="text-slate-900 dark:text-slate-200 font-medium">{subscription.billing_name}</dd>
                                    </div>
                                )}
                                {subscription.billing_email && (
                                    <div>
                                        <dt className="text-slate-500 dark:text-slate-400">Email fatturazione</dt>
                                        <dd className="text-slate-900 dark:text-slate-200 font-medium">{subscription.billing_email}</dd>
                                    </div>
                                )}
                                {subscription.billing_company && (
                                    <div>
                                        <dt className="text-slate-500 dark:text-slate-400">Azienda</dt>
                                        <dd className="text-slate-900 dark:text-slate-200 font-medium">{subscription.billing_company}</dd>
                                    </div>
                                )}
                                {subscription.billing_vat && (
                                    <div>
                                        <dt className="text-slate-500 dark:text-slate-400">P.IVA / C.F.</dt>
                                        <dd className="text-slate-900 dark:text-slate-200 font-medium">{subscription.billing_vat}</dd>
                                    </div>
                                )}
                                {subscription.billing_address && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-slate-500 dark:text-slate-400">Indirizzo</dt>
                                        <dd className="text-slate-900 dark:text-slate-200 font-medium">
                                            {subscription.billing_address}
                                            {subscription.billing_city && `, ${subscription.billing_city}`}
                                            {subscription.billing_zip && ` ${subscription.billing_zip}`}
                                            {subscription.billing_country && ` (${subscription.billing_country})`}
                                        </dd>
                                    </div>
                                )}
                                {!subscription.billing_name && !subscription.billing_email && (
                                    <p className="sm:col-span-2 text-slate-500 dark:text-slate-400 text-sm italic">
                                        Nessun dato di fatturazione impostato.
                                    </p>
                                )}
                            </dl>
                        ) : (
                            <div>
                                <BillingForm subscription={subscription} />
                                <button
                                    type="button"
                                    onClick={() => setShowBillingForm(false)}
                                    className="mt-3 text-sm text-slate-500 hover:text-slate-700 underline"
                                >
                                    Annulla
                                </button>
                            </div>
                        )}
                    </div>
                )}

                {/* Informativa Mollie */}
                <div className="bg-slate-50 dark:bg-gray-800/50 p-4 sm:rounded-lg border border-slate-200 dark:border-gray-700">
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                        I pagamenti sono gestiti in modo sicuro da{' '}
                        <a href="https://www.mollie.com" target="_blank" rel="noopener noreferrer" className="underline hover:text-slate-700">
                            Mollie
                        </a>
                        . Finanzamente non conserva i dati della tua carta di credito.
                        La modifica del metodo di pagamento avviene tramite il portale sicuro di Mollie.
                    </p>
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
