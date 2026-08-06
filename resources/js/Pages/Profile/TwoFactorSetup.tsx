import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function TwoFactorSetup({
    qrCodeSvg,
    manualSetupKey,
}: {
    qrCodeSvg: string;
    manualSetupKey: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('profile.two-factor.confirm'));
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="Configura MFA" />}>
            <Head title="Configura MFA" />

            <PageContent maxWidth="3xl" className="space-y-6">
                <div className="rounded-2xl border border-emerald-200/70 bg-white p-6 shadow-sm dark:border-emerald-800/70 dark:bg-gray-900">
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Configura l&apos;autenticazione a due fattori
                    </h1>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Scansiona il QR code con un&apos;app di autenticazione TOTP a tua scelta
                        (Aegis, FreeOTP, Microsoft Authenticator, 1Password, ecc.).
                    </p>

                    <div className="mt-6 flex flex-col items-center gap-4">
                        <div
                            className="rounded-lg bg-white p-3"
                            dangerouslySetInnerHTML={{ __html: qrCodeSvg }}
                        />
                        <div className="text-center">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Oppure inserisci manualmente:</p>
                            <p className="mt-1 font-mono text-sm text-gray-900 dark:text-gray-100">{manualSetupKey}</p>
                        </div>
                    </div>

                    <form onSubmit={submit} className="mt-6 space-y-4">
                        <div>
                            <InputLabel htmlFor="code" value="Codice di verifica a 6 cifre" />
                            <TextInput
                                id="code"
                                name="code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="mt-1 block w-full"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                isFocused
                            />
                            <InputError message={errors.code} className="mt-2" />
                        </div>

                        <div className="flex items-center gap-4">
                            <PrimaryButton disabled={processing}>Conferma e attiva</PrimaryButton>
                            <Link href={route('profile.edit')} className="text-sm underline">
                                Annulla
                            </Link>
                        </div>
                    </form>
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
