import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import axios from 'axios';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    const handleLogout: FormEventHandler = async (e) => {
        e.preventDefault();
        const form = e.currentTarget as HTMLFormElement;
        try {
            await axios.post(form.action, {}, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                withCredentials: true,
            });
            window.location.href = '/';
        } catch (error) {
            // Gestione errore opzionale
        }
    };

    return (
        <GuestLayout>
            <Head title="Verifica Email" />

            <div className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                Grazie per esserti registrato! Prima di iniziare, potresti verificare
                il tuo indirizzo email cliccando sul link che ti abbiamo appena inviato.
                Se non hai ricevuto l'email, saremo felici di inviartene un'altra.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
                    Un nuovo link di verifica è stato inviato all'indirizzo email
                    che hai fornito durante la registrazione.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                        Invia nuova email di verifica
                    </PrimaryButton>

                    <form onSubmit={handleLogout} action={route('logout')} method="POST" className="inline">
                        <button
                            type="submit"
                            className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                        >
                            Esci
                        </button>
                    </form>
                </div>
            </form>
        </GuestLayout>
    );
}
