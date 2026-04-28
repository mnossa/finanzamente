import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import InlineSuccessBadge from '@/Components/InlineSuccessBadge';
import SectionBadge from '@/Components/SectionBadge';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header className="space-y-2">
                <SectionBadge
                    label="Profilo"
                    icon={(
                        <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 2a4 4 0 100 8 4 4 0 000-8zm-6 15a6 6 0 1112 0H4z" />
                        </svg>
                    )}
                />
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Informazioni Profilo
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Aggiorna le informazioni del tuo profilo e l'indirizzo email.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nome" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <p className="text-sm text-amber-900 dark:text-amber-200">
                            Il tuo indirizzo email non è verificato.
                            {' '}
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm font-medium text-amber-900 underline decoration-amber-400 underline-offset-2 hover:text-amber-950 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:text-amber-200 dark:hover:text-amber-100 dark:focus:ring-offset-gray-800"
                            >
                                Clicca qui per inviare nuovamente l'email di verifica.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                Un nuovo link di verifica è stato inviato al tuo
                                indirizzo email.
                            </div>
                        )}
                    </div>
                )}

                <FormActionsBar>
                    <PrimaryButton disabled={processing}>Salva</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <InlineSuccessBadge />
                    </Transition>
                </FormActionsBar>
            </form>
        </section>
    );
}
