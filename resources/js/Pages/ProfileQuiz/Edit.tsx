import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { FormEventHandler } from 'react';

interface ProfileSettings {
    family_status: 'single' | 'couple' | 'family';
    tracks_investments: boolean;
}

interface Props {
    currentSettings?: ProfileSettings;
}

export default function Edit({ currentSettings }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        family_status: currentSettings?.family_status ?? ('single' as 'single' | 'couple' | 'family'),
        tracks_investments: currentSettings?.tracks_investments ?? false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.quiz-settings.update'));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Modifica Impostazioni Profilo" />

            <PageContent>
                <div className="mb-6">
                    <Link
                        href={route('profile.edit')}
                        className="inline-flex items-center text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                    >
                        <svg
                            className="mr-2 h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                        Torna al profilo
                    </Link>
                </div>

                <div className="overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-slate-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div className="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                        <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                            Modifica Impostazioni di Profilazione
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Aggiorna le tue preferenze per personalizzare l'interfaccia
                            e i moduli disponibili.
                        </p>
                    </div>

                    <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="p-6 space-y-4">
                        <div>
                            <InputLabel
                                value="1. Qual è la tua situazione familiare?"
                                className="mb-3 text-base font-semibold"
                            />

                            <div className="space-y-3">
                                {([
                                    {
                                        value: 'single' as const,
                                        title: 'Single / Vivo da solo',
                                        description: 'Gestisco solo le mie finanze personali',
                                    },
                                    {
                                        value: 'couple' as const,
                                        title: 'In coppia / Convivente',
                                        description: 'Gestisco le finanze insieme al partner',
                                    },
                                    {
                                        value: 'family' as const,
                                        title: 'Famiglia con figli',
                                        description: 'Gestisco le finanze familiari con uno o più figli',
                                    },
                                ]).map((option) => (
                                    <div
                                        key={option.value}
                                        className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                            data.family_status === option.value
                                                ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                        }`}
                                        onClick={() => setData('family_status', option.value)}
                                    >
                                        <div className="flex items-start gap-3">
                                            <input
                                                type="radio"
                                                name="family_status"
                                                value={option.value}
                                                checked={data.family_status === option.value}
                                                onChange={() => setData('family_status', option.value)}
                                                className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <div className="flex-1">
                                                <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                                    {option.title}
                                                </h3>
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                    {option.description}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <InputError
                                message={errors.family_status}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                value="2. Gestisci investimenti?"
                                className="mb-3 text-base font-semibold"
                            />

                            <div className="space-y-3">
                                {([
                                    {
                                        value: false,
                                        title: 'No, non gestisco investimenti',
                                        description: 'Mi concentro solo su entrate, uscite e risparmi',
                                    },
                                    {
                                        value: true,
                                        title: 'Sì, gestisco investimenti',
                                        description: 'Verranno abilitati moduli per tracking azioni, ETF, crypto, ecc.',
                                    },
                                ]).map((option) => (
                                    <div
                                        key={String(option.value)}
                                        className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                            data.tracks_investments === option.value
                                                ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                        }`}
                                        onClick={() => setData('tracks_investments', option.value)}
                                    >
                                        <div className="flex items-start gap-3">
                                            <input
                                                type="radio"
                                                name="tracks_investments"
                                                value={String(option.value)}
                                                checked={data.tracks_investments === option.value}
                                                onChange={() => setData('tracks_investments', option.value)}
                                                className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <div className="flex-1">
                                                <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                                    {option.title}
                                                </h3>
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                    {option.description}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <InputError
                                message={errors.tracks_investments}
                                className="mt-2"
                            />
                        </div>

                        <div className="flex items-center justify-end gap-4 pt-4">
                            <Link href={route('profile.edit')}>
                                <SecondaryButton type="button">
                                    Annulla
                                </SecondaryButton>
                            </Link>
                            <PrimaryButton disabled={processing}>
                                Salva Modifiche
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
