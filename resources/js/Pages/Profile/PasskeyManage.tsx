import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import SectionCard from '@/Components/SectionCard';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import PasskeyAuthenticationForm, { PasskeySummary } from './Partials/PasskeyAuthenticationForm';

export default function PasskeyManage({
    passkeys = [],
    successMessage,
    errorMessage,
}: PageProps<{
    passkeys: PasskeySummary[];
    successMessage?: string;
    errorMessage?: string;
}>) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Chiavi di accesso" />}>
            <Head title="Chiavi di accesso" />

            <PageContent maxWidth="3xl" className="space-y-4">
                {successMessage && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                        {successMessage}
                    </div>
                )}
                {errorMessage && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200">
                        {errorMessage}
                    </div>
                )}

                <SectionCard>
                    <PasskeyAuthenticationForm passkeys={passkeys} manageMode className="w-full" />
                </SectionCard>

                <div>
                    <Link
                        href={route('profile.edit')}
                        className="text-sm font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                    >
                        Torna al profilo
                    </Link>
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
