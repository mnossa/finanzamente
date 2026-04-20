import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import clsx from 'clsx';

interface ProfileSettings {
    has_vat: boolean;
    family_status: 'single' | 'couple' | 'family';
    tracks_investments: boolean;
    revenue_threshold?: number;
    revenue_tracking_enabled?: boolean;
    tax_rate?: number;
    inps_rate?: number;
    budget503020_targets?: {
        necessity: number;
        extra: number;
        investment: number;
    };
}

interface Props {
    currentSettings?: ProfileSettings;
}

const REVENUE_PRESETS = [
    { label: '€85.000 (Forfettario)', value: 85000 },
    { label: '€100.000', value: 100000 },
    { label: '€400.000', value: 400000 },
];

export default function Edit({ currentSettings }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        has_vat: currentSettings?.has_vat ?? false,
        family_status: currentSettings?.family_status ?? ('single' as 'single' | 'couple' | 'family'),
        tracks_investments: currentSettings?.tracks_investments ?? false,
        revenue_threshold: currentSettings?.revenue_threshold ?? 85000,
        revenue_tracking_enabled: currentSettings?.revenue_tracking_enabled ?? true,
        tax_rate: currentSettings?.tax_rate ?? 15,
        inps_rate: currentSettings?.inps_rate ?? 26.23,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.quiz-settings.update'));
    };

    const isPreset = REVENUE_PRESETS.some((p) => p.value === data.revenue_threshold);

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

                    <form onSubmit={submit} className="p-6 space-y-6">
                        {/* Domanda 1: Hai una Partita IVA? */}
                        <div>
                            <InputLabel
                                value="1. Hai una Partita IVA?"
                                className="mb-3 text-base font-semibold"
                            />

                            <div className="space-y-3">
                                <div
                                    className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                        data.has_vat === false
                                            ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                            : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                    }`}
                                    onClick={() => setData('has_vat', false)}
                                >
                                    <div className="flex items-start gap-3">
                                        <input
                                            type="radio"
                                            name="has_vat"
                                            value="false"
                                            checked={data.has_vat === false}
                                            onChange={() => setData('has_vat', false)}
                                            className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <div className="flex-1">
                                            <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                                No, sono un privato
                                            </h3>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                Gestisco solo finanze personali o familiari
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                        data.has_vat === true
                                            ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                            : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                    }`}
                                    onClick={() => setData('has_vat', true)}
                                >
                                    <div className="flex items-start gap-3">
                                        <input
                                            type="radio"
                                            name="has_vat"
                                            value="true"
                                            checked={data.has_vat === true}
                                            onChange={() => setData('has_vat', true)}
                                            className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <div className="flex-1">
                                            <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                                Sì, ho una Partita IVA
                                            </h3>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                Verranno abilitati moduli per gestione IVA e
                                                detrazioni fiscali
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <InputError message={errors.has_vat} className="mt-2" />
                        </div>

                        {/* Monitoraggio Fatturato (visibile solo se has_vat === true) */}
                        {data.has_vat && (
                            <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                                <InputLabel
                                    value="Monitoraggio Fatturato Annuo"
                                    className="mb-3 text-base font-semibold text-emerald-800 dark:text-emerald-300"
                                />
                                <p className="mb-4 text-sm text-emerald-700 dark:text-emerald-400">
                                    Configura il monitoraggio del fatturato per tenere sotto controllo la soglia del regime forfettario.
                                </p>

                                {/* Toggle abilitazione */}
                                <div className="mb-4 flex items-center justify-between">
                                    <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        Abilita monitoraggio fatturato
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setData('revenue_tracking_enabled', !data.revenue_tracking_enabled)}
                                        aria-label='Abilita o disabilita il monitoraggio del fatturato'
                                        className={clsx(
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                                            data.revenue_tracking_enabled
                                                ? 'bg-emerald-500'
                                                : 'bg-gray-300 dark:bg-gray-600'
                                        )}
                                    >
                                        <span
                                            className={clsx(
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                data.revenue_tracking_enabled ? 'translate-x-6' : 'translate-x-1'
                                            )}
                                        />
                                    </button>
                                </div>

                                {/* Soglia predefinita */}
                                <div>
                                    <InputLabel value="Soglia di fatturato annuo" className="mb-2 text-sm" />
                                    <div className="mb-2 flex flex-wrap gap-2">
                                        {REVENUE_PRESETS.map((preset) => (
                                            <button
                                                key={preset.value}
                                                type="button"
                                                onClick={() => setData('revenue_threshold', preset.value)}
                                                className={clsx(
                                                    'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                                    data.revenue_threshold === preset.value
                                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                                        : 'border-gray-300 bg-white text-gray-700 hover:border-emerald-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                                )}
                                            >
                                                {preset.label}
                                            </button>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (isPreset) setData('revenue_threshold', 0);
                                            }}
                                            className={clsx(
                                                'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                                !isPreset
                                                    ? 'border-emerald-500 bg-emerald-500 text-white'
                                                    : 'border-gray-300 bg-white text-gray-700 hover:border-emerald-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                            )}
                                        >
                                            Personalizzato
                                        </button>
                                    </div>
                                    {!isPreset && (
                                        <input
                                            type="number"
                                            min={1}
                                            max={10000000}
                                            value={data.revenue_threshold || ''}
                                            onChange={(e) => setData('revenue_threshold', Number(e.target.value))}
                                            placeholder="Inserisci soglia personalizzata (€)"
                                            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                    )}
                                    <InputError message={errors.revenue_threshold} className="mt-1" />
                                </div>

                                {/* Aliquote fiscali */}
                                <div className="mt-4 space-y-3 border-t border-emerald-200 pt-4 dark:border-emerald-700">
                                    <InputLabel
                                        value="Aliquote Fiscali per Termometro Tasse"
                                        className="mb-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"
                                    />
                                    <p className="mb-3 text-xs text-emerald-700 dark:text-emerald-400">
                                        Imposta le tue aliquote per il calcolo automatico dell'accantonamento fiscale
                                    </p>

                                    {/* Imposta Sostitutiva */}
                                    <div>
                                        <InputLabel value="Aliquota Imposta Sostitutiva (%)" className="mb-1 text-sm" />
                                        <input
                                            type="number"
                                            min={0}
                                            max={100}
                                            step={0.1}
                                            value={data.tax_rate || ''}
                                            onChange={(e) => setData('tax_rate', Number(e.target.value))}
                                            placeholder="15"
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            Default: 15% (regime forfettario) o 5% (startup)
                                        </p>
                                        <InputError message={errors.tax_rate} className="mt-1" />
                                    </div>

                                    {/* Contributi INPS */}
                                    <div>
                                        <InputLabel value="Aliquota Contributi INPS (%)" className="mb-1 text-sm" />
                                        <input
                                            type="number"
                                            min={0}
                                            max={100}
                                            step={0.01}
                                            value={data.inps_rate || ''}
                                            onChange={(e) => setData('inps_rate', Number(e.target.value))}
                                            placeholder="26.23"
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        />
                                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                            Default: 26.23% (gestione separata INPS)
                                        </p>
                                        <InputError message={errors.inps_rate} className="mt-1" />
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Domanda 2: Situazione familiare */}
                        <div>
                            <InputLabel
                                value="2. Qual è la tua situazione familiare?"
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

                            <InputError
                                message={errors.family_status}
                                className="mt-2"
                            />
                        </div>

                        {/* Domanda 3: Investimenti */}
                        <div>
                            <InputLabel
                                value="3. Gestisci investimenti?"
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
                                                Verranno abilitati moduli per tracking azioni,
                                                ETF, crypto, ecc.
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
