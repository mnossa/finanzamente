import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { shouldOfferBiometricLoginUi } from '@/utils/pwaDisplayMode';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export default function Login({
    status,
    canResetPassword,
    canRegister,
    environmentBadge,
}: {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    environmentBadge?: string | null;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });
    const [showBiometric, setShowBiometric] = useState(false);
    const [biometricError, setBiometricError] = useState<string | null>(null);

    const { verify, isLoading: biometricLoading, isSupported } = usePasskeyVerify({
        autofill: true,
        onSuccess: (response) => {
            const redirect =
                response && typeof response === 'object' && 'redirect' in response
                    ? String((response as { redirect?: string }).redirect ?? '')
                    : '';
            window.location.href = redirect || route('dashboard');
        },
        onError: (err) => {
            if (err instanceof UserCancelledError) {
                setBiometricError(null);
                return;
            }
            setBiometricError(err.message || 'Accesso con biometria non riuscito. Usa email e password.');
        },
    });

    useEffect(() => {
        setShowBiometric(shouldOfferBiometricLoginUi());
    }, []);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const startBiometricLogin = () => {
        setBiometricError(null);
        void verify();
    };

    return (
        <GuestLayout>
            <Head title="Accedi" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {environmentBadge && (
                <div className="mb-4 inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold tracking-wide text-amber-700">
                    Ambiente: {environmentBadge}
                </div>
            )}

            {showBiometric && isSupported && (
                <div className="mb-6 space-y-3">
                    <PrimaryButton
                        type="button"
                        className="w-full"
                        disabled={biometricLoading}
                        onClick={startBiometricLogin}
                    >
                        {biometricLoading ? 'Verifica in corso…' : 'Accedi con impronta o Face ID'}
                    </PrimaryButton>
                    {biometricError && (
                        <p className="text-sm text-red-600 dark:text-red-400">{biometricError}</p>
                    )}
                    <div className="relative py-1 text-center text-xs uppercase tracking-wide text-gray-400">
                        <span className="bg-white px-2 dark:bg-gray-800">oppure</span>
                    </div>
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username webauthn"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            Ricordami
                        </span>
                    </label>
                </div>

                <div className="mt-4 flex items-center justify-end">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                        >
                            Password dimenticata?
                        </Link>
                    )}

                    <PrimaryButton className="ms-4" disabled={processing}>
                        Accedi
                    </PrimaryButton>
                </div>
            </form>

            {canRegister && (
                <div className="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                    Non hai ancora un account?{' '}
                    <Link
                        href={route('register')}
                        className="font-medium text-emerald-600 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-emerald-400 dark:hover:text-emerald-300"
                    >
                        Registrati
                    </Link>
                </div>
            )}
        </GuestLayout>
    );
}
