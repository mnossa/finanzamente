import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import EmojiPicker from '@/Components/EmojiPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';
import { cats } from '@/utils/analytics';

const STEP_COUNT = 5;

interface Props {
    categoryTypes: Record<string, string>;
}

function getCategoryTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        income: '📈',
        expense: '📉',
    };
    return icons[type] || '📁';
}

export default function CategoryCreateGuided({ categoryTypes }: Props) {
    const [step, setStep] = useState(0);
    const typeEntries = Object.entries(categoryTypes);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'expense',
        color: '#6366f1',
        icon: '💸',
        is_fixed_expense: false,
        exclude_from_lifestyle_score: false,
        expense_distribution: null as 'needs' | 'wants' | 'investments' | null,
    });

    const isExpense = data.type === 'expense';
    const typeLabel = categoryTypes[data.type] ?? data.type;

    const canNext = (): boolean => {
        if (step === 0) {
            return data.name.trim().length > 0;
        }
        return true;
    };

    const stepMeta = [
        { title: 'Come si chiama?', subtitle: 'Es. Spesa, Affitto, Stipendio' },
        { title: 'Entrata o uscita?', subtitle: 'Definisce come verrà usata nei report.' },
        { title: 'Icona e colore', subtitle: 'Riconoscila subito in lista.' },
        { title: 'Opzioni avanzate', subtitle: 'Solo per le uscite — puoi saltare.' },
        { title: 'Conferma', subtitle: 'Controlla e crea la categoria.' },
    ][step];

    const wizardSteps = Array.from({ length: STEP_COUNT }, () => ({}));

    const distributionLabel = (() => {
        switch (data.expense_distribution) {
            case 'needs':
                return 'Necessità';
            case 'wants':
                return 'Extra';
            case 'investments':
                return 'Investimenti';
            default:
                return 'Nessuno';
        }
    })();

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                if (step < STEP_COUNT - 1) {
                    if (canNext()) {
                        setStep((s) => s + 1);
                    }
                } else {
                    post(route('categories.store'), {
                        onSuccess: () => cats.created(data.type as 'expense' | 'income'),
                    });
                }
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps}
                currentStep={step}
                title={stepMeta.title}
                subtitle={stepMeta.subtitle}
            >
                {step === 0 && (
                    <div>
                        <InputLabel htmlFor="name" value="Nome categoria" />
                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="es. Spesa, Affitto..."
                            autoFocus
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                )}

                {step === 1 && (
                    <div className="grid grid-cols-2 gap-3">
                        {typeEntries.map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => {
                                    setData('type', value);
                                    setData('icon', value === 'income' ? '💰' : '💸');
                                }}
                                className={clsx(
                                    'flex items-center gap-2 rounded-xl border-2 p-3 text-left',
                                    data.type === value
                                        ? value === 'income'
                                            ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                            : 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                        : 'border-gray-200 dark:border-gray-600',
                                )}
                            >
                                <span className="text-2xl">{getCategoryTypeIcon(value)}</span>
                                <span className="text-sm font-medium">{label}</span>
                            </button>
                        ))}
                        <InputError message={errors.type} className="col-span-2" />
                    </div>
                )}

                {step === 2 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Icona" />
                            <EmojiPicker
                                value={data.icon}
                                onChange={(emoji) => setData('icon', emoji)}
                                className="mt-1"
                            />
                            <InputError message={errors.icon} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="color" value="Colore" />
                            <div className="mt-1 flex items-center gap-3">
                                <input
                                    id="color"
                                    type="color"
                                    className="h-10 w-14 cursor-pointer rounded border border-gray-300 p-1 dark:border-gray-700"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                />
                                <TextInput
                                    type="text"
                                    className="block flex-1"
                                    value={data.color}
                                    onChange={(e) => setData('color', e.target.value)}
                                    maxLength={20}
                                />
                            </div>
                            <InputError message={errors.color} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        {!isExpense ? (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Nessuna opzione extra per le entrate. Premi Avanti o Salta.
                            </p>
                        ) : (
                            <>
                                <label className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                                    <input
                                        type="checkbox"
                                        checked={data.is_fixed_expense}
                                        onChange={(e) => setData('is_fixed_expense', e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600"
                                    />
                                    <span className="text-sm text-blue-900 dark:text-blue-100">Spesa fissa</span>
                                </label>
                                <label className="flex items-start gap-3 rounded-lg border border-purple-200 bg-purple-50 p-3 dark:border-purple-800 dark:bg-purple-900/20">
                                    <input
                                        type="checkbox"
                                        checked={data.exclude_from_lifestyle_score}
                                        onChange={(e) => setData('exclude_from_lifestyle_score', e.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-gray-300 text-purple-600"
                                    />
                                    <span className="text-sm text-purple-900 dark:text-purple-100">
                                        Escludi dal Lifestyle Score
                                    </span>
                                </label>
                                <div>
                                    <InputLabel value="Gruppo di spesa" />
                                    <div className="mt-2 grid grid-cols-2 gap-2">
                                        {(
                                            [
                                                { value: null, label: 'Nessuno', icon: '—' },
                                                { value: 'needs', label: 'Necessità', icon: '🏠' },
                                                { value: 'wants', label: 'Extra', icon: '🎯' },
                                                { value: 'investments', label: 'Investimenti', icon: '📈' },
                                            ] as const
                                        ).map((opt) => (
                                            <button
                                                key={String(opt.value)}
                                                type="button"
                                                onClick={() => setData('expense_distribution', opt.value)}
                                                className={clsx(
                                                    'rounded-lg border-2 p-2 text-center text-xs font-medium',
                                                    data.expense_distribution === opt.value
                                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                                        : 'border-gray-200 dark:border-gray-600',
                                                )}
                                            >
                                                <span className="block text-lg">{opt.icon}</span>
                                                {opt.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                )}

                {step === 4 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Nome</dt>
                            <dd className="font-medium">{data.name}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Tipo</dt>
                            <dd>{typeLabel}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Aspetto</dt>
                            <dd>
                                {data.icon}{' '}
                                <span
                                    className="inline-block h-3 w-3 rounded-full align-middle"
                                    style={{ backgroundColor: data.color }}
                                />
                            </dd>
                        </div>
                        {isExpense && (data.is_fixed_expense || data.exclude_from_lifestyle_score || data.expense_distribution) && (
                            <>
                                {data.is_fixed_expense && (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-gray-500">Spesa fissa</dt>
                                        <dd>Sì</dd>
                                    </div>
                                )}
                                {data.exclude_from_lifestyle_score && (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-gray-500">Lifestyle</dt>
                                        <dd>Esclusa</dd>
                                    </div>
                                )}
                                {data.expense_distribution && (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-gray-500">Gruppo</dt>
                                        <dd>{distributionLabel}</dd>
                                    </div>
                                )}
                            </>
                        )}
                    </dl>
                )}

                <div className="mt-8 flex items-center justify-between gap-3">
                    {step > 0 ? (
                        <button
                            type="button"
                            onClick={() => setStep((s) => Math.max(0, s - 1))}
                            className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                        >
                            Indietro
                        </button>
                    ) : (
                        <span />
                    )}
                    <div className="flex gap-2">
                        {step === 3 && (
                            <button
                                type="button"
                                onClick={() => setStep((s) => s + 1)}
                                className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                Salta
                            </button>
                        )}
                        <PrimaryButton type="submit" disabled={!canNext() || processing}>
                            {step === STEP_COUNT - 1
                                ? processing
                                    ? 'Salvataggio...'
                                    : 'Crea categoria'
                                : 'Avanti'}
                        </PrimaryButton>
                    </div>
                </div>
            </GuidedFormWizard>
        </form>
    );
}
