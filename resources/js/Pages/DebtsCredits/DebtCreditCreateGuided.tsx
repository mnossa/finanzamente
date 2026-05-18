import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { debts } from '@/utils/analytics';
import { formatCurrency } from '@/utils/format';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';

const STEP_COUNT = 6;

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Props {
    currencies: Currency[];
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-gray-500">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}

export default function DebtCreditCreateGuided({ currencies }: Props) {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        counterparty: '',
        amount: '',
        currency_code: 'EUR',
        type: 'debt' as 'debt' | 'credit',
        due_date: '',
        description: '',
    });

    const stepMeta = [
        { title: 'Debito o credito?', subtitle: 'Indica se devi soldi o te ne devono.' },
        { title: 'Chi è la controparte?', subtitle: 'Nome della persona o entità.' },
        { title: 'Quanto?', subtitle: 'Importo e valuta della posizione.' },
        { title: 'Scadenza', subtitle: 'Opzionale — puoi saltare.' },
        { title: 'Note', subtitle: 'Opzionale — puoi saltare.' },
        { title: 'Conferma', subtitle: 'Controlla e salva.' },
    ][step];

    const canNext = (): boolean => {
        switch (step) {
            case 1:
                return data.counterparty.trim().length > 0;
            case 2:
                return data.amount !== '' && Number(data.amount) > 0;
            default:
                return true;
        }
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) {
            setStep((s) => s + 1);
        }
    };

    const goBack = () => setStep((s) => Math.max(0, s - 1));
    const typeLabel = data.type === 'debt' ? 'Debito' : 'Credito';

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(e) => {
                e.preventDefault();
                if (step < STEP_COUNT - 1) {
                    goNext();
                } else {
                    post(route('debts-credits.store'), {
                        onSuccess: () => debts.created(data.type),
                    });
                }
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={stepMeta.title}
                subtitle={stepMeta.subtitle}
            >
                {step === 0 && (
                    <div className="grid grid-cols-2 gap-3">
                        {(
                            [
                                { value: 'debt' as const, label: 'Debito', icon: '📤', hint: 'Soldi che devi' },
                                { value: 'credit' as const, label: 'Credito', icon: '📥', hint: 'Soldi che ti devono' },
                            ] as const
                        ).map((opt) => (
                            <button
                                key={opt.value}
                                type="button"
                                onClick={() => setData('type', opt.value)}
                                className={clsx(
                                    'flex flex-col items-center rounded-xl border-2 p-4',
                                    data.type === opt.value
                                        ? opt.value === 'debt'
                                            ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                            : 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-gray-200 dark:border-gray-600',
                                )}
                            >
                                <span className="text-3xl">{opt.icon}</span>
                                <span className="mt-2 font-medium">{opt.label}</span>
                                <span className="mt-1 text-xs text-gray-500">{opt.hint}</span>
                            </button>
                        ))}
                        <InputError message={errors.type} className="col-span-2" />
                    </div>
                )}

                {step === 1 && (
                    <div>
                        <InputLabel htmlFor="counterparty" value="Controparte" />
                        <TextInput
                            id="counterparty"
                            className="mt-1 block w-full"
                            value={data.counterparty}
                            onChange={(e) => setData('counterparty', e.target.value)}
                            placeholder={data.type === 'debt' ? 'A chi devi i soldi?' : 'Chi ti deve i soldi?'}
                            autoFocus
                        />
                        <InputError message={errors.counterparty} className="mt-2" />
                    </div>
                )}

                {step === 2 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="amount" value="Importo" />
                            <TextInput
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="mt-1 block w-full"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                autoFocus
                            />
                            <InputError message={errors.amount} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="currency_code" value="Valuta" />
                            <select
                                id="currency_code"
                                value={data.currency_code}
                                onChange={(e) => setData('currency_code', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {currencies.map((curr) => (
                                    <option key={curr.code} value={curr.code}>
                                        {curr.symbol} - {curr.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.currency_code} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <InputLabel htmlFor="due_date" value="Data di scadenza" />
                        <TextInput
                            id="due_date"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.due_date}
                            onChange={(e) => setData('due_date', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-gray-500">Riceverai un avviso alla scadenza.</p>
                        <InputError message={errors.due_date} className="mt-2" />
                    </div>
                )}

                {step === 4 && (
                    <div>
                        <InputLabel htmlFor="description" value="Note" />
                        <textarea
                            id="description"
                            rows={3}
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Motivo del debito/credito..."
                        />
                        <InputError message={errors.description} className="mt-2" />
                    </div>
                )}

                {step === 5 && (
                    <dl className="space-y-3 text-sm">
                        <SummaryRow label="Tipo" value={typeLabel} />
                        <SummaryRow label="Controparte" value={data.counterparty} />
                        <SummaryRow
                            label="Importo"
                            value={formatCurrency(Number(data.amount), data.currency_code)}
                        />
                        {data.due_date && <SummaryRow label="Scadenza" value={formatItalianDate(data.due_date)} />}
                        {data.description && <SummaryRow label="Note" value={data.description} />}
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={STEP_COUNT}
                    processing={processing}
                    canNext={canNext()}
                    onBack={goBack}
                    cancelHref={route('debts-credits.index')}
                    submitLabel={data.type === 'debt' ? 'Aggiungi debito' : 'Aggiungi credito'}
                    onSkip={step === 3 || step === 4 ? goNext : undefined}
                />
            </GuidedFormWizard>
        </form>
    );
}
