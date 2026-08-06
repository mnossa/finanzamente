import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { wizardSteps } from '@/utils/guidedCreate';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

const STEP_COUNT = 4;

export default function HouseholdCreateGuided() {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        financial_management_type: 'shared_wallet',
        balance_type: 'equal',
    });

    const effectiveSteps = useMemo(() => {
        if (data.financial_management_type === 'shared_wallet') {
            return [0, 1, 3];
        }
        return [0, 1, 2, 3];
    }, [data.financial_management_type]);
    const visualStep = effectiveSteps[step] ?? 0;
    const visualCount = effectiveSteps.length;

    const canNext = (): boolean => {
        if (visualStep === 0) return data.name.trim().length > 0;
        return true;
    };

    const goNext = () => {
        if (step < visualCount - 1 && canNext()) {
            setStep((s) => s + 1);
        }
    };

    const goBack = () => setStep((s) => Math.max(0, s - 1));

    const stepTitles: Record<number, { title: string; subtitle: string }> = {
        0: { title: 'Nome household', subtitle: 'Es. Casa Rossi o Personale.' },
        1: { title: 'Gestione finanze', subtitle: 'Scegli il modello più adatto.' },
        2: { title: 'Bilanciamento debiti', subtitle: 'Divisione equa o personalizzata.' },
        3: { title: 'Conferma', subtitle: 'Controlla e crea la household.' },
    };
    const titles = stepTitles[visualStep] ?? stepTitles[0];

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(event) => {
                event.preventDefault();
                if (step < visualCount - 1) {
                    goNext();
                    return;
                }
                post(route('households.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(visualCount)}
                currentStep={step}
                title={titles.title}
                subtitle={titles.subtitle}
            >
                {visualStep === 0 && (
                    <div>
                        <InputLabel htmlFor="name" value="Nome della household" />
                        <TextInput
                            id="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Es: Casa Rossi, Famiglia, Personale..."
                            autoFocus
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                )}

                {visualStep === 1 && (
                    <div className="space-y-3">
                        <button
                            type="button"
                            onClick={() => setData('financial_management_type', 'shared_wallet')}
                            className={clsx(
                                'w-full rounded-xl border-2 p-4 text-left',
                                data.financial_management_type === 'shared_wallet'
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                    : 'border-gray-200 dark:border-gray-700',
                            )}
                        >
                            <div className="font-medium">Portafoglio Comune</div>
                            <div className="mt-1 text-sm text-gray-500">Conti condivisi, nessun calcolo debiti interni.</div>
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('financial_management_type', 'debt_balancing')}
                            className={clsx(
                                'w-full rounded-xl border-2 p-4 text-left',
                                data.financial_management_type === 'debt_balancing'
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                    : 'border-gray-200 dark:border-gray-700',
                            )}
                        >
                            <div className="font-medium">Bilanciamento Debiti</div>
                            <div className="mt-1 text-sm text-gray-500">Conti personali separati con calcolo automatico debiti.</div>
                        </button>
                        <InputError message={errors.financial_management_type} className="mt-2" />
                    </div>
                )}

                {visualStep === 2 && (
                    <div className="space-y-3">
                        <InputLabel value="Modalità di divisione" />
                        <button
                            type="button"
                            onClick={() => setData('balance_type', 'equal')}
                            className={clsx(
                                'w-full rounded-xl border-2 p-4 text-left',
                                data.balance_type === 'equal'
                                    ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20'
                                    : 'border-gray-200 dark:border-gray-700',
                            )}
                        >
                            <div className="font-medium">Divisione equa automatica</div>
                            <div className="mt-1 text-sm text-gray-500">Le spese condivise sono sempre divise in parti uguali.</div>
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('balance_type', 'custom')}
                            className={clsx(
                                'w-full rounded-xl border-2 p-4 text-left',
                                data.balance_type === 'custom'
                                    ? 'border-orange-400 bg-orange-50 dark:bg-orange-900/20'
                                    : 'border-gray-200 dark:border-gray-700',
                            )}
                        >
                            <div className="font-medium">Percentuali personalizzate</div>
                            <div className="mt-1 text-sm text-gray-500">Configuri quote diverse per i membri (es. 30/70).</div>
                        </button>
                        <InputError message={errors.balance_type} className="mt-2" />
                    </div>
                )}

                {visualStep === 3 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Nome</dt>
                            <dd className="font-medium">{data.name}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Gestione</dt>
                            <dd>{data.financial_management_type === 'shared_wallet' ? 'Portafoglio Comune' : 'Bilanciamento Debiti'}</dd>
                        </div>
                        {data.financial_management_type === 'debt_balancing' && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Divisione</dt>
                                <dd>{data.balance_type === 'equal' ? 'Equa automatica' : 'Percentuali personalizzate'}</dd>
                            </div>
                        )}
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={visualCount}
                    processing={processing}
                    canNext={canNext()}
                    onBack={goBack}
                    cancelHref={route('households.index')}
                    submitLabel="Crea household"
                />
            </GuidedFormWizard>
        </form>
    );
}
