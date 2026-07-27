import { Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
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
            <header className="hidden sm:block space-y-2">
                <SectionBadge
                    label="Profilazione"
                    icon={(
                        <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 2a1 1 0 01.894.553l1.382 2.8 3.089.449a1 1 0 01.554 1.706l-2.235 2.179.528 3.076a1 1 0 01-1.451 1.054L10 12.473l-2.761 1.452a1 1 0 01-1.451-1.054l.528-3.076L4.08 7.508a1 1 0 01.554-1.706l3.089-.449 1.382-2.8A1 1 0 0110 2z" />
                        </svg>
                    )}
                />
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Impostazioni di Profilazione
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Le tue preferenze per personalizzare l'interfaccia e i moduli
                    disponibili.
                </p>
            </header>

            <div className="mt-6 space-y-4">
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
