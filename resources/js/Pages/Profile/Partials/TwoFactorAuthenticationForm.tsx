import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorAuthenticationForm({
    enabled,
    enabledAt,
    recoveryCodes,
    className = '',
}: {
    enabled: boolean;
    enabledAt?: string | null;
    recoveryCodes?: string[] | null;
    className?: string;
}) {
    const safeRecoveryCodes = recoveryCodes ?? [];
    const [showDisableForm, setShowDisableForm] = useState(false);
    const [showRegenerateForm, setShowRegenerateForm] = useState(false);

    const disableForm = useForm({
        password: '',
        code: '',
    });

    const regenerateForm = useForm({
        password: '',
        code: '',
    });

    const submitDisable: FormEventHandler = (e) => {
        e.preventDefault();
        disableForm.post(route('profile.two-factor.disable'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowDisableForm(false);
                disableForm.reset();
            },
        });
    };

    const submitRegenerate: FormEventHandler = (e) => {
        e.preventDefault();
        regenerateForm.post(route('profile.two-factor.recovery-codes'), {
            preserveScroll: true,
            onSuccess: () => {
                setShowRegenerateForm(false);
                regenerateForm.reset();
            },
        });
    };

    return (
        <section className={className}>
            <h2 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                Autenticazione a due fattori
            </h2>
            <p className="mb-5 text-sm text-gray-600 dark:text-gray-300">
                Proteggi il tuo account con un codice generato dall&apos;app di autenticazione che preferisci
                (TOTP). Compatibile con Aegis, FreeOTP, Microsoft Authenticator, 1Password e altre app standard.
                I segreti restano sui server Finanzamente, in Europa.
            </p>

            {safeRecoveryCodes.length > 0 && (
                <div className="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/30">
                    <p className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                        Salva questi codici di recupero in un posto sicuro
                    </p>
                    <p className="mt-1 text-xs text-amber-800 dark:text-amber-300">
                        Ogni codice può essere usato una sola volta se perdi l&apos;accesso all&apos;app.
                    </p>
                    <ul className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {safeRecoveryCodes.map((code) => (
                            <li key={code} className="rounded bg-white px-3 py-2 font-mono text-sm text-gray-900 dark:bg-gray-900 dark:text-gray-100">
                                {code}
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {enabled ? (
                <div className="space-y-4">
                    <p className="text-sm text-emerald-700 dark:text-emerald-300">
                        MFA attiva{enabledAt ? ` dal ${enabledAt}` : ''}.
                    </p>

                    {!showRegenerateForm ? (
                        <button
                            type="button"
                            onClick={() => setShowRegenerateForm(true)}
                            className="text-sm font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                        >
                            Rigenera codici di recupero
                        </button>
                    ) : (
                        <form onSubmit={submitRegenerate} className="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-gray-700">
                            <InputLabel htmlFor="regen_password" value="Password attuale" />
                            <TextInput
                                id="regen_password"
                                type="password"
                                value={regenerateForm.data.password}
                                onChange={(e) => regenerateForm.setData('password', e.target.value)}
                                className="block w-full"
                            />
                            <InputError message={regenerateForm.errors.password} />
                            <InputLabel htmlFor="regen_code" value="Codice dall'app" />
                            <TextInput
                                id="regen_code"
                                value={regenerateForm.data.code}
                                onChange={(e) => regenerateForm.setData('code', e.target.value)}
                                className="block w-full"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                            />
                            <InputError message={regenerateForm.errors.code} />
                            <div className="flex gap-3">
                                <PrimaryButton disabled={regenerateForm.processing}>Genera nuovi codici</PrimaryButton>
                                <button type="button" onClick={() => setShowRegenerateForm(false)} className="text-sm underline">
                                    Annulla
                                </button>
                            </div>
                        </form>
                    )}

                    {!showDisableForm ? (
                        <button
                            type="button"
                            onClick={() => setShowDisableForm(true)}
                            className="text-sm font-medium text-red-600 underline hover:text-red-700"
                        >
                            Disattiva autenticazione a due fattori
                        </button>
                    ) : (
                        <form onSubmit={submitDisable} className="space-y-3 rounded-xl border border-red-100 p-4 dark:border-red-900/40">
                            <InputLabel htmlFor="disable_password" value="Password attuale" />
                            <TextInput
                                id="disable_password"
                                type="password"
                                value={disableForm.data.password}
                                onChange={(e) => disableForm.setData('password', e.target.value)}
                                className="block w-full"
                            />
                            <InputError message={disableForm.errors.password} />
                            <InputLabel htmlFor="disable_code" value="Codice dall'app o codice di recupero" />
                            <TextInput
                                id="disable_code"
                                value={disableForm.data.code}
                                onChange={(e) => disableForm.setData('code', e.target.value)}
                                className="block w-full"
                                autoComplete="one-time-code"
                            />
                            <InputError message={disableForm.errors.code} />
                            <div className="flex gap-3">
                                <PrimaryButton disabled={disableForm.processing}>Disattiva MFA</PrimaryButton>
                                <button type="button" onClick={() => setShowDisableForm(false)} className="text-sm underline">
                                    Annulla
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            ) : (
                <Link
                    href={route('profile.two-factor.enable')}
                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-accent transition-all hover:bg-emerald-700"
                >
                    Abilita autenticazione a due fattori
                </Link>
            )}
        </section>
    );
}
