import CategoryPicker from '@/Components/CategoryPicker';
import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import RecurrenceScheduleFields, { formatRecurrenceScheduleRule } from '@/Components/RecurrenceScheduleFields';
import TextInput from '@/Components/TextInput';
import { recurring } from '@/utils/analytics';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
    icon: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Frequencies {
    [key: string]: string;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    remaining_amount: number;
    type: 'debt' | 'credit';
    status: string;
    currency_code: string;
}

interface Props {
    accounts: Account[];
    categories: Category[];
    frequencies: Frequencies;
    debtsCredits: DebtCredit[];
}

const STEP_COUNT = 7;

export default function RecurringTransactionCreateGuided({ accounts, categories, frequencies, debtsCredits }: Props) {
    const [step, setStep] = useState(0);
    const [txType, setTxType] = useState<'income' | 'expense'>('expense');
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        category_id: '',
        amount: '',
        frequency: 'monthly',
        day_of_month_mode: 'start_date',
        day_of_month: '',
        non_working_day_policy: 'postpone',
        start_date: today,
        end_date: '',
        description: '',
        debt_credit_id: '',
    });

    const selectedCategory = useMemo(
        () => categories.find((category) => String(category.id) === data.category_id),
        [categories, data.category_id],
    );

    const filteredDebtsCredits = useMemo(() => {
        if (!selectedCategory) return debtsCredits;
        const expectedType = selectedCategory.type === 'expense' ? 'debt' : 'credit';
        return debtsCredits.filter((item) => item.type === expectedType);
    }, [debtsCredits, selectedCategory]);

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) setStep((s) => s + 1);
    };

    const canNext = (): boolean => {
        if (step === 0) return Boolean(data.account_id);
        if (step === 1) return Boolean(data.category_id);
        if (step === 2) return data.amount !== '' && Number(data.amount) > 0 && Boolean(data.frequency);
        if (step === 3) return Boolean(data.start_date);
        return true;
    };

    const meta = [
        { title: 'Seleziona conto', subtitle: 'Conto su cui registrare la ricorrenza.' },
        { title: 'Seleziona categoria', subtitle: 'Tipo transazione bloccato dalla categoria.' },
        { title: 'Importo e frequenza', subtitle: 'Quanto e ogni quanto si ripete.' },
        { title: 'Periodo attivo', subtitle: 'Data inizio obbligatoria, fine opzionale.' },
        { title: 'Collegamento debito/credito', subtitle: 'Opzionale - puoi saltare.' },
        { title: 'Descrizione', subtitle: 'Opzionale - puoi saltare.' },
        { title: 'Conferma', subtitle: 'Verifica prima di creare.' },
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

                post(route('recurring-transactions.store'), {
                    onSuccess: () => recurring.created(data.frequency, txType),
                });
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={meta.title}
                subtitle={meta.subtitle}
            >
                {step === 0 && (
                    <div className="max-h-[min(40vh,16rem)] space-y-2 overflow-y-auto">
                        {accounts.map((account) => (
                            <button
                                key={account.id}
                                type="button"
                                onClick={() => setData('account_id', String(account.id))}
                                className={clsx(
                                    'w-full rounded-xl border-2 p-3 text-left',
                                    data.account_id === String(account.id)
                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-gray-200 dark:border-gray-700',
                                )}
                            >
                                <div className="font-medium">{account.name}</div>
                                <div className="text-xs text-gray-500">{account.currency_code}</div>
                            </button>
                        ))}
                        <InputError message={errors.account_id} className="mt-2" />
                    </div>
                )}

                {step === 1 && (
                    <div>
                        <CategoryPicker
                            categories={categories}
                            value={data.category_id}
                            onChange={(categoryId) => {
                                setData('category_id', categoryId);
                                const category = categories.find((item) => String(item.id) === categoryId);
                                if (category) {
                                    setTxType(category.type);
                                }
                            }}
                            lockedType={txType}
                        />
                        <InputError message={errors.category_id} className="mt-2" />
                    </div>
                )}

                {step === 2 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="amount" value="Importo" />
                            <div className="relative mt-1">
                                <span
                                    className={clsx(
                                        'absolute left-3 top-1/2 -translate-y-1/2 text-lg',
                                        txType === 'expense' ? 'text-red-500' : 'text-green-500',
                                    )}
                                >
                                    {txType === 'expense' ? '-' : '+'}
                                </span>
                                <TextInput
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    className="block w-full pl-8"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                />
                            </div>
                            <InputError message={errors.amount} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="frequency" value="Frequenza" />
                            <select
                                id="frequency"
                                value={data.frequency}
                                onChange={(e) => setData('frequency', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {Object.entries(frequencies).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.frequency} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="start_date" value="Data inizio" />
                                <TextInput
                                    id="start_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.start_date}
                                    onChange={(e) => setData('start_date', e.target.value)}
                                />
                                <InputError message={errors.start_date} className="mt-2" />
                            </div>
                            <div>
                                <InputLabel htmlFor="end_date" value="Data fine (opzionale)" />
                                <TextInput
                                    id="end_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.end_date}
                                    onChange={(e) => setData('end_date', e.target.value)}
                                />
                                <InputError message={errors.end_date} className="mt-2" />
                            </div>
                        </div>
                        <RecurrenceScheduleFields
                            frequency={data.frequency}
                            dayOfMonthMode={data.day_of_month_mode as 'start_date' | 'fixed' | 'last_day'}
                            dayOfMonth={data.day_of_month}
                            nonWorkingDayPolicy={data.non_working_day_policy as 'postpone' | 'anticipate' | 'keep'}
                            errors={{
                                day_of_month_mode: errors.day_of_month_mode,
                                day_of_month: errors.day_of_month,
                                non_working_day_policy: errors.non_working_day_policy,
                            }}
                            onChange={(field, value) => setData(field, value)}
                        />
                    </div>
                )}

                {step === 4 && (
                    <div>
                        {debtsCredits.length === 0 ? (
                            <p className="text-sm text-gray-500">Nessun debito/credito disponibile.</p>
                        ) : !selectedCategory ? (
                            <p className="text-sm text-gray-500">Seleziona prima una categoria per filtrare i collegamenti.</p>
                        ) : filteredDebtsCredits.length === 0 ? (
                            <p className="text-sm text-gray-500">
                                Nessun {txType === 'expense' ? 'debito' : 'credito'} aperto da collegare.
                            </p>
                        ) : (
                            <select
                                id="debt_credit_id"
                                value={data.debt_credit_id}
                                onChange={(e) => setData('debt_credit_id', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option value="">Nessun collegamento</option>
                                {filteredDebtsCredits.map((dc) => (
                                    <option key={dc.id} value={dc.id}>
                                        {dc.type === 'debt' ? '📤' : '📥'} {dc.counterparty} - {dc.remaining_amount}
                                    </option>
                                ))}
                            </select>
                        )}
                        <InputError message={errors.debt_credit_id} className="mt-2" />
                    </div>
                )}

                {step === 5 && (
                    <div>
                        <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                        <textarea
                            id="description"
                            rows={2}
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                        />
                        <InputError message={errors.description} className="mt-2" />
                    </div>
                )}

                {step === 6 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Tipo</dt>
                            <dd>{txType === 'income' ? 'Entrata' : 'Uscita'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Conto</dt>
                            <dd>{accounts.find((a) => String(a.id) === data.account_id)?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Categoria</dt>
                            <dd>{selectedCategory?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Importo</dt>
                            <dd>{data.amount}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Frequenza</dt>
                            <dd>{frequencies[data.frequency] || data.frequency}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Regola calendario</dt>
                            <dd className="text-right">
                                {formatRecurrenceScheduleRule(
                                    data.frequency,
                                    data.day_of_month_mode as 'start_date' | 'fixed' | 'last_day',
                                    data.day_of_month ? Number(data.day_of_month) : null,
                                    data.non_working_day_policy as 'postpone' | 'anticipate' | 'keep',
                                )}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Inizio</dt>
                            <dd>{formatItalianDate(data.start_date)}</dd>
                        </div>
                        {data.end_date && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Fine</dt>
                                <dd>{formatItalianDate(data.end_date)}</dd>
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
                    onSkip={step === 4 || step === 5 ? goNext : undefined}
                    cancelHref={route('recurring-transactions.index')}
                    submitLabel="Crea ricorrenza"
                />
            </GuidedFormWizard>
        </form>
    );
}
