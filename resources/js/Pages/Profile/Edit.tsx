import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import ProfileQuizSettingsCard from './Partials/ProfileQuizSettingsCard';
import ConsentPreferencesForm from './Partials/ConsentPreferencesForm';
import NotificationPreferencesForm from './Partials/NotificationPreferencesForm';
import MobileBottomNavPreferencesForm from './Partials/MobileBottomNavPreferencesForm';
import SharingAndDataCard from './Partials/SharingAndDataCard';
import TwoFactorAuthenticationForm from './Partials/TwoFactorAuthenticationForm';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';

interface CurrencyOption {
    code: string;
    name: string;
    symbol: string | null;
}

interface CohortSelectOption {
    value: string;
    label: string;
}

interface SharingHousehold {
    id: number;
    name: string;
    financial_management_type: string;
    financial_management_label: string;
    url: string;
}

export default function Edit({
    mustVerifyEmail,
    status,
    successMessage,
    errorMessage,
    consents,
    currencies = [],
    cohortProfileHelp = '',
    cohortIncomeBands = [],
    cohortMacroRegions = [],
    twoFactorEnabled = false,
    twoFactorEnabledAt,
    twoFactorRecoveryCodes = [],
    sharing,
    proPlanFeatures = [],
    currentPlan = 'base',
    proEnabled = true,
}: PageProps<{
    mustVerifyEmail: boolean;
    status?: string;
    successMessage?: string;
    errorMessage?: string;
    consents: {
        marketing_email: boolean;
        analytics_tracking: boolean;
    };
    currencies?: CurrencyOption[];
    cohortProfileHelp?: string;
    cohortIncomeBands?: CohortSelectOption[];
    cohortMacroRegions?: CohortSelectOption[];
    twoFactorEnabled?: boolean;
    twoFactorEnabledAt?: string | null;
    twoFactorRecoveryCodes?: string[];
    sharing: {
        households_count: number;
        active_household: SharingHousehold | null;
        households_select_url: string;
    };
    proPlanFeatures?: string[];
    currentPlan?: string;
    proEnabled?: boolean;
}>) {
    const visibleProFeatures = proPlanFeatures.slice(0, 6);

    return (
        <AuthenticatedLayout
            header={<PageHeader title="Profilo" />}
        >
            <Head title="Profilo" />

            <PageContent maxWidth="5xl" className="space-y-4">
                <section className="rounded-2xl border border-emerald-200/70 bg-linear-to-br from-emerald-50 via-white to-teal-50 p-5 shadow-sm sm:p-7 dark:border-emerald-800/70 dark:from-emerald-950/30 dark:via-gray-900 dark:to-teal-950/20">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Impostazioni account
                    </h1>
                    <p className="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300">
                        Aggiorna i tuoi dati personali, gestisci le preferenze privacy e configura la profilazione per ricevere un&apos;esperienza su misura.
                    </p>
                </section>

                {successMessage && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                        {successMessage}
                    </div>
                )}

                {errorMessage && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                        {errorMessage}
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-2">
                    <SectionCard>
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            currencies={currencies}
                            cohortProfileHelp={cohortProfileHelp}
                            cohortIncomeBands={cohortIncomeBands}
                            cohortMacroRegions={cohortMacroRegions}
                            className="w-full"
                        />
                    </SectionCard>

                    <SectionCard>
                        <ProfileQuizSettingsCard className="w-full" />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <SharingAndDataCard sharing={sharing} className="w-full" />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <ConsentPreferencesForm className="w-full" consents={consents} />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <MobileBottomNavPreferencesForm />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <NotificationPreferencesForm />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <div className="w-full">
                            <h2 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                                Abbonamento e funzionalità aggiuntive
                            </h2>
                            <p className="mb-3 text-sm text-gray-600 dark:text-gray-300">
                                {currentPlan === 'pro'
                                    ? 'Hai il piano Pro attivo con accesso alle funzionalità avanzate.'
                                    : 'Passa al piano Pro per sbloccare strumenti avanzati senza limiti su nuclei, investimenti e integrazioni.'}
                            </p>
                            {currentPlan !== 'pro' && visibleProFeatures.length > 0 && (
                                <ul className="mb-5 grid gap-2 sm:grid-cols-2">
                                    {visibleProFeatures.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <span className="mt-0.5 text-emerald-600">✓</span>
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {proEnabled && (
                                <Link
                                    href={route('profile.subscription')}
                                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-accent transition-all hover:bg-emerald-700 hover:shadow-accent-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                    {currentPlan === 'pro' ? 'Gestisci abbonamento' : 'Scopri e acquista Pro'}
                                </Link>
                            )}
                        </div>
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <TwoFactorAuthenticationForm
                            enabled={twoFactorEnabled}
                            enabledAt={twoFactorEnabledAt}
                            recoveryCodes={twoFactorRecoveryCodes}
                            className="w-full"
                        />
                    </SectionCard>

                    <SectionCard>
                        <UpdatePasswordForm className="w-full" />
                    </SectionCard>

                    <SectionCard>
                        <DeleteUserForm className="w-full" />
                    </SectionCard>
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
