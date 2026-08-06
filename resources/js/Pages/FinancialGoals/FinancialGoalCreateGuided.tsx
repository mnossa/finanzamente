import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Props {
    currencies: Currency[];
    suggestedIcons: string[];
}

const STEP_COUNT = 5;
const COLOR_PRESETS = ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6'];

export default function FinancialGoalCreateGuided({ currencies, suggestedIcons }: Props) {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        target_amount: '',
        target_date: '',
        currency_code: 'EUR',
        icon: '🎯',
        color: '#6366f1',
    });

    const selectedCurrency = useMemo(
        () => currencies.find((currency) => currency.code === data.currency_code),
        [currencies, data.currency_code],
    );

    const canNext = (): boolean => {
        if (step === 0) return data.name.trim().length > 0;
        if (step === 3) return data.target_amount !== '' && Number(data.target_amount) > 0;
        return true;
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) setStep((s) => s + 1);
    };

    const meta = [
        { title: 'Nome obiettivo', subtitle: 'Quale traguardo vuoi raggiungere?' },
        { title: 'Scegli un’icona', subtitle: 'Riconosci subito il tuo obiettivo.' },
        { title: 'Scegli un colore', subtitle: 'Personalizza il look del goal.' },
        { title: 'Target economico', subtitle: 'Importo obbligatorio, data opzionale.' },
        { title: 'Conferma', subtitle: 'Verifica i dati prima di creare.' },
    ][step];

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(event) => {
                event.preventDefault();
                if (step < STEP_COUNT - 1) {
                    goNext();
                    return;
                }
                post(route('financial-goals.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={meta.title}
                subtitle={meta.subtitle}
            >
                {step === 0 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="name" value="Nome obiettivo" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoFocus
                                placeholder="Es. Fondo emergenza, Vacanza estiva..."
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                            <textarea
                                id="description"
                                rows={3}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                            <InputError message={errors.description} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 1 && (
                    <div className="grid grid-cols-6 gap-2">
                        {suggestedIcons.map((icon) => (
                            <button
                                key={icon}
                                type="button"
                                onClick={() => setData('icon', icon)}
                                className={clsx(
                                    'flex h-10 w-10 items-center justify-center rounded-lg text-2xl',
                                    data.icon === icon
                                        ? 'bg-emerald-100 ring-2 ring-emerald-500 dark:bg-emerald-900/30'
                                        : 'bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700',
                                )}
                            >
                                {icon}
                            </button>
                        ))}
                        <InputError message={errors.icon} className="col-span-6 mt-2" />
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <div className="flex flex-wrap gap-2">
                            {COLOR_PRESETS.map((color) => (
                                <button
                                    key={color}
                                    type="button"
                                    onClick={() => setData('color', color)}
                                    className={clsx(
                                        'h-8 w-8 rounded-full',
                                        data.color === color && 'ring-2 ring-gray-400 ring-offset-2',
                                    )}
                                    style={{ backgroundColor: color }}
                                />
                            ))}
                            <input
                                type="color"
                                value={data.color}
                                onChange={(e) => setData('color', e.target.value)}
                                className="h-8 w-8 cursor-pointer rounded"
                            />
                        </div>
                        <InputError message={errors.color} className="mt-2" />
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="target_amount" value="Importo obiettivo" />
                                <TextInput
                                    id="target_amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    className="mt-1 block w-full"
                                    value={data.target_amount}
                                    onChange={(e) => setData('target_amount', e.target.value)}
                                    autoFocus
                                />
                                <InputError message={errors.target_amount} className="mt-2" />
                            </div>
                            <div>
                                <InputLabel htmlFor="currency_code" value="Valuta" />
                                <select
                                    id="currency_code"
                                    className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={data.currency_code}
                                    onChange={(e) => setData('currency_code', e.target.value)}
                                >
                                    {currencies.map((currency) => (
                                        <option key={currency.code} value={currency.code}>
                                            {currency.symbol} - {currency.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.currency_code} className="mt-2" />
                            </div>
                        </div>
                        <div>
                            <InputLabel htmlFor="target_date" value="Data obiettivo (opzionale)" />
                            <TextInput
                                id="target_date"
                                type="date"
                                className="mt-1 block w-full"
                                value={data.target_date}
                                onChange={(e) => setData('target_date', e.target.value)}
                            />
                            <InputError message={errors.target_date} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 4 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Obiettivo</dt>
                            <dd className="text-right font-medium">{data.icon} {data.name}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Colore</dt>
                            <dd>
                                <span className="inline-block h-4 w-4 rounded-full" style={{ backgroundColor: data.color }} />
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Target</dt>
                            <dd className="font-medium">
                                {data.target_amount || '0'} {selectedCurrency?.symbol || data.currency_code}
                            </dd>
                        </div>
                        {data.target_date && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Scadenza</dt>
                                <dd>{formatItalianDate(data.target_date)}</dd>
                            </div>
                        )}
                        {data.description && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Descrizione</dt>
                                <dd className="text-right">{data.description}</dd>
                            </div>
                        )}
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={STEP_COUNT}
                    processing={processing}
                    canNext={canNext()}
                    onBack={() => setStep((s) => Math.max(0, s - 1))}
                    onSkip={step === 3 ? goNext : undefined}
                    cancelHref={route('financial-goals.index')}
                    submitLabel="Crea obiettivo"
                />
            </GuidedFormWizard>
        </form>
    );
}
