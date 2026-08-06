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

interface Category {
    id: number;
    name: string;
    icon: string | null;
}

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Props {
    categories: Category[];
    currencies: Currency[];
}

const STEP_COUNT = 5;

export default function BudgetCreateGuided({ categories, currencies }: Props) {
    const [step, setStep] = useState(0);
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const { data, setData, post, processing, errors } = useForm({
        category_id: '',
        amount: '',
        currency_code: 'EUR',
        period_start: firstDay.toISOString().split('T')[0],
        period_end: lastDay.toISOString().split('T')[0],
        description: '',
    });

    const selectedCategory = useMemo(
        () => categories.find((cat) => String(cat.id) === data.category_id),
        [categories, data.category_id],
    );
    const selectedCurrency = useMemo(
        () => currencies.find((curr) => curr.code === data.currency_code),
        [currencies, data.currency_code],
    );

    const setCurrentMonth = () => {
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        setData('period_start', first.toISOString().split('T')[0]);
        setData('period_end', last.toISOString().split('T')[0]);
    };

    const setNextMonth = () => {
        const first = new Date(now.getFullYear(), now.getMonth() + 1, 1);
        const last = new Date(now.getFullYear(), now.getMonth() + 2, 0);
        setData('period_start', first.toISOString().split('T')[0]);
        setData('period_end', last.toISOString().split('T')[0]);
    };

    const setCurrentYear = () => {
        const first = new Date(now.getFullYear(), 0, 1);
        const last = new Date(now.getFullYear(), 11, 31);
        setData('period_start', first.toISOString().split('T')[0]);
        setData('period_end', last.toISOString().split('T')[0]);
    };

    const canNext = (): boolean => {
        if (step === 0) return data.category_id !== '';
        if (step === 1) return data.amount !== '' && Number(data.amount) > 0 && data.currency_code !== '';
        if (step === 2) return Boolean(data.period_start) && Boolean(data.period_end);
        return true;
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) {
            setStep((s) => s + 1);
        }
    };

    const stepMeta = [
        { title: 'Scegli la categoria', subtitle: 'Quale area vuoi pianificare?' },
        { title: 'Importo e valuta', subtitle: 'Definisci il limite del budget.' },
        { title: 'Periodo budget', subtitle: 'Usa i preset per compilare in un tap.' },
        { title: 'Note', subtitle: 'Opzionale - puoi saltare.' },
        { title: 'Conferma', subtitle: 'Controlla i dati prima di creare.' },
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

                post(route('budgets.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={stepMeta.title}
                subtitle={stepMeta.subtitle}
            >
                {step === 0 && (
                    <div className="space-y-2">
                        {categories.map((cat) => (
                            <button
                                key={cat.id}
                                type="button"
                                onClick={() => setData('category_id', String(cat.id))}
                                className={clsx(
                                    'w-full rounded-xl border-2 px-4 py-3 text-left',
                                    data.category_id === String(cat.id)
                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-gray-200 dark:border-gray-700',
                                )}
                            >
                                <span className="mr-2">{cat.icon || '📁'}</span>
                                <span className="font-medium">{cat.name}</span>
                            </button>
                        ))}
                        <InputError message={errors.category_id} className="mt-2" />
                    </div>
                )}

                {step === 1 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="amount" value="Importo" />
                            <TextInput
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                className="mt-1 block w-full"
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

                {step === 2 && (
                    <div className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={setCurrentMonth}
                                className="rounded-md bg-gray-100 px-3 py-1.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Mese corrente
                            </button>
                            <button
                                type="button"
                                onClick={setNextMonth}
                                className="rounded-md bg-gray-100 px-3 py-1.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Prossimo mese
                            </button>
                            <button
                                type="button"
                                onClick={setCurrentYear}
                                className="rounded-md bg-gray-100 px-3 py-1.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                            >
                                Anno corrente
                            </button>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="period_start" value="Data inizio" />
                                <TextInput
                                    id="period_start"
                                    type="date"
                                    value={data.period_start}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('period_start', e.target.value)}
                                />
                                <InputError message={errors.period_start} className="mt-2" />
                            </div>
                            <div>
                                <InputLabel htmlFor="period_end" value="Data fine" />
                                <TextInput
                                    id="period_end"
                                    type="date"
                                    value={data.period_end}
                                    className="mt-1 block w-full"
                                    onChange={(e) => setData('period_end', e.target.value)}
                                />
                                <InputError message={errors.period_end} className="mt-2" />
                            </div>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <InputLabel htmlFor="description" value="Note (opzionale)" />
                        <textarea
                            id="description"
                            rows={3}
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Note opzionali sul budget..."
                        />
                        <InputError message={errors.description} className="mt-2" />
                    </div>
                )}

                {step === 4 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Categoria</dt>
                            <dd className="text-right font-medium">
                                {selectedCategory ? `${selectedCategory.icon || '📁'} ${selectedCategory.name}` : '-'}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Importo</dt>
                            <dd className="text-right font-medium">
                                {data.amount || '0'} {selectedCurrency?.symbol || data.currency_code}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Inizio</dt>
                            <dd>{formatItalianDate(data.period_start)}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Fine</dt>
                            <dd>{formatItalianDate(data.period_end)}</dd>
                        </div>
                        {data.description && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Note</dt>
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
                    cancelHref={route('budgets.index')}
                    submitLabel="Crea budget"
                />
            </GuidedFormWizard>
        </form>
    );
}
