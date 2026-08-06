import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { wizardSteps } from '@/utils/guidedCreate';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';

const STEP_COUNT = 4;
const PRESET_COLORS = [
    '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#14b8a6', '#06b6d4',
    '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e',
];

export default function TagCreateGuided() {
    const [step, setStep] = useState(0);
    const { data, setData, post, processing, errors } = useForm({ name: '', color: '#6366f1' });

    const meta = [
        { title: 'Come si chiama il tag?', subtitle: 'Es. Vacanze, Regalo, Lavoro' },
        { title: 'Scegli un colore', subtitle: 'Ti aiuta a riconoscerlo in lista.' },
        { title: 'Anteprima', subtitle: 'Controlla come apparirà.' },
        { title: 'Conferma', subtitle: 'Tutto ok? Crea il tag.' },
    ][step];

    const canNext = step !== 0 || data.name.trim().length > 0;

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(e) => {
                e.preventDefault();
                if (step < STEP_COUNT - 1 && canNext) {
                    setStep((s) => s + 1);
                } else if (step === STEP_COUNT - 1) {
                    post(route('tags.store'));
                }
            }}
        >
            <GuidedFormWizard steps={wizardSteps(STEP_COUNT)} currentStep={step} title={meta.title} subtitle={meta.subtitle}>
                {step === 0 && (
                    <div>
                        <InputLabel htmlFor="name" value="Nome" />
                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                )}
                {step === 1 && (
                    <div>
                        <div className="flex flex-wrap gap-2">
                            {PRESET_COLORS.map((color) => (
                                <button
                                    key={color}
                                    type="button"
                                    onClick={() => setData('color', color)}
                                    className={clsx(
                                        'h-8 w-8 rounded-full',
                                        data.color === color && 'ring-2 ring-offset-2 ring-gray-900 dark:ring-white',
                                    )}
                                    style={{ backgroundColor: color }}
                                    aria-label={`Colore ${color}`}
                                />
                            ))}
                        </div>
                        <input
                            type="color"
                            value={data.color}
                            onChange={(e) => setData('color', e.target.value)}
                            className="mt-3 h-10 w-10 cursor-pointer rounded"
                        />
                        <InputError message={errors.color} className="mt-2" />
                    </div>
                )}
                {step === 2 && (
                    <span
                        className="inline-block rounded-full px-4 py-2 text-sm font-medium text-white"
                        style={{ backgroundColor: data.color }}
                    >
                        {data.name || 'Nome tag'}
                    </span>
                )}
                {step === 3 && (
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Nome</dt>
                            <dd>{data.name}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-500">Colore</dt>
                            <dd>
                                <span className="inline-block h-4 w-4 rounded-full" style={{ backgroundColor: data.color }} />
                            </dd>
                        </div>
                    </dl>
                )}
                <GuidedFormStepActions
                    step={step}
                    totalSteps={STEP_COUNT}
                    processing={processing}
                    canNext={canNext}
                    onBack={() => setStep((s) => Math.max(0, s - 1))}
                    cancelHref={route('tags.index')}
                    submitLabel="Crea tag"
                />
            </GuidedFormWizard>
        </form>
    );
}
