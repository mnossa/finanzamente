import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedSimpleLayout from '@/Layouts/AuthenticatedSimpleLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ProfileSettings {
    has_vat: boolean;
    family_status: 'single' | 'couple' | 'family';
    tracks_investments: boolean;
}

interface Props {
    currentSettings?: ProfileSettings;
}

export default function Show({ currentSettings }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        family_status: currentSettings?.family_status ?? ('single' as 'single' | 'couple' | 'family'),
        tracks_investments: currentSettings?.tracks_investments ?? false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('profile-quiz.store'));
    };

    return (
        <AuthenticatedSimpleLayout>
            <Head title="Configurazione Profilo" />

            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Configura il tuo profilo
                </h2>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Rispondi a queste 2 domande per personalizzare l'interfaccia
                    e abilitare solo i moduli di cui hai bisogno.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                {/* Domanda 1: Situazione familiare */}
                <div>
                    <InputLabel
                        value="1. Qual è la tua situazione familiare?"
                        className="mb-3 text-base font-semibold"
                    />

                    <div className="space-y-3">
                        <div
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.family_status === 'single'
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('family_status', 'single')}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="family_status"
                                    value="single"
                                    checked={data.family_status === 'single'}
                                    onChange={() =>
                                        setData('family_status', 'single')
                                    }
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                        Single / Vivo da solo
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Gestisco solo le mie finanze personali
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.family_status === 'couple'
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('family_status', 'couple')}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="family_status"
                                    value="couple"
                                    checked={data.family_status === 'couple'}
                                    onChange={() =>
                                        setData('family_status', 'couple')
                                    }
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                        In coppia / Convivente
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Gestisco le finanze insieme al partner
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.family_status === 'family'
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('family_status', 'family')}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="family_status"
                                    value="family"
                                    checked={data.family_status === 'family'}
                                    onChange={() =>
                                        setData('family_status', 'family')
                                    }
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                        Famiglia con figli
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Gestisco le finanze familiari con uno o più figli
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <InputError message={errors.family_status} className="mt-2" />
                </div>

                {/* Domanda 2: Investimenti */}
                <div>
                    <InputLabel
                        value="2. Gestisci investimenti?"
                        className="mb-3 text-base font-semibold"
                    />

                    <div className="space-y-3">
                        <div
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.tracks_investments === false
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('tracks_investments', false)}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="tracks_investments"
                                    value="false"
                                    checked={data.tracks_investments === false}
                                    onChange={() =>
                                        setData('tracks_investments', false)
                                    }
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                        No, non gestisco investimenti
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Mi concentro solo su entrate, uscite e risparmi
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.tracks_investments === true
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('tracks_investments', true)}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="tracks_investments"
                                    value="true"
                                    checked={data.tracks_investments === true}
                                    onChange={() =>
                                        setData('tracks_investments', true)
                                    }
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                        Sì, gestisco investimenti
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Verranno abilitati moduli per tracking azioni, ETF, crypto, ecc.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <InputError
                        message={errors.tracks_investments}
                        className="mt-2"
                    />
                </div>

                <div className="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <p className="text-sm text-blue-800 dark:text-blue-200">
                        💡 <strong>Nota:</strong> Potrai sempre modificare
                        queste impostazioni dal tuo profilo utente.
                    </p>
                </div>

                <div className="mt-6 flex justify-end">
                    <PrimaryButton disabled={processing}>
                        Salva e Continua
                    </PrimaryButton>
                </div>
            </form>
        </AuthenticatedSimpleLayout>
    );
}
