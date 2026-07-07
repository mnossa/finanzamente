import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function TwoFactorChallenge() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.login.store'));
    };

    return (
        <GuestLayout>
            <Head title="Verifica a due fattori" />

            <div className="mb-4">
                <h1 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Verifica a due fattori
                </h1>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    Inserisci il codice a 6 cifre dalla tua app di autenticazione TOTP
                    oppure un codice di recupero.
                </p>
            </div>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="code" value="Codice" />
                    <TextInput
                        id="code"
                        name="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        className="mt-1 block w-full"
                        autoComplete="one-time-code"
                        isFocused
                    />
                    <InputError message={errors.code} className="mt-2" />
                </div>

                <div className="mt-6">
                    <PrimaryButton className="w-full justify-center" disabled={processing}>
                        Verifica
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
