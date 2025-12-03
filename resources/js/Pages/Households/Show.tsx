import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Household, HouseholdMember, PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Props extends PageProps {
    household: Household;
    members: HouseholdMember[];
}

export default function Show({ household, members }: Props) {
    const [showInviteModal, setShowInviteModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showLeaveModal, setShowLeaveModal] = useState(false);

    // Form per modifica nome
    const editForm = useForm({
        name: household.name,
    });

    // Form per invito
    const inviteForm = useForm({
        email: '',
        role: 'member' as 'member' | 'guest',
    });

    const updateHousehold: FormEventHandler = (e) => {
        e.preventDefault();
        editForm.patch(route('households.update', household.id));
    };

    const inviteMember: FormEventHandler = (e) => {
        e.preventDefault();
        inviteForm.post(route('households.invite', household.id), {
            onSuccess: () => {
                setShowInviteModal(false);
                inviteForm.reset();
            },
        });
    };

    const removeMember = (memberId: number) => {
        if (confirm('Sei sicuro di voler rimuovere questo membro?')) {
            router.delete(
                route('households.remove-member', [household.id, memberId]),
            );
        }
    };

    const deleteHousehold = () => {
        router.delete(route('households.destroy', household.id));
    };

    const leaveHousehold = () => {
        router.post(route('households.leave', household.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Impostazioni Household
                </h2>
            }
        >
            <Head title={`Household: ${household.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {/* Informazioni Household */}
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                        <section className="max-w-xl">
                            <header>
                                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Informazioni Household
                                </h2>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Modifica il nome della tua household.
                                </p>
                            </header>

                            <form
                                onSubmit={updateHousehold}
                                className="mt-6 space-y-6"
                            >
                                <div>
                                    <InputLabel htmlFor="name" value="Nome" />
                                    <TextInput
                                        id="name"
                                        className="mt-1 block w-full"
                                        value={editForm.data.name}
                                        onChange={(e) =>
                                            editForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        disabled={!household.is_owner}
                                    />
                                    <InputError
                                        message={editForm.errors.name}
                                        className="mt-2"
                                    />
                                </div>

                                {household.is_owner && (
                                    <PrimaryButton
                                        disabled={editForm.processing}
                                    >
                                        Salva
                                    </PrimaryButton>
                                )}
                            </form>
                        </section>
                    </div>

                    {/* Membri */}
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                        <section>
                            <header className="flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Membri
                                    </h2>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Gestisci i membri della household.
                                    </p>
                                </div>
                                {household.is_owner && (
                                    <PrimaryButton
                                        onClick={() => setShowInviteModal(true)}
                                    >
                                        Invita Membro
                                    </PrimaryButton>
                                )}
                            </header>

                            <div className="mt-6 space-y-3">
                                {members.map((member) => (
                                    <div
                                        key={member.id}
                                        className="flex items-center justify-between rounded-lg border border-gray-200 p-4 dark:border-gray-700"
                                    >
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {member.name}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                {member.email}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                    member.is_owner
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : member.role ===
                                                            'guest'
                                                          ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                          : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                }`}
                                            >
                                                {member.is_owner
                                                    ? 'Proprietario'
                                                    : member.role === 'guest'
                                                      ? 'Ospite'
                                                      : 'Membro'}
                                            </span>
                                            {household.is_owner &&
                                                !member.is_owner && (
                                                    <button
                                                        onClick={() =>
                                                            removeMember(
                                                                member.id,
                                                            )
                                                        }
                                                        className="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                    >
                                                        Rimuovi
                                                    </button>
                                                )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    {/* Zona pericolosa */}
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                        <section className="max-w-xl">
                            <header>
                                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Zona Pericolosa
                                </h2>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {household.is_owner
                                        ? 'Elimina questa household e tutti i dati associati.'
                                        : 'Lascia questa household.'}
                                </p>
                            </header>

                            <div className="mt-6">
                                {household.is_owner ? (
                                    <DangerButton
                                        onClick={() => setShowDeleteModal(true)}
                                    >
                                        Elimina Household
                                    </DangerButton>
                                ) : (
                                    <DangerButton
                                        onClick={() => setShowLeaveModal(true)}
                                    >
                                        Lascia Household
                                    </DangerButton>
                                )}
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            {/* Modal Invito */}
            <Modal show={showInviteModal} onClose={() => setShowInviteModal(false)}>
                <form onSubmit={inviteMember} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Invita un Membro
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Inserisci l'email dell'utente che vuoi invitare.
                    </p>

                    <div className="mt-6">
                        <InputLabel htmlFor="invite_email" value="Email" />
                        <TextInput
                            id="invite_email"
                            type="email"
                            className="mt-1 block w-full"
                            value={inviteForm.data.email}
                            onChange={(e) =>
                                inviteForm.setData('email', e.target.value)
                            }
                            placeholder="utente@esempio.it"
                            isFocused
                        />
                        <InputError
                            message={inviteForm.errors.email}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="invite_role" value="Ruolo" />
                        <select
                            id="invite_role"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={inviteForm.data.role}
                            onChange={(e) =>
                                inviteForm.setData(
                                    'role',
                                    e.target.value as 'member' | 'guest',
                                )
                            }
                        >
                            <option value="member">Membro</option>
                            <option value="guest">Ospite (solo visualizzazione)</option>
                        </select>
                    </div>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            onClick={() => setShowInviteModal(false)}
                        >
                            Annulla
                        </SecondaryButton>
                        <PrimaryButton disabled={inviteForm.processing}>
                            Invita
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Modal Elimina */}
            <Modal show={showDeleteModal} onClose={() => setShowDeleteModal(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Sei sicuro di voler eliminare questa household?
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Questa azione è irreversibile. Tutti i conti, le
                        transazioni e i dati associati verranno eliminati
                        definitivamente.
                    </p>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            onClick={() => setShowDeleteModal(false)}
                        >
                            Annulla
                        </SecondaryButton>
                        <DangerButton onClick={deleteHousehold}>
                            Elimina Household
                        </DangerButton>
                    </div>
                </div>
            </Modal>

            {/* Modal Lascia */}
            <Modal show={showLeaveModal} onClose={() => setShowLeaveModal(false)}>
                <div className="p-6">
                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Sei sicuro di voler lasciare questa household?
                    </h2>

                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Non avrai più accesso ai dati di questa household. Per
                        rientrare dovrai essere invitato nuovamente.
                    </p>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton
                            onClick={() => setShowLeaveModal(false)}
                        >
                            Annulla
                        </SecondaryButton>
                        <DangerButton onClick={leaveHousehold}>
                            Lascia Household
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
