import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Household, HouseholdMember, PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { ChartBarIcon, TagIcon } from '@heroicons/react/24/outline';
import PageHeader from '@/Components/PageHeader';

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    invited_by: string;
    expires_at: string;
    created_at: string;
}

interface Props extends PageProps {
    household: Household;
    members: HouseholdMember[];
    pendingInvitations: PendingInvitation[];
}

export default function Show({ household, members, pendingInvitations }: Props) {
    const [showInviteModal, setShowInviteModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showLeaveModal, setShowLeaveModal] = useState(false);

    // Form per modifica nome
    const editForm = useForm({
        name: household.name,
        financial_management_type: household.financial_management_type || 'shared_wallet',
        balance_percentages: household.balance_percentages || {} as Record<string, number>,
        enable_turn_suggestions: household.enable_turn_suggestions || false,
        turn_suggestion_settings: household.turn_suggestion_settings || {} as Record<string, any>,
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

    const cancelInvitation = (invitationId: number) => {
        if (confirm('Sei sicuro di voler cancellare questo invito?')) {
            router.delete(
                route('households.cancel-invitation', [
                    household.id,
                    invitationId,
                ]),
            );
        }
    };

    const resendInvitation = (invitationId: number) => {
        router.post(
            route('households.resend-invitation', [household.id, invitationId]),
        );
    };

    const deleteHousehold = () => {
        router.delete(route('households.destroy', household.id));
    };

    const leaveHousehold = () => {
        router.post(route('households.leave', household.id));
    };

    // Calcola percentuali eque per tutti i membri
    const calculateEqualPercentages = () => {
        const memberCount = members.length;
        if (memberCount === 0) return {};

        const equalPercentage = Math.floor(100 / memberCount);
        const remainder = 100 - (equalPercentage * memberCount);

        const percentages: Record<string, number> = {};
        members.forEach((member, index) => {
            percentages[member.id] = index === 0 ? equalPercentage + remainder : equalPercentage;
        });

        return percentages;
    };

    // Imposta percentuali eque
    const setEqualPercentages = () => {
        editForm.setData('balance_percentages', calculateEqualPercentages());
    };

    // Aggiorna percentuale individuale
    const updateMemberPercentage = (memberId: number, percentage: string) => {
        const numPercentage = parseFloat(percentage) || 0;
        editForm.setData('balance_percentages', {
            ...editForm.data.balance_percentages,
            [memberId]: numPercentage
        });
    };

    // Calcola totale percentuali
    const getTotalPercentage = () => {
        return Object.values(editForm.data.balance_percentages).reduce((sum, perc) => sum + (perc || 0), 0);
    };

    // Verifica se percentuali sono valide
    const arePercentagesValid = () => {
        const total = getTotalPercentage();
        return Math.abs(total - 100) < 0.01;
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Household: ${household.name}`}
                />
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
                                    Modifica il nome e le impostazioni principali della tua household.
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

                                {/* Modalità di Gestione Finanziaria */}
                                <div>
                                    <InputLabel
                                        value="Modalità di gestione finanziaria"
                                        className="mb-3"
                                    />

                                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                                        <div className="flex items-center gap-3">
                                            <div className="flex-shrink-0">
                                                {editForm.data.financial_management_type === 'shared_wallet' ? (
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                                        <svg className="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
                                                        </svg>
                                                    </div>
                                                ) : (
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900">
                                                        <svg className="h-4 w-4 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                    {household.financial_management_type_label}
                                                </h3>
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    {editForm.data.financial_management_type === 'shared_wallet'
                                                        ? 'Tutti i membri condividono conti e spese completamente.'
                                                        : 'Ogni membro mantiene i propri conti. L\'app calcola i debiti per le spese condivise.'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {household.is_owner && (
                                        <div className="mt-4 space-y-3">
                                            <div
                                                className={`relative rounded-lg border-2 p-3 cursor-pointer transition-colors ${editForm.data.financial_management_type === 'shared_wallet'
                                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                                    }`}
                                                onClick={() => editForm.setData('financial_management_type', 'shared_wallet')}
                                            >
                                                <div className="flex items-start gap-3">
                                                    <input
                                                        type="radio"
                                                        name="financial_management_type"
                                                        value="shared_wallet"
                                                        checked={editForm.data.financial_management_type === 'shared_wallet'}
                                                        onChange={() => editForm.setData('financial_management_type', 'shared_wallet')}
                                                        className="mt-0.5 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                                    />
                                                    <div className="flex-1">
                                                        <h4 className="font-medium text-gray-900 dark:text-gray-100">
                                                            Portafoglio Comune
                                                        </h4>
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            Visione aggregata, budget condivisi, nessun calcolo di debiti interni.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                className={`relative rounded-lg border-2 p-3 cursor-pointer transition-colors ${editForm.data.financial_management_type === 'debt_balancing'
                                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                                    }`}
                                                onClick={() => editForm.setData('financial_management_type', 'debt_balancing')}
                                            >
                                                <div className="flex items-start gap-3">
                                                    <input
                                                        type="radio"
                                                        name="financial_management_type"
                                                        value="debt_balancing"
                                                        checked={editForm.data.financial_management_type === 'debt_balancing'}
                                                        onChange={() => editForm.setData('financial_management_type', 'debt_balancing')}
                                                        className="mt-0.5 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                                    />
                                                    <div className="flex-1">
                                                        <h4 className="font-medium text-gray-900 dark:text-gray-100">
                                                            Bilanciamento Debiti
                                                        </h4>
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            Conti individuali separati, calcolo automatico dei debiti.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Gestione Percentuali per Bilanciamento Debiti */}
                                    {household.is_owner && editForm.data.financial_management_type === 'debt_balancing' && (
                                        <div className="mt-6 rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20">
                                            <div className="flex items-center justify-between mb-4">
                                                <h4 className="text-sm font-medium text-orange-800 dark:text-orange-200">
                                                    Percentuali di bilanciamento
                                                </h4>
                                                <button
                                                    type="button"
                                                    onClick={setEqualPercentages}
                                                    className="text-xs px-2 py-1 rounded bg-orange-200 text-orange-800 hover:bg-orange-300 dark:bg-orange-800 dark:text-orange-200 dark:hover:bg-orange-700"
                                                >
                                                    Dividi equamente
                                                </button>
                                            </div>

                                            <div className="space-y-3">
                                                {members.map((member) => (
                                                    <div key={member.id} className="flex items-center gap-3 rounded-lg border border-orange-300 bg-white p-3 dark:border-orange-600 dark:bg-gray-800">
                                                        <div className="flex-1">
                                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                {member.name}
                                                            </p>
                                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                                {member.email}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                value={editForm.data.balance_percentages[member.id] || 0}
                                                                onChange={(e) => updateMemberPercentage(member.id, e.target.value)}
                                                                className="w-20 rounded border-gray-300 text-sm text-center dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                                            />
                                                            <span className="text-sm font-medium text-gray-600 dark:text-gray-400">%</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>

                                            <div className="mt-4 flex items-center justify-between">
                                                <div className={`text-sm ${arePercentagesValid()
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : 'text-red-600 dark:text-red-400'
                                                    }`}>
                                                    Totale: {getTotalPercentage().toFixed(1)}%
                                                    {arePercentagesValid() ? ' ✓' : ' (deve essere 100%)'}
                                                </div>

                                                {!arePercentagesValid() && (
                                                    <p className="text-xs text-red-600 dark:text-red-400">
                                                        Le percentuali devono sommare esattamente al 100%
                                                    </p>
                                                )}
                                            </div>

                                            <p className="mt-3 text-xs text-orange-600 dark:text-orange-400">
                                                💡 Queste percentuali determinano come vengono suddivise le spese condivise tra i membri.
                                            </p>
                                        </div>
                                    )}

                                    <InputError
                                        message={editForm.errors.financial_management_type}
                                        className="mt-2"
                                    />

                                    {household.is_owner && (
                                        <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                            ⚠️ Cambiare questa impostazione può influire sui calcoli e sui report esistenti.
                                        </p>
                                    )}
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

                    {/* Gestione Spese Fisse (solo per bilanciamento debiti) */}
                    {household.financial_management_type === 'debt_balancing' && (
                        <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8 dark:bg-gray-800">
                            <section>
                                <header>
                                    <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Spese Fisse
                                    </h2>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Gestisci le categorie di spese fisse e il suggeritore di turni per facilitare l'alternanza dei pagamenti.
                                    </p>
                                </header>

                                <div className="mt-6 space-y-6">
                                    {/* Suggeritore di Turni */}
                                    {/* {console.log(editForm.data)} */}
                                    {household.is_owner && (
                                        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center">
                                                    <input
                                                        type="checkbox"
                                                        id="enable_turn_suggestions"
                                                        checked={editForm.data.enable_turn_suggestions || false}
                                                        onChange={(e) => {

                                                            const newValue = e.target.checked;

                                                            // 1. Aggiorna lo stato per la UI (reazione visiva)
                                                            editForm.setData('enable_turn_suggestions', newValue);

                                                            // 2. Trasforma i dati "on-the-fly" per la richiesta
                                                            editForm.transform((data) => ({
                                                                ...data,
                                                                enable_turn_suggestions: newValue,
                                                            }));

                                                            // 3. Invia (TypeScript sarà felice perché non passi 'data' nelle opzioni)
                                                            editForm.patch(route('households.update', household.id), {
                                                                preserveScroll: true,
                                                                only: ['enable_turn_suggestions', 'turn_suggestion_settings'],
                                                            });

                                                            // editForm.setData('enable_turn_suggestions', e.target.checked);
                                                            // // Salvataggio automatico
                                                            // editForm.patch(route('households.update', household.id), {
                                                            //     preserveScroll: true,
                                                            //     only: ['enable_turn_suggestions', 'turn_suggestion_settings'],

                                                            // });
                                                        }}
                                                        disabled={editForm.processing}
                                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                                    />
                                                    <label
                                                        htmlFor="enable_turn_suggestions"
                                                        className="ml-2 block text-sm font-medium text-blue-900 dark:text-blue-100"
                                                    >
                                                        Abilita Suggeritore di Turni
                                                        {editForm.processing && (
                                                            <span className="ml-2 text-xs text-blue-600">
                                                                (salvando...)
                                                            </span>
                                                        )}
                                                    </label>
                                                </div>
                                            </div>
                                            <p className="mt-2 text-xs text-blue-700 dark:text-blue-200">
                                                Il sistema suggerirà automaticamente chi dovrebbe pagare la prossima spesa per ogni categoria fissa,
                                                alternando tra i membri della household.
                                            </p>
                                        </div>
                                    )}

                                    {/* Link alla Dashboard Spese Fisse */}
                                    <div className="flex flex-col gap-4 sm:flex-row">
                                        <Link
                                            href={route('fixed-expenses.dashboard', household.id)}
                                            className="inline-flex items-center justify-center gap-2 px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 transition duration-150 ease-in-out"
                                        >
                                            <ChartBarIcon className="h-4 w-4" />
                                            Visualizza Contributi Spese Fisse
                                        </Link>

                                        <Link
                                            href={'/categories'}
                                            className="inline-flex items-center justify-center gap-2 px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 transition duration-150 ease-in-out"
                                        >
                                            <TagIcon className="h-4 w-4" />
                                            Gestisci Categorie
                                        </Link>
                                    </div>

                                    {/* Info sui contributi */}
                                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                                        <h3 className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            Come funzionano le Spese Fisse
                                        </h3>
                                        <ul className="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                            <li>• Marca le categorie come "spese fisse" (affitto, bollette, etc.)</li>
                                            <li>• Il sistema traccia automaticamente i contributi di ogni membro</li>
                                            <li>• Visualizza chi deve a chi senza dover pareggiare ogni singolo pagamento</li>
                                            <li>• Il suggeritore di turni aiuta ad alternare i pagamenti tra i membri</li>
                                        </ul>
                                    </div>
                                </div>
                            </section>
                        </div>
                    )}

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
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${member.is_owner
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

                            {/* Inviti Pendenti */}
                            {household.is_owner &&
                                pendingInvitations.length > 0 && (
                                    <div className="mt-8">
                                        <h3 className="text-md font-medium text-gray-900 dark:text-gray-100">
                                            Inviti in attesa
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            Utenti invitati che non si sono
                                            ancora registrati.
                                        </p>
                                        <div className="mt-4 space-y-3">
                                            {pendingInvitations.map(
                                                (invitation) => (
                                                    <div
                                                        key={invitation.id}
                                                        className="flex items-center justify-between rounded-lg border border-dashed border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20"
                                                    >
                                                        <div>
                                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                {
                                                                    invitation.email
                                                                }
                                                            </p>
                                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                                Invitato da{' '}
                                                                {
                                                                    invitation.invited_by
                                                                }{' '}
                                                                • Scade il{' '}
                                                                {
                                                                    invitation.expires_at
                                                                }
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-3">
                                                            <span
                                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${invitation.role ===
                                                                    'guest'
                                                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                                    }`}
                                                            >
                                                                {invitation.role ===
                                                                    'guest'
                                                                    ? 'Ospite'
                                                                    : 'Membro'}
                                                            </span>
                                                            <span className="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                                In attesa
                                                            </span>
                                                            <button
                                                                onClick={() =>
                                                                    resendInvitation(
                                                                        invitation.id,
                                                                    )
                                                                }
                                                                className="text-sm text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                                                            >
                                                                Reinvia
                                                            </button>
                                                            <button
                                                                onClick={() =>
                                                                    cancelInvitation(
                                                                        invitation.id,
                                                                    )
                                                                }
                                                                className="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                            >
                                                                Annulla
                                                            </button>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
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
                        Inserisci l'email dell'utente che vuoi invitare. Se
                        l'utente è già registrato verrà aggiunto direttamente,
                        altrimenti riceverà un'email con un link per
                        registrarsi.
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
