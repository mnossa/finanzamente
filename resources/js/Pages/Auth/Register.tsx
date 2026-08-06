import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        user_type: 'persona' as const,
        fiscal_code: '',
        marketing_email: false,
        analytics_tracking: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => {
                reset('password', 'password_confirmation');
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Registrati" />

            <div className="flex flex-col">
                <div>
                    <form onSubmit={submit} autoComplete="off">
                        {/* Honeypot fields per laravel-honeypot */}
                        <input type="text" name="my_name" className="hidden" tabIndex={-1} autoComplete="off" aria-hidden="true" />
                        <input type="hidden" name="my_time" value={typeof window !== 'undefined' ? Date.now() : ''} />

                        <div>
                            <InputLabel htmlFor="name" value="Nome *" />
                            <TextInput
                                id="name"
                                name="name"
                                value={data.name}
                                className="mt-1 block w-full"
                                autoComplete="name"
                                isFocused={true}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>

                        <div className="mt-4">
                            <InputLabel htmlFor="email" value="Email *" />
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block w-full"
                                autoComplete="username"
                                onChange={(e) => setData('email', e.target.value)}
                                required
                            />
                            <InputError message={errors.email} className="mt-2" />
                        </div>

                        <input type="hidden" name="user_type" value="persona" />

                        <div className="mt-4">
                            <InputLabel htmlFor="fiscal_code" value="Codice Fiscale (opzionale)" />
                            <TextInput
                                id="fiscal_code"
                                name="fiscal_code"
                                value={data.fiscal_code}
                                className="mt-1 block w-full uppercase"
                                placeholder="RSSMRA80A01H501U"
                                maxLength={16}
                                onChange={(e) => setData('fiscal_code', e.target.value.toUpperCase())}
                            />
                            <InputError message={errors.fiscal_code} className="mt-2" />
                        </div>

                        <div className="mt-4">
                            <InputLabel htmlFor="password" value="Password *" />
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                onChange={(e) => setData('password', e.target.value)}
                                required
                            />
                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="mt-4">
                            <InputLabel htmlFor="password_confirmation" value="Conferma Password *" />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                required
                            />
                            <InputError message={errors.password_confirmation} className="mt-2" />
                        </div>

                        <div className="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Preferenze privacy (opzionali)
                            </p>
                            <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Con la registrazione confermi l’informativa privacy e i termini di servizio in vigore (incluso l’uso di dati in
                                forma aggregata o anonimizzata per migliorare il servizio, come descritto in privacy). Qui sotto solo i consensi
                                opzionali.
                            </p>

                            <label className="mt-3 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.marketing_email}
                                    onChange={(e) => setData('marketing_email', e.target.checked)}
                                    className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span>Ricevi email marketing e aggiornamenti prodotto.</span>
                            </label>

                            <label className="mt-2 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.analytics_tracking}
                                    onChange={(e) => setData('analytics_tracking', e.target.checked)}
                                    className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span>Consenti analytics per miglioramento servizio.</span>
                            </label>

                        </div>

                        <div className="mt-4 flex items-center justify-end">
                            <Link
                                href={route('login')}
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                            >
                                Hai già un account?
                            </Link>

                            <PrimaryButton className="ms-4" disabled={processing}>
                                Registrati
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </GuestLayout>
    );
}
