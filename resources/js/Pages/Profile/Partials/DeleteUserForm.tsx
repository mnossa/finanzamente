import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import FormActionsBar from '@/Components/FormActionsBar';
import Modal from '@/Components/Modal';
import SectionBadge from '@/Components/SectionBadge';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { trackEvent } from '../../../utils/tracking';

export default function DeleteUserForm({
    className = '',
}: {
    className?: string;
}) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => {
                trackEvent('cancellazione_account_completata');
                closeModal();
            },
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header className="hidden sm:block space-y-2">
                <SectionBadge
                    label="Zona pericolosa"
                    tone="danger"
                    icon={(
                        <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.518 11.59c.75 1.334-.213 2.99-1.742 2.99H1.48c-1.53 0-2.492-1.656-1.742-2.99L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                    )}
                />
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Elimina Account
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Una volta eliminato il tuo account, tutte le sue risorse e
                    i dati saranno cancellati in modo permanente. Prima di
                    eliminare il tuo account, scarica tutti i dati o le
                    informazioni che desideri conservare.
                </p>
            </header>

            <FormActionsBar sticky={false}>
                <DangerButton onClick={confirmUserDeletion} className="rounded-xl">
                    Elimina Account
                </DangerButton>
            </FormActionsBar>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Sei sicuro di voler eliminare il tuo account?
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Una volta eliminato il tuo account, tutte le sue risorse
                        e i dati saranno cancellati in modo permanente. Inserisci
                        la tua password per confermare l'eliminazione definitiva
                        del tuo account.
                    </p>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value="Password"
                            className="sr-only"
                        />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 block w-full sm:w-3/4"
                            isFocused
                            placeholder="Password"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex flex-wrap justify-end gap-2">
                        <SecondaryButton onClick={closeModal}>
                            Annulla
                        </SecondaryButton>

                        <DangerButton disabled={processing}>
                            Elimina Account
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
