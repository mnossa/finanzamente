import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TagAutocomplete from '@/Components/TagAutocomplete';
import TextInput from '@/Components/TextInput';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import SplitPaymentSection, { type SplitLine } from '@/Components/SplitPaymentSection';
import TransactionQuickChips, { type QuickChip } from '@/Components/TransactionQuickChips';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';
import { formatCurrency, formatDate } from '@/utils/format';

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

interface Tag {
    id: number;
    name: string;
    color: string | null;
}

interface DebtCreditPrefill {
    debt_credit_id: string;
    transaction_type: 'income' | 'expense';
    category_id: string;
    amount: string;
    description: string;
    account_id: string;
    date: string;
    original_currency_code: string;
    counterparty: string;
    type_label: string;
}

interface Props {
    accounts: Account[];
    categories: Category[];
    defaultAccountId?: string;
    debtCreditPrefill?: DebtCreditPrefill | null;
    quickChips?: QuickChip[];
}

const STEP_COUNT = 8;
const QUICK_STEP_COUNT = 2;

function formatItalianDate(dateStr: string): string {
    if (!dateStr) {
        return '-';
    }
    return formatDate(dateStr.includes('T') ? dateStr : `${dateStr}T12:00:00`);
}

export default function TransactionCreateGuided({
    accounts,
    categories,
    defaultAccountId,
    debtCreditPrefill = null,
    quickChips = [],
}: Props) {
    const today = new Date().toISOString().split('T')[0];
    const hasDebtPrefill = debtCreditPrefill !== null;
    const initialAccountId = debtCreditPrefill?.account_id || defaultAccountId || (accounts.length > 0 ? String(accounts[0].id) : '');
    const [step, setStep] = useState(hasDebtPrefill ? 1 : 0);
    const [quickFlow, setQuickFlow] = useState(false);
    const [txType, setTxType] = useState<'income' | 'expense'>(debtCreditPrefill?.transaction_type ?? 'expense');
    const [selectedTagsList, setSelectedTagsList] = useState<Tag[]>([]);

    const [splitEnabled, setSplitEnabled] = useState(false);
    const [splits, setSplits] = useState<SplitLine[]>(() => [
        { account_id: initialAccountId, amount: debtCreditPrefill?.amount || '' },
        {
            account_id: accounts[1] ? String(accounts[1].id) : (accounts[0] ? String(accounts[0].id) : ''),
            amount: '',
        },
    ]);

    const { data, setData, post, processing, errors, transform } = useForm({
        account_id: initialAccountId,
        category_id: debtCreditPrefill?.category_id || '',
        amount: debtCreditPrefill?.amount || '',
        date: debtCreditPrefill?.date || today,
        description: debtCreditPrefill?.description || '',
        is_private: false,
        is_tax_deductible: false,
        tax_deduction_rate: '19',
        tax_deduction_type: '',
        tax_year: new Date().getFullYear(),
        tag_ids: [] as number[],
        new_tag_names: [] as string[],
        debt_credit_id: debtCreditPrefill?.debt_credit_id || '',
        original_amount: '',
        original_currency_code: debtCreditPrefill?.original_currency_code || '',
        manual_rate: '',
        splits: [] as Array<{ account_id: number; amount: string }>,
    });

    transform((formData) => ({
        ...formData,
        splits: splitEnabled
            ? splits.map((line) => ({
                  account_id: Number(line.account_id),
                  amount: line.amount,
              }))
            : [],
        account_id: splitEnabled && splits[0]?.account_id ? splits[0].account_id : formData.account_id,
    }));

    const filteredCategories = useMemo(
        () => categories.filter((c) => c.type === txType),
        [categories, txType],
    );

    const selectedAccount = accounts.find((a) => String(a.id) === data.account_id);
    const selectedCategory = categories.find((c) => String(c.id) === data.category_id);
    const isExpense = txType === 'expense';
    const isQuickFinalStep = quickFlow && step === 1;

    const handleTagAdd = (tag: Tag) => {
        const normalized = { ...tag, name: tag.name.toUpperCase() };
        if (selectedTagsList.some((t) => t.name === normalized.name)) {
            return;
        }
        setSelectedTagsList((prev) => [...prev, normalized]);
        if (normalized.id > 0) {
            setData('tag_ids', [...data.tag_ids, normalized.id]);
        } else {
            setData('new_tag_names', [...data.new_tag_names, normalized.name]);
        }
    };

    const handleTagRemove = (tagName: string) => {
        const toRemove = selectedTagsList.find((t) => t.name === tagName);
        if (!toRemove) {
            return;
        }
        setSelectedTagsList((prev) => prev.filter((t) => t.name !== tagName));
        if (toRemove.id > 0) {
            setData('tag_ids', data.tag_ids.filter((id) => id !== toRemove.id));
        } else {
            setData('new_tag_names', data.new_tag_names.filter((n) => n !== tagName));
        }
    };

    const canNext = (): boolean => {
        switch (step) {
            case 0:
                return true;
            case 1:
                return data.amount !== '' && Number(data.amount) > 0;
            case 2:
                return Boolean(data.date);
            case 3: {
                if (splitEnabled) {
                    if (accounts.length < 2) {
                        return false;
                    }
                    const total = parseFloat(data.amount) || 0;
                    const sum = splits.reduce((acc, line) => acc + (parseFloat(line.amount) || 0), 0);
                    const hasTwoLines = splits.filter((l) => l.account_id && parseFloat(l.amount) > 0).length >= 2;

                    return hasTwoLines && Math.abs(sum - total) <= 0.02;
                }

                return Boolean(data.account_id);
            }
            case 4:
                return Boolean(data.category_id);
            case 5:
            case 6:
                return true;
            default:
                return true;
        }
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) {
            setStep((s) => s + 1);
        }
    };

    const goBack = () => {
        if (quickFlow && step === 1) {
            setQuickFlow(false);
            setStep(0);
            return;
        }

        setStep((s) => Math.max(0, s - 1));
    };

    const handleQuickChipSelect = (chip: QuickChip) => {
        setQuickFlow(true);
        setTxType(chip.type);
        setData((prev) => ({
            ...prev,
            category_id: String(chip.category_id),
            account_id: String(chip.account_id),
            date: today,
            ...(chip.type === 'income'
                ? { is_tax_deductible: false, tax_deduction_type: '' }
                : {}),
        }));
        setStep(1);
    };

    const submit = () => {
        post(route('transactions.store'));
    };

    const taxTypeLabel = TAX_DEDUCTION_TYPES.find((t) => t.value === data.tax_deduction_type)?.label;

    const stepMeta = [
        { title: 'Che tipo di movimento?', subtitle: 'Entrata o uscita di denaro.' },
        quickFlow
            ? {
                  title: txType === 'income' ? 'Quanto hai incassato?' : 'Quanto hai speso?',
                  subtitle: 'Categoria, conto e data sono già pronti.',
              }
            : { title: 'Quanto?', subtitle: "Inserisci l'importo in euro." },
        { title: 'Quando?', subtitle: 'Data del movimento.' },
        { title: 'Su quale conto?', subtitle: "Il conto interessato dall'operazione." },
        { title: 'In quale categoria?', subtitle: `Solo categorie di ${txType === 'income' ? 'entrata' : 'uscita'}.` },
        { title: 'Vuoi aggiungere una nota?', subtitle: 'Opzionale — puoi saltare.' },
        { title: 'Altre opzioni', subtitle: 'Tag, privacy e detrazioni fiscali. Tutto opzionale.' },
        { title: 'Tutto pronto?', subtitle: 'Controlla e conferma.' },
    ][step];

    const wizardSteps = Array.from({ length: quickFlow ? QUICK_STEP_COUNT : STEP_COUNT }, () => ({}));

    return (
        <>
            <Head title="Nuova transazione" />
            {hasDebtPrefill && debtCreditPrefill ? (
                <div className="mb-4 rounded-xl border border-purple-200 bg-purple-50 px-4 py-3 text-sm text-purple-900 dark:border-purple-700 dark:bg-purple-900/20 dark:text-purple-100">
                    Stai registrando un {debtCreditPrefill.type_label.toLowerCase()} per{' '}
                    <strong>{debtCreditPrefill.counterparty}</strong>. I campi sono precompilati, ma puoi modificarli.
                </div>
            ) : null}
            <form
                id={FM_MOBILE_PRIMARY_FORM_ID}
                onSubmit={(e) => {
                    e.preventDefault();
                    if (isQuickFinalStep || step === STEP_COUNT - 1) {
                        submit();
                    } else {
                        goNext();
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
                        <div className="space-y-5">
                            <TransactionQuickChips
                                chips={quickChips}
                                selectedCategoryId={data.category_id}
                                onSelect={handleQuickChipSelect}
                            />

                            {quickChips.length > 0 && (
                                <div className="flex items-center gap-3" aria-hidden="true">
                                    <span className="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
                                    <span className="text-xs font-medium uppercase tracking-wide text-gray-400">
                                        oppure inserisci a mano
                                    </span>
                                    <span className="h-px flex-1 bg-gray-200 dark:bg-gray-700" />
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-3">
                            {(['expense', 'income'] as const).map((type) => (
                                <button
                                    key={type}
                                    type="button"
                                    onClick={() => {
                                        setTxType(type);
                                        setData('category_id', '');
                                        if (type === 'income') {
                                            setData('is_tax_deductible', false);
                                            setData('tax_deduction_type', '');
                                        }
                                    }}
                                    className={clsx(
                                        'rounded-xl border-2 p-4 text-center transition-colors',
                                        txType === type
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-600',
                                    )}
                                >
                                    <span className="text-2xl">{type === 'income' ? '📥' : '📤'}</span>
                                    <p className="mt-2 font-medium">{type === 'income' ? 'Entrata' : 'Uscita'}</p>
                                </button>
                            ))}
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <InputLabel htmlFor="amount" value="Importo (€)" />
                            <TextInput
                                id="amount"
                                type="number"
                                inputMode="decimal"
                                step="0.01"
                                min="0.01"
                                className="mt-1 block w-full text-lg"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                autoFocus
                            />
                            <InputError message={errors.amount} className="mt-2" />
                            {quickFlow && (
                                <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-100">
                                    <p className="font-medium">
                                        {selectedCategory?.icon ? `${selectedCategory.icon} ` : ''}
                                        {selectedCategory?.name ?? 'Categoria frequente'}
                                    </p>
                                    <p className="mt-1 text-xs text-emerald-700 dark:text-emerald-200">
                                        {selectedAccount?.name ?? 'Conto selezionato'} · Oggi
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {step === 2 && (
                        <div>
                            <InputLabel htmlFor="date" value="Data" />
                            <TextInput
                                id="date"
                                type="date"
                                className="mt-1 block w-full"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                            />
                            <InputError message={errors.date} className="mt-2" />
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-4">
                            {accounts.length >= 2 ? (
                                <SplitPaymentSection
                                    enabled={splitEnabled}
                                    onToggle={setSplitEnabled}
                                    accounts={accounts}
                                    splits={splits}
                                    onSplitsChange={setSplits}
                                    totalAmount={data.amount}
                                    errors={errors}
                                />
                            ) : (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Serve almeno un secondo conto per ripartire il pagamento su più conti.
                                </p>
                            )}
                            {!splitEnabled && (
                                <div className="space-y-2">
                                    {accounts.map((account) => (
                                        <button
                                            key={account.id}
                                            type="button"
                                            onClick={() => setData('account_id', String(account.id))}
                                            className={clsx(
                                                'w-full rounded-lg border px-4 py-3 text-left text-sm font-medium transition-colors',
                                                data.account_id === String(account.id)
                                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                                    : 'border-gray-200 dark:border-gray-600',
                                            )}
                                        >
                                            {account.name}
                                        </button>
                                    ))}
                                </div>
                            )}
                            <InputError message={errors.account_id} />
                            <InputError message={errors.splits} />
                        </div>
                    )}

                    {step === 4 && (
                        <div>
                            <CategoryPicker
                                categories={filteredCategories}
                                value={data.category_id}
                                onChange={(id) => setData('category_id', id)}
                                lockedType={txType}
                            />
                            <InputError message={errors.category_id} className="mt-2" />
                        </div>
                    )}

                    {step === 5 && (
                        <div>
                            <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                            <TextInput
                                id="description"
                                className="mt-1 block w-full"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Es. Spesa al supermercato"
                            />
                        </div>
                    )}

                    {step === 6 && (
                        <div className="space-y-5">
                            <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-600">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                    checked={data.is_private}
                                    onChange={(e) => setData('is_private', e.target.checked)}
                                />
                                <span className="text-sm text-gray-800 dark:text-gray-200">Transazione privata</span>
                            </label>

                            <div>
                                <InputLabel value="Tag" />
                                <TagAutocomplete
                                    selectedTags={selectedTagsList}
                                    onAdd={handleTagAdd}
                                    onRemove={handleTagRemove}
                                    className="mt-1"
                                />
                            </div>

                            {isExpense && (
                                <div className="rounded-xl border-2 border-emerald-200 bg-emerald-50/50 dark:border-emerald-700 dark:bg-emerald-900/20">
                                    <label className="flex cursor-pointer items-center gap-3 px-4 py-3">
                                        <input
                                            type="checkbox"
                                            className="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                            checked={data.is_tax_deductible}
                                            onChange={(e) => {
                                                setData('is_tax_deductible', e.target.checked);
                                                if (!e.target.checked) {
                                                    setData('tax_deduction_type', '');
                                                }
                                            }}
                                        />
                                        <div>
                                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                Spesa detraibile (730)
                                            </p>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">
                                                Per la dichiarazione dei redditi
                                            </p>
                                        </div>
                                    </label>

                                    {data.is_tax_deductible && (
                                        <div className="space-y-3 border-t border-emerald-200 px-4 pb-4 pt-3 dark:border-emerald-700">
                                            <div>
                                                <InputLabel htmlFor="tax_deduction_type" value="Tipo di detrazione" />
                                                <select
                                                    id="tax_deduction_type"
                                                    className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                    value={data.tax_deduction_type}
                                                    onChange={(e) => setData('tax_deduction_type', e.target.value)}
                                                >
                                                    <option value="">Seleziona tipo</option>
                                                    {TAX_DEDUCTION_TYPES.map((opt) => (
                                                        <option key={opt.value} value={opt.value}>
                                                            {opt.label}
                                                        </option>
                                                    ))}
                                                </select>
                                                <InputError message={errors.tax_deduction_type} className="mt-1" />
                                            </div>
                                            <div>
                                                <InputLabel htmlFor="tax_deduction_rate" value="Percentuale (%)" />
                                                <TextInput
                                                    id="tax_deduction_rate"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    max="100"
                                                    className="mt-1 block w-full"
                                                    value={data.tax_deduction_rate}
                                                    onChange={(e) => setData('tax_deduction_rate', e.target.value)}
                                                />
                                                <InputError message={errors.tax_deduction_rate} className="mt-1" />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {step === 7 && (
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Tipo</dt>
                                <dd>{txType === 'income' ? 'Entrata' : 'Uscita'}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Importo</dt>
                                <dd className="font-semibold fm-sensitive-amount tabular-nums">
                                    {formatCurrency(Number(data.amount))}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Data</dt>
                                <dd>{formatItalianDate(data.date)}</dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Conto</dt>
                                <dd className="text-right">
                                    {splitEnabled
                                        ? splits
                                              .filter((l) => l.account_id && parseFloat(l.amount) > 0)
                                              .map((l) => {
                                                  const acc = accounts.find((a) => String(a.id) === l.account_id);
                                                  return `${acc?.name ?? 'Conto'}: ${formatCurrency(Number(l.amount))}`;
                                              })
                                              .join(' · ')
                                        : selectedAccount?.name}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Categoria</dt>
                                <dd className="text-right">{selectedCategory?.name}</dd>
                            </div>
                            {data.description && (
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Nota</dt>
                                    <dd className="text-right">{data.description}</dd>
                                </div>
                            )}
                            {data.is_private && (
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Privacy</dt>
                                    <dd>Privata</dd>
                                </div>
                            )}
                            {selectedTagsList.length > 0 && (
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Tag</dt>
                                    <dd className="text-right">{selectedTagsList.map((t) => t.name).join(', ')}</dd>
                                </div>
                            )}
                            {data.is_tax_deductible && isExpense && (
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Detrazione</dt>
                                    <dd className="text-right">
                                        {taxTypeLabel ?? data.tax_deduction_type} ({data.tax_deduction_rate}%)
                                    </dd>
                                </div>
                            )}
                        </dl>
                    )}

                    <div className="mt-8 flex items-center justify-between gap-3">
                        {step > 0 ? (
                            <button
                                type="button"
                                onClick={goBack}
                                className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            >
                                Indietro
                            </button>
                        ) : (
                            <Link href={route('transactions.index')} className="text-sm text-gray-500">
                                Annulla
                            </Link>
                        )}
                        <div className="flex gap-2">
                            {step === 6 && !quickFlow && (
                                <button
                                    type="button"
                                    onClick={() => setStep((s) => s + 1)}
                                    className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                >
                                    Salta
                                </button>
                            )}
                            <PrimaryButton type="submit" disabled={!canNext() || processing}>
                                {isQuickFinalStep || step === STEP_COUNT - 1
                                    ? processing
                                        ? 'Salvataggio...'
                                        : 'Salva'
                                    : 'Avanti'}
                            </PrimaryButton>
                        </div>
                    </div>
                </GuidedFormWizard>
            </form>
        </>
    );
}
