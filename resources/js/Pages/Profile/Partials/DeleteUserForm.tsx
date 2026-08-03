import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import FormActionsBar from '@/Components/FormActionsBar';
import Modal from '@/Components/Modal';
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
        <section className={`space-y-4 ${className}`}>
            <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                    Elimina account
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Una volta eliminato l&apos;account, tutte le risorse e i dati
                    saranno cancellati in modo permanente.
                </p>
            </div>

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
