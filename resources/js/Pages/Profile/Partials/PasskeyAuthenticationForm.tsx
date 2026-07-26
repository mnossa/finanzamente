import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { shouldOfferBiometricLoginUi } from '@/utils/pwaDisplayMode';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { Link, router } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export type PasskeySummary = {
    id: number;
    name: string;
    authenticator?: string | null;
    last_used_at?: string | null;
    created_at?: string | null;
};

export default function PasskeyAuthenticationForm({
    passkeys = [],
    manageMode = false,
    className = '',
}: {
    passkeys?: PasskeySummary[];
    manageMode?: boolean;
    className?: string;
}) {
    const [name, setName] = useState('Questo dispositivo');
    const [localError, setLocalError] = useState<string | null>(null);
    const [showBiometricHint, setShowBiometricHint] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setLocalError(null);
            router.reload({ only: ['passkeys', 'successMessage'] });
        },
        onError: (err) => {
            if (err instanceof UserCancelledError) {
                setLocalError(null);
                return;
            }
            setLocalError(err.message || 'Registrazione della chiave di accesso non riuscita.');
        },
    });

    useEffect(() => {
        setShowBiometricHint(shouldOfferBiometricLoginUi());
    }, []);

    const submitRegister: FormEventHandler = (e) => {
        e.preventDefault();
        setLocalError(null);
        const trimmed = name.trim();
        if (!trimmed) {
            setLocalError('Inserisci un nome per questa chiave di accesso.');
            return;
        }
        void register(trimmed);
    };

    const deletePasskey = (passkeyId: number) => {
        if (!window.confirm('Eliminare questa chiave di accesso? Non potrai più usarla per accedere.')) {
            return;
        }

        router.delete(`/user/passkeys/${passkeyId}`, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['passkeys', 'successMessage'] });
            },
        });
    };

    const displayError = localError || error;

    return (
        <section className={className}>
            <h2 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                Chiavi di accesso (biometria)
            </h2>
            <p className="mb-5 text-sm text-gray-600 dark:text-gray-300">
                Accedi all&apos;app installata con impronta digitale o Face ID, senza digitare la password.
                Funziona sui dispositivi che supportano le chiavi di accesso (passkey). La password resta
                sempre disponibile come alternativa.
            </p>

            {showBiometricHint && (
                <p className="mb-4 text-xs text-emerald-700 dark:text-emerald-300">
                    Su questo dispositivo puoi usare lo sblocco biometrico dopo aver registrato una chiave.
                </p>
            )}

            {passkeys.length > 0 ? (
                <ul className="mb-5 space-y-3">
                    {passkeys.map((passkey) => (
                        <li
                            key={passkey.id}
                            className="flex flex-col gap-2 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700"
                        >
                            <div>
                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {passkey.name}
                                </p>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {passkey.authenticator ? `${passkey.authenticator} · ` : ''}
                                    {passkey.last_used_at
                                        ? `Ultimo uso ${passkey.last_used_at}`
                                        : passkey.created_at
                                          ? `Creata il ${passkey.created_at}`
                                          : 'Mai usata'}
                                </p>
                            </div>
                            {manageMode && (
                                <button
                                    type="button"
                                    onClick={() => deletePasskey(passkey.id)}
                                    className="text-sm font-medium text-red-600 underline hover:text-red-700"
                                >
                                    Elimina
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mb-5 text-sm text-gray-600 dark:text-gray-300">
                    Nessuna chiave di accesso registrata.
                </p>
            )}

            {manageMode ? (
                <form onSubmit={submitRegister} className="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-gray-700">
                    {!isSupported && (
                        <p className="text-sm text-amber-800 dark:text-amber-200">
                            Questo browser non supporta le chiavi di accesso. Usa un telefono o un browser aggiornato.
                        </p>
                    )}
                    <InputLabel htmlFor="passkey_name" value="Nome della chiave" />
                    <TextInput
                        id="passkey_name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        className="block w-full"
                        placeholder="Es. iPhone di Mario"
                        maxLength={100}
                    />
                    <InputError message={displayError ?? undefined} />
                    <PrimaryButton type="submit" disabled={isLoading || !isSupported}>
                        {isLoading ? 'Registrazione…' : 'Registra chiave di accesso'}
                    </PrimaryButton>
                </form>
            ) : (
                <Link
                    href={route('profile.passkeys.manage')}
                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-accent transition-all hover:bg-emerald-700"
                >
                    {passkeys.length > 0 ? 'Gestisci chiavi di accesso' : 'Configura sblocco biometrico'}
                </Link>
            )}
        </section>
    );
}
