import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import ProfileQuizSettingsCard from './Partials/ProfileQuizSettingsCard';
import ConsentPreferencesForm from './Partials/ConsentPreferencesForm';
import NotificationPreferencesForm from './Partials/NotificationPreferencesForm';
import MobileBottomNavPreferencesForm from './Partials/MobileBottomNavPreferencesForm';
import SharingAndDataCard from './Partials/SharingAndDataCard';
import TwoFactorAuthenticationForm from './Partials/TwoFactorAuthenticationForm';
import PasskeyAuthenticationForm, { PasskeySummary } from './Partials/PasskeyAuthenticationForm';
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
    passkeys = [],
    sharing,
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
    passkeys?: PasskeySummary[];
    sharing: {
        households_count: number;
        active_household: SharingHousehold | null;
        households_select_url: string;
    };
}>) {
    return (
        <AuthenticatedLayout
            header={<PageHeader title="Profilo" />}
        >
            <Head title="Profilo" />

            <PageContent maxWidth="5xl" className="space-y-4">
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
                        <TwoFactorAuthenticationForm
                            enabled={twoFactorEnabled}
                            enabledAt={twoFactorEnabledAt}
                            recoveryCodes={twoFactorRecoveryCodes}
                            className="w-full"
                        />
                    </SectionCard>

                    <SectionCard className="lg:col-span-2">
                        <PasskeyAuthenticationForm
                            passkeys={passkeys}
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
