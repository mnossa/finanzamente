import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import clsx from 'clsx';
import UmamiAnalytics from '@/Components/UmamiAnalytics';
import { PageProps } from '@/types';

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
    plans: Record<string, PlanData>;
    proEnabled: boolean;
    annualDiscountPercent: number;
}

function formatPrice(cents: number): string {
    return cents.toFixed(2).replace('.', ',');
}

export default function SelectPlan({ plans, proEnabled, annualDiscountPercent }: Props) {
    const [isAnnual, setIsAnnual] = useState(false);
    const { privacy } = usePage<PageProps>().props;

    const basePlan = plans['base'];
    const proPlan = plans['pro'];

    const handleSelectPlan = (planKey: string) => {
        const billingCycle = isAnnual ? 'annual' : 'monthly';
        router.get(route('register'), { plan: planKey, billing_cycle: billingCycle });
    };

    return (
        <>
            <UmamiAnalytics enabled={privacy?.analytics_enabled ?? false} />
            <div className="min-h-screen bg-slate-50 flex flex-col items-center py-12 px-4">
                {/* Logo */}
                <div className="mb-8">
                    <Link href="/" className="flex items-center gap-3">
                        <ApplicationLogo className="w-12 h-12" />
                        <span className="text-2xl font-bold text-slate-800">Finanzamente</span>
                    </Link>
                </div>

                <Head title="Scegli il tuo piano" />

                <div className="w-full max-w-4xl">
                    <div className="text-center mb-10">
                        <h1 className="text-3xl font-bold text-slate-900 mb-3">
                            Scegli il piano giusto per te
                        </h1>
                        <p className="text-slate-600 text-lg">
                            Inizia gratis o sblocca tutte le funzionalità con il piano Pro.
                        </p>
                    </div>

                    {/* Toggle mensile/annuale */}
                    {proEnabled && proPlan && (
                        <div className="flex justify-center mb-10">
                            <div className="inline-flex items-center gap-4 bg-white rounded-full px-6 py-3 shadow-sm border border-slate-200">
                                <span className={clsx('text-sm font-medium transition-colors', !isAnnual ? 'text-slate-900' : 'text-slate-400')}>
                                    Mensile
                                </span>
                                <button
                                    type="button"
                                    role="switch"
                                    aria-checked={isAnnual}
                                    aria-label="Passa al piano annuale"
                                    onClick={() => setIsAnnual((v) => !v)}
                                    className={clsx(
                                        'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                                        isAnnual ? 'bg-emerald-500' : 'bg-slate-200',
                                    )}
                                >
                                    <span
                                        className={clsx(
                                            'inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform',
                                            isAnnual ? 'translate-x-6' : 'translate-x-1',
                                        )}
                                    />
                                </button>
                                <span className={clsx('text-sm font-medium transition-colors', isAnnual ? 'text-slate-900' : 'text-slate-400')}>
                                    Annuale
                                    <span className="ml-2 inline-block bg-emerald-100 text-emerald-700 text-xs font-semibold px-2 py-0.5 rounded-full">
                                        -{annualDiscountPercent}%
                                    </span>
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Cards piani */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Piano Base */}
                        {basePlan && (
                            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 flex flex-col">
                                <div className="mb-6">
                                    <span className="text-sm font-medium text-slate-500 uppercase tracking-wider">
                                        {basePlan.name}
                                    </span>
                                    <div className="mt-2 flex items-baseline gap-1">
                                        <span className="text-4xl font-bold text-slate-900">Gratis</span>
                                    </div>
                                    <p className="mt-1 text-slate-500 text-sm">{basePlan.label}</p>
                                </div>

                                <ul className="space-y-3 mb-8 flex-1">
                                    {basePlan.features.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2">
                                            <svg className="w-5 h-5 text-emerald-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                            </svg>
                                            <span className="text-slate-700 text-sm">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    onClick={() => handleSelectPlan('base')}
                                    className="w-full py-3 px-6 rounded-xl font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-colors"
                                >
                                    Inizia gratis
                                </button>
                            </div>
                        )}

                        {/* Piano Pro */}
                        {proPlan && proEnabled && (
                            <div className="bg-linear-to-b from-emerald-600 to-emerald-700 rounded-2xl shadow-lg p-8 flex flex-col relative overflow-hidden">
                                <div className="absolute top-4 right-4">
                                    <span className="bg-white/20 text-white text-xs font-semibold px-3 py-1 rounded-full">
                                        Consigliato
                                    </span>
                                </div>

                                <div className="mb-6">
                                    <span className="text-sm font-medium text-emerald-200 uppercase tracking-wider">
                                        {proPlan.name}
                                    </span>
                                    <div className="mt-2 flex items-baseline gap-1">
                                        <span className="text-4xl font-bold text-white">
                                            {isAnnual
                                                ? `${formatPrice(proPlan.price_annual_monthly)} €`
                                                : `${formatPrice(proPlan.price_monthly)} €`}
                                        </span>
                                        <span className="text-emerald-200 text-sm">/mese</span>
                                    </div>
                                    {isAnnual && (
                                        <div className="mt-1 space-y-0.5">
                                            <p className="text-emerald-200 text-sm">
                                                <span className="line-through text-emerald-300/70">
                                                    {formatPrice(proPlan.price_monthly * 12)} €/anno
                                                </span>
                                                {' → '}
                                                <span className="font-semibold text-white">
                                                    {formatPrice(proPlan.price_annual_total)} €/anno
                                                </span>
                                            </p>
                                            <p className="text-emerald-100 text-xs">
                                                Risparmi {formatPrice(proPlan.price_monthly * 12 - proPlan.price_annual_total)} €/anno
                                            </p>
                                        </div>
                                    )}
                                    {!isAnnual && (
                                        <p className="mt-1 text-emerald-200 text-sm">{proPlan.label}</p>
                                    )}
                                </div>

                                <ul className="space-y-3 mb-8 flex-1">
                                    {proPlan.features.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2">
                                            <svg className="w-5 h-5 text-white mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                            </svg>
                                            <span className="text-emerald-50 text-sm">{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <button
                                    type="button"
                                    onClick={() => handleSelectPlan('pro')}
                                    className="w-full py-3 px-6 rounded-xl font-semibold text-emerald-700 bg-white hover:bg-emerald-50 transition-colors shadow-sm"
                                >
                                    {isAnnual ? 'Inizia con Pro annuale' : 'Inizia con Pro mensile'}
                                </button>
                            </div>
                        )}

                        {/* Piano Pro disabilitato */}
                        {proPlan && !proEnabled && (
                            <div className="bg-slate-100 rounded-2xl border border-slate-200 shadow-sm p-8 flex flex-col opacity-60">
                                <div className="mb-6">
                                    <span className="text-sm font-medium text-slate-500 uppercase tracking-wider">
                                        {proPlan.name}
                                    </span>
                                    <div className="mt-2">
                                        <span className="text-2xl font-bold text-slate-500">Presto disponibile</span>
                                    </div>
                                </div>
                                <ul className="space-y-3 mb-8 flex-1">
                                    {proPlan.features.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2">
                                            <svg className="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                            </svg>
                                            <span className="text-slate-500 text-sm">{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                                <button disabled className="w-full py-3 px-6 rounded-xl font-semibold text-slate-400 bg-slate-200 cursor-not-allowed">
                                    Non disponibile
                                </button>
                            </div>
                        )}
                    </div>

                    <p className="text-center text-slate-500 text-sm mt-8">
                        Hai già un account?{' '}
                        <Link href={route('login')} className="text-emerald-600 hover:text-emerald-700 font-medium underline">
                            Accedi
                        </Link>
                    </p>
                </div>
            </div>
        </>
    );
}
