import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { trackEvent } from '../../utils/tracking';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        user_type: 'persona' as 'persona' | 'partita_iva',
        fiscal_code: '',
        vat_number: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();



        // Invia i dati al server
        post(route('register'), {
            onFinish: () => {
                trackEvent('registrazione_effettuata', {
                    user_type: data.user_type,
                });
                reset('password', 'password_confirmation')
            },
        });
    };

    return (
        <GuestLayout>
            <Head title="Registrati" />

            <form onSubmit={submit}>
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

                <div className="mt-4">
                    <InputLabel htmlFor="user_type" value="Tipo Utente *" />

                    <select
                        id="user_type"
                        name="user_type"
                        value={data.user_type}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-emerald-600 dark:focus:ring-emerald-600"
                        onChange={(e) =>
                            setData(
                                'user_type',
                                e.target.value as 'persona' | 'partita_iva',
                            )
                        }
                        required
                    >
                        <option value="persona">Persona Fisica</option>
                        <option value="partita_iva">Partita IVA</option>
                    </select>

                    <InputError message={errors.user_type} className="mt-2" />
                </div>

                {data.user_type === 'persona' && (
                    <div className="mt-4">
                        <InputLabel
                            htmlFor="fiscal_code"
                            value="Codice Fiscale"
                        />

                        <TextInput
                            id="fiscal_code"
                            name="fiscal_code"
                            value={data.fiscal_code}
                            className="mt-1 block w-full uppercase"
                            placeholder="RSSMRA80A01H501U"
                            maxLength={16}
                            onChange={(e) =>
                                setData(
                                    'fiscal_code',
                                    e.target.value.toUpperCase(),
                                )
                            }
                        />

                        <InputError
                            message={errors.fiscal_code}
                            className="mt-2"
                        />
                    </div>
                )}

                {data.user_type === 'partita_iva' && (
                    <div className="mt-4">
                        <InputLabel htmlFor="vat_number" value="Partita IVA" />

                        <TextInput
                            id="vat_number"
                            name="vat_number"
                            value={data.vat_number}
                            className="mt-1 block w-full"
                            placeholder="12345678901"
                            maxLength={11}
                            onChange={(e) =>
                                setData('vat_number', e.target.value)
                            }
                        />

                        <InputError
                            message={errors.vat_number}
                            className="mt-2"
                        />
                    </div>
                )}

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
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Conferma Password *"
                    />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
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
        </GuestLayout>
    );
}
