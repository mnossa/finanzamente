import { Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import FormActionsBar from '@/Components/FormActionsBar';
import SecondaryButton from '@/Components/SecondaryButton';

interface Props {
    className?: string;
}

export default function ProfileQuizSettingsCard({ className = '' }: Props) {
    const { auth } = usePage<PageProps>().props;
    const profileSettings = auth.user.profile_settings;

    const familyStatusLabels = {
        single: 'Single / Vivo da solo',
        couple: 'In coppia / Convivente',
        family: 'Famiglia con figli',
    };

    return (
        <section className={className}>
            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                Quiz profilo
            </h2>
            <div className="space-y-4">
                {profileSettings ? (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Situazione
                                </div>
                                <div className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {familyStatusLabels[
                                        profileSettings.family_status as keyof typeof familyStatusLabels
                                    ] || profileSettings.family_status}
                                </div>
                            </div>

                            <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                <div className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Investimenti
                                </div>
                                <div className="mt-2 flex items-center gap-2">
                                    <span
                                        className={`inline-flex h-6 w-6 items-center justify-center rounded-full ${
                                            profileSettings.tracks_investments
                                                ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900 dark:text-emerald-400'
                                                : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                        }`}
                                    >
                                        {profileSettings.tracks_investments ? '✓' : '✗'}
                                    </span>
                                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {profileSettings.tracks_investments ? 'Sì' : 'No'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                💡 Le impostazioni influenzano i moduli disponibili
                                nella dashboard.
                            </p>
                        </div>
                    </>
                ) : (
                    <div className="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <p className="text-sm text-yellow-800 dark:text-yellow-200">
                            ⚠️ Non hai ancora completato il quiz di profilazione.
                        </p>
                    </div>
                )}

                <FormActionsBar sticky={false}>
                    <Link href={route('profile.quiz-settings.edit')}>
                        <SecondaryButton>Modifica Impostazioni</SecondaryButton>
                    </Link>
                </FormActionsBar>
            </div>
        </section>
    );
}
