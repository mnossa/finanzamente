import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import TagAutocomplete from '@/Components/TagAutocomplete';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TransactionCreateGuided from './TransactionCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import clsx from 'clsx';
import PageHeader from '@/Components/PageHeader';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import { useFxPreview } from '@/hooks/useFxPreview';
import { useFormTimer } from '@/hooks/useFormTimer';
import { tx } from '@/utils/analytics';
import SplitPaymentSection, { SplitLine } from '@/Components/SplitPaymentSection';
import MealVoucherSpendSection, { MealVoucherLine } from '@/Components/MealVoucherSpendSection';
import { useState, useEffect, useRef, useMemo } from 'react';
import {
    accountsForTransactionType,
    mealVoucherUnitValueOnDate,
    resolveTransactionAccountId,
    type TransactionAccount,
} from '@/utils/transactionAccounts';

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
    icon: string | null;
}

interface Account extends TransactionAccount {}

interface Tag {
    id: number;
    name: string;
    color: string | null;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    remaining_amount: number;
    type: 'debt' | 'credit';
    status: string;
    currency_code: string;
}

interface Currency {
    code: string;
    name: string;
    symbol: string | null;
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

interface CreateProps {
    accounts: Account[];
    categories: Category[];
    defaultAccountId?: string;
    defaultDebtCreditId?: string;
    debtCreditPrefill?: DebtCreditPrefill | null;
    debtsCredits: DebtCredit[];
    currencies: Currency[];
    userDefaultCurrency: string;
}

export default function Create({
    accounts,
    categories,
    defaultAccountId,
    defaultDebtCreditId,
    debtCreditPrefill = null,
    debtsCredits,
    currencies,
    userDefaultCurrency,
}: CreateProps) {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features) && accounts.length > 0 && categories.length > 0) {
        return (
            <AuthenticatedLayout
                header={
                    <PageHeader
                        title="Nuova transazione"
                        mobileTitle="Nuova"
                        backLink={route('transactions.index')}
                    />
                }
            >
                <PageContent maxWidth="3xl">
                    <TransactionCreateGuided
                        accounts={accounts}
                        categories={categories}
                        defaultAccountId={defaultAccountId}
                        debtCreditPrefill={debtCreditPrefill}
                    />
                </PageContent>
            </AuthenticatedLayout>
        );
    }

    const today = new Date().toISOString().split('T')[0];
    const amountRef = useRef<HTMLInputElement>(null);
    const prefillAccountId = debtCreditPrefill?.account_id || defaultAccountId || (accounts.length > 0 ? String(accounts[0].id) : '');

    const { data, setData, post, processing, errors, transform } = useForm({
        account_id: prefillAccountId,
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
        debt_credit_id: debtCreditPrefill?.debt_credit_id || defaultDebtCreditId || '',
        original_amount: '',
        original_currency_code: debtCreditPrefill?.original_currency_code || '',
        manual_rate: '',
        splits: [] as SplitLine[],
        meal_voucher_lines: [] as MealVoucherLine[],
    });

    const [splitEnabled, setSplitEnabled] = useState(false);
    const [splits, setSplits] = useState<SplitLine[]>(() => [
        { account_id: prefillAccountId, amount: debtCreditPrefill?.amount || '' },
        {
            account_id: accounts[1] ? String(accounts[1].id) : (accounts[0] ? String(accounts[0].id) : ''),
            amount: '',
        },
    ]);
    const [showFx, setShowFx] = useState(
        () => Boolean(
            debtCreditPrefill?.original_currency_code
            && accounts.find((account) => String(account.id) === prefillAccountId)?.currency_code
            && debtCreditPrefill.original_currency_code !== accounts.find((account) => String(account.id) === prefillAccountId)?.currency_code,
        ),
    );
    const { getElapsedSeconds } = useFormTimer();
    const submitted = useRef(false);
    const fxTracked = useRef(false);
    const optionsTracked = useRef(false);
    const taxTracked = useRef(false);

    // Rilevazione abbandono form
    useEffect(() => {
        return () => {
            if (!submitted.current) {
                tx.formAbandoned('create', getElapsedSeconds());
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    const selectedAccount = accounts.find((a) => a.id === Number(data.account_id));
    const accountCurrency = selectedAccount?.currency_code ?? 'EUR';
    const isMealVoucherAccount = Boolean(selectedAccount?.is_meal_voucher);
    const mealVoucherLots = selectedAccount?.meal_voucher_lots ?? [];
    const mealVoucherUnit = mealVoucherUnitValueOnDate(selectedAccount, data.date);

    const fxPreview = useFxPreview({
        enabled: showFx && !!data.original_currency_code && !!accountCurrency && data.original_currency_code !== accountCurrency,
        from: data.original_currency_code,
        to: accountCurrency,
        date: data.date,
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';

    const selectableAccounts = useMemo(
        () => accountsForTransactionType(accounts, isExpense ? 'expense' : 'income'),
        [accounts, isExpense],
    );

    useEffect(() => {
        const nextAccountId = resolveTransactionAccountId(selectableAccounts, data.account_id);
        if (nextAccountId !== data.account_id) {
            setData('account_id', nextAccountId);
        }
    }, [selectableAccounts, data.account_id, setData]);

    useEffect(() => {
        if (!splitEnabled) {
            return;
        }

        setSplits((currentSplits) =>
            currentSplits.map((line) => ({
                ...line,
                account_id: resolveTransactionAccountId(selectableAccounts, line.account_id),
            })),
        );
    }, [selectableAccounts, splitEnabled]);

    // Filtra i debiti/crediti in base al tipo di categoria:
    // spesa → debiti (stai pagando ciò che devi)
    // entrata → crediti (stai incassando ciò che ti devono)
    const filteredDebtsCredits = selectedCategory
        ? debtsCredits.filter((dc) => (isExpense ? dc.type === 'debt' : dc.type === 'credit'))
        : debtsCredits;

    const [selectedTagsList, setSelectedTagsList] = useState<Tag[]>([]);

    const handleTagAdd = (tag: Tag) => {
        const normalized = { ...tag, name: tag.name.toUpperCase() };
        if (selectedTagsList.some((t) => t.name === normalized.name)) return;
        setSelectedTagsList((prev) => [...prev, normalized]);
        if (normalized.id > 0) {
            setData('tag_ids', [...data.tag_ids, normalized.id]);
        } else {
            setData('new_tag_names', [...data.new_tag_names, normalized.name]);
        }
    };

    const handleTagRemove = (tagName: string) => {
        const toRemove = selectedTagsList.find((t) => t.name === tagName);
        if (!toRemove) return;
        setSelectedTagsList((prev) => prev.filter((t) => t.name !== tagName));
        if (toRemove.id > 0) {
            setData('tag_ids', data.tag_ids.filter((id) => id !== toRemove.id));
        } else {
            setData('new_tag_names', data.new_tag_names.filter((n) => n !== tagName));
        }
    };

    transform((formData) => ({
        ...formData,
        splits: splitEnabled
            ? splits.map((line) => ({
                  account_id: Number(line.account_id),
                  amount: line.amount,
              }))
            : [],
        account_id: splitEnabled && splits[0]?.account_id
            ? splits[0].account_id
            : formData.account_id,
    }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('transactions.store'), {
            onSuccess: () => {
                submitted.current = true;
                tx.created({
                    type: selectedCategory?.type ?? 'expense',
                    has_tags: selectedTagsList.length > 0,
                    has_description: data.description.trim().length > 0,
                    has_fx: showFx && !!data.original_currency_code,
                    is_private: data.is_private,
                    is_tax_deductible: data.is_tax_deductible,
                    has_debt_link: !!data.debt_credit_id,
                    form_seconds: getElapsedSeconds(),
                });
            },
            onError: (errors) => {
                tx.formError('create', Object.keys(errors));
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuova Transazione"
                    backLink={route('transactions.index')}
                />
            }
        >
            <Head title="Nuova Transazione" />

            <PageContent maxWidth="3xl">
                    <SectionCard className="space-y-4">
                        {/* Titolo pagina visibile solo su desktop */}
                        <header className="hidden sm:block space-y-1">
                            <SectionBadge
                                label="Transazioni"
                                icon={<span className="text-sm leading-none">💸</span>}
                            />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Inserisci una nuova transazione
                            </h2>
                        </header>
                        <div className="space-y-2">
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Altre operazioni
                            </p>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <Link
                                    href={route('transfers.create')}
                                    className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-left transition-colors hover:border-amber-400 dark:border-amber-800 dark:bg-amber-900/20 dark:hover:border-amber-600"
                                >
                                    <span className="text-lg" aria-hidden>🔄</span>
                                    <p className="mt-1 text-sm font-semibold text-amber-950 dark:text-amber-100">
                                        Trasferimento
                                    </p>
                                    <p className="mt-0.5 text-xs text-amber-800 dark:text-amber-200">
                                        Tra due conti
                                    </p>
                                </Link>
                                <Link
                                    href={route('refunds.create')}
                                    className="rounded-xl border border-sky-200 bg-sky-50 px-3 py-3 text-left transition-colors hover:border-sky-400 dark:border-sky-800 dark:bg-sky-900/20 dark:hover:border-sky-600"
                                >
                                    <span className="text-lg" aria-hidden>💸</span>
                                    <p className="mt-1 text-sm font-semibold text-sky-950 dark:text-sky-100">
                                        Rimborso
                                    </p>
                                    <p className="mt-0.5 text-xs text-sky-800 dark:text-sky-200">
                                        Su una spesa esistente
                                    </p>
                                </Link>
                                <Link
                                    href={route('recurring-transactions.create')}
                                    className="rounded-xl border border-violet-200 bg-violet-50 px-3 py-3 text-left transition-colors hover:border-violet-400 dark:border-violet-800 dark:bg-violet-900/20 dark:hover:border-violet-600"
                                >
                                    <span className="text-lg" aria-hidden>🔁</span>
                                    <p className="mt-1 text-sm font-semibold text-violet-950 dark:text-violet-100">
                                        Ricorrenza
                                    </p>
                                    <p className="mt-0.5 text-xs text-violet-800 dark:text-violet-200">
                                        Movimento ripetuto
                                    </p>
                                </Link>
                            </div>
                        </div>
                        {accounts.length === 0 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏦</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessun conto disponibile
                                </h3>
                                <p className="mb-4 text-gray-500 dark:text-gray-400">
                                    Crea prima un conto per poter registrare transazioni.
                                </p>
                                <LinkButton href={route('accounts.create')}>
                                    Crea un Conto
                                </LinkButton>
                            </div>
                        ) : categories.length === 0 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏷️</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessuna categoria disponibile
                                </h3>
                                <p className="mb-4 text-gray-500 dark:text-gray-400">
                                    Crea prima delle categorie per classificare le transazioni.
                                </p>
                                <LinkButton href={route('categories.create')}>
                                    Crea una Categoria
                                </LinkButton>
                            </div>
                        ) : (
                            <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                                {/* Importo e Data — in cima, visibili subito */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel htmlFor="amount" value="Importo" />
                                        <div className="relative mt-1">
                                            <span
                                                className={clsx(
                                                    'absolute left-3 top-1/2 -translate-y-1/2 text-lg font-bold',
                                                    isExpense ? 'text-red-500' : 'text-green-500'
                                                )}
                                            >
                                                {isExpense ? '−' : '+'}
                                            </span>
                                            <TextInput
                                                ref={amountRef}
                                                id="amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                className="block w-full pl-8 text-lg font-semibold"
                                                value={data.amount}
                                                onChange={(e) => setData('amount', e.target.value)}
                                                placeholder="0,00"
                                                required
                                            />
                                        </div>
                                        <InputError message={errors.amount} className="mt-1" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="date" value="Data" />
                                        <TextInput
                                            id="date"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.date}
                                            onChange={(e) => setData('date', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.date} className="mt-1" />
                                    </div>
                                </div>

                                {/* Categoria */}
                                <div>
                                    <InputLabel htmlFor="category_id" value="Categoria" />
                                    <CategoryPicker
                                        categories={categories}
                                        value={data.category_id}
                                        onChange={(categoryId) => {
                                            setData('category_id', categoryId);
                                        }}
                                        error={errors.category_id}
                                        className="mt-1"
                                    />
                                </div>

                                {!isMealVoucherAccount && (
                                <SplitPaymentSection
                                    enabled={splitEnabled}
                                    onToggle={(enabled) => {
                                        setSplitEnabled(enabled);
                                    }}
                                    accounts={selectableAccounts}
                                    splits={splits}
                                    onSplitsChange={setSplits}
                                    totalAmount={data.amount}
                                    errors={errors as Record<string, string>}
                                />
                                )}

                                {/* Conto */}
                                {!splitEnabled && (
                                <div>
                                    <InputLabel htmlFor="account_id" value="Conto" />
                                    <select
                                        id="account_id"
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.account_id}
                                        onChange={(e) => {
                                            setData('account_id', e.target.value);
                                            setData('meal_voucher_lines', []);
                                        }}
                                        required
                                    >
                                        <option value="">Seleziona un conto</option>
                                        {selectableAccounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name} ({account.currency_code})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.account_id} className="mt-1" />
                                </div>
                                )}

                                {isMealVoucherAccount && isExpense && !splitEnabled && (
                                    <MealVoucherSpendSection
                                        lots={mealVoucherLots}
                                        lines={data.meal_voucher_lines}
                                        amount={data.amount}
                                        currencyCode={accountCurrency}
                                        error={errors.meal_voucher_lines}
                                        onAmountChange={(value) => setData('amount', value)}
                                        onChange={(lines, euro) => {
                                            setData('meal_voucher_lines', lines);
                                            if (euro > 0) {
                                                setData('amount', String(euro));
                                            }
                                        }}
                                    />
                                )}

                                {isMealVoucherAccount && !isExpense && mealVoucherUnit && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Accredito buoni: l&apos;importo deve essere un multiplo di{' '}
                                        {new Intl.NumberFormat('it-IT', { style: 'currency', currency: accountCurrency }).format(mealVoucherUnit)}{' '}
                                        (ticket interi al valore vigente alla data selezionata).
                                    </p>
                                )}

                                {/* Descrizione */}
                                <div>
                                    <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                                    <textarea
                                        id="description"
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        rows={2}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="es. Spesa al supermercato"
                                    />
                                    <InputError message={errors.description} className="mt-1" />
                                </div>

                                {/* Opzioni extra — collassabili su mobile */}
                                <details className="group rounded-xl border border-gray-200 dark:border-gray-700" onToggle={(e) => {
                                    if ((e.target as HTMLDetailsElement).open && !optionsTracked.current) {
                                        optionsTracked.current = true;
                                        tx.optionsOpened();
                                    }
                                }}>
                                    <summary className="flex cursor-pointer select-none items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        <span>Opzioni aggiuntive</span>
                                        <svg className="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </summary>
                                    <div className="space-y-4 px-4 pb-4 pt-2">
                                        {/* Transazione Privata */}
                                        <label className="flex cursor-pointer items-center gap-3 rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-gray-700/50">
                                            <input
                                                id="is_private"
                                                type="checkbox"
                                                className="h-5 w-5 rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                                checked={data.is_private}
                                                onChange={(e) => setData('is_private', e.target.checked)}
                                            />
                                            <div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-white">🔒 Transazione privata</p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">Solo tu potrai vederla</p>
                                            </div>
                                        </label>

                                        {/* Tag */}
                                        <div>
                                            <InputLabel value="Tag" />
                                            <TagAutocomplete
                                                selectedTags={selectedTagsList}
                                                onAdd={handleTagAdd}
                                                onRemove={handleTagRemove}
                                                className="mt-1"
                                            />
                                        </div>

                                        {/* Valuta diversa */}
                                        <div className="rounded-lg border border-dashed border-gray-300 p-3 dark:border-gray-600">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    const next = !showFx;
                                                    setShowFx(next);
                                                    if (!next) {
                                                        setData('original_amount', '');
                                                        setData('original_currency_code', '');
                                                        setData('manual_rate', '');
                                                    } else {
                                                        if (!data.original_currency_code) {
                                                            setData('original_currency_code', userDefaultCurrency || 'EUR');
                                                        }
                                                        if (!fxTracked.current) {
                                                            fxTracked.current = true;
                                                            tx.fxOpened();
                                                        }
                                                    }
                                                }}
                                                className="flex w-full items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-200"
                                                aria-expanded={showFx}
                                            >
                                                <span>💱 Pagato in valuta diversa ({accountCurrency})</span>
                                                <span className="text-xs text-gray-500">{showFx ? '−' : '+'}</span>
                                            </button>

                                            {showFx && (
                                                <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <InputLabel htmlFor="original_amount" value="Importo originale" />
                                                        <TextInput
                                                            id="original_amount"
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            className="mt-1 block w-full"
                                                            value={data.original_amount}
                                                            onChange={(e) => setData('original_amount', e.target.value)}
                                                            placeholder="es. 30"
                                                        />
                                                        <InputError message={errors.original_amount} className="mt-1" />
                                                    </div>
                                                    <div>
                                                        <InputLabel htmlFor="original_currency_code" value="Valuta" />
                                                        <select
                                                            id="original_currency_code"
                                                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                            value={data.original_currency_code}
                                                            onChange={(e) => setData('original_currency_code', e.target.value)}
                                                        >
                                                            <option value="">Seleziona…</option>
                                                            {currencies.map((c) => (
                                                                <option key={c.code} value={c.code}>
                                                                    {c.code} — {c.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        <InputError message={errors.original_currency_code} className="mt-1" />
                                                    </div>
                                                    <div className="col-span-2 sm:col-span-1">
                                                        <InputLabel htmlFor="manual_rate" value="Cambio manuale (opz.)" />
                                                        <TextInput
                                                            id="manual_rate"
                                                            type="number"
                                                            step="0.0001"
                                                            min="0.0001"
                                                            className="mt-1 block w-full"
                                                            value={data.manual_rate}
                                                            onChange={(e) => setData('manual_rate', e.target.value)}
                                                            placeholder="lascia vuoto per il tasso BCE"
                                                        />
                                                        <InputError message={errors.manual_rate} className="mt-1" />
                                                        {data.original_currency_code && data.original_currency_code !== accountCurrency && (
                                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400" data-testid="fx-preview-hint">
                                                                {fxPreview.isLoading && 'Calcolo cambio…'}
                                                                {!fxPreview.isLoading && fxPreview.rate !== null && (
                                                                    <>1 {data.original_currency_code} = {fxPreview.rate.toFixed(4)} {accountCurrency}</>
                                                                )}
                                                                {!fxPreview.isLoading && fxPreview.error && (
                                                                    <span className="text-amber-600 dark:text-amber-400">{fxPreview.error}</span>
                                                                )}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* Collega a Debito/Credito */}
                                        {debtsCredits.length > 0 && (
                                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-3 dark:border-purple-700 dark:bg-purple-900/20">
                                                <InputLabel htmlFor="debt_credit_id" value="🔗 Collega a Debito/Credito" />
                                                {filteredDebtsCredits.length === 0 && selectedCategory ? (
                                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        Nessun {isExpense ? 'debito' : 'credito'} aperto.
                                                    </p>
                                                ) : (
                                                    <select
                                                        id="debt_credit_id"
                                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                        value={data.debt_credit_id}
                                                        onChange={(e) => setData('debt_credit_id', e.target.value)}
                                                    >
                                                        <option value="">Nessun collegamento</option>
                                                        {filteredDebtsCredits.map((dc) => (
                                                            <option key={dc.id} value={dc.id}>
                                                                {dc.type === 'debt' ? '📤' : '📥'} {dc.counterparty} — rimanenti: {new Intl.NumberFormat('it-IT', { style: 'currency', currency: dc.currency_code }).format(dc.remaining_amount)}
                                                            </option>
                                                        ))}
                                                    </select>
                                                )}
                                                {!selectedCategory && (
                                                    <p className="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                                        Seleziona prima una categoria.
                                                    </p>
                                                )}
                                                <InputError message={errors.debt_credit_id} className="mt-1" />
                                            </div>
                                        )}
                                    </div>
                                </details>

                                {/* Detrazione Fiscale (solo per spese) */}
                                {isExpense && (
                                    <div className="rounded-xl border-2 border-emerald-200 bg-emerald-50/50 dark:border-emerald-700 dark:bg-emerald-900/20">
                                        <label className="flex cursor-pointer items-center gap-3 px-4 py-3">
                                            <input
                                                id="is_tax_deductible"
                                                type="checkbox"
                                                className="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                checked={data.is_tax_deductible}
                                                onChange={(e) => {
                                                    setData('is_tax_deductible', e.target.checked);
                                                    if (e.target.checked && !taxTracked.current) {
                                                        taxTracked.current = true;
                                                        tx.taxOpened();
                                                    }
                                                }}
                                            />
                                            <div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-white">📋 Spesa detraibile (730)</p>
                                                <p className="text-xs text-gray-600 dark:text-gray-400">Segna per la dichiarazione dei redditi</p>
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
                                                        required={data.is_tax_deductible}
                                                    >
                                                        <option value="">Seleziona tipo</option>
                                                        <option value="mediche">🏥 Spese Mediche (19%)</option>
                                                        <option value="veterinarie">🐾 Spese Veterinarie (19%)</option>
                                                        <option value="istruzione">🎓 Istruzione (19%)</option>
                                                        <option value="mutuo">🏠 Mutuo Prima Casa (19%)</option>
                                                        <option value="ristrutturazione">🔨 Ristrutturazione (50%)</option>
                                                        <option value="assicurazioni">🛡️ Assicurazioni (19%)</option>
                                                        <option value="previdenza">💼 Previdenza Complementare</option>
                                                        <option value="donazioni">❤️ Donazioni (19%-26%)</option>
                                                        <option value="altro">📌 Altro</option>
                                                    </select>
                                                    <InputError message={errors.tax_deduction_type} className="mt-1" />
                                                </div>
                                                <div className="grid grid-cols-2 gap-3">
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
                                                            placeholder="es. 19"
                                                            required={data.is_tax_deductible}
                                                        />
                                                        <InputError message={errors.tax_deduction_rate} className="mt-1" />
                                                    </div>
                                                    <div>
                                                        <InputLabel htmlFor="tax_year" value="Anno fiscale" />
                                                        <TextInput
                                                            id="tax_year"
                                                            type="number"
                                                            min="2000"
                                                            max="2100"
                                                            className="mt-1 block w-full"
                                                            value={data.tax_year}
                                                            onChange={(e) => setData('tax_year', Number(e.target.value))}
                                                        />
                                                        <InputError message={errors.tax_year} className="mt-1" />
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Azioni */}
                                <FormActionsBar className="justify-end">
                                    <Link
                                        href={route('transactions.index')}
                                        className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton disabled={processing || !data.category_id || !data.amount} className="flex-1 sm:flex-none justify-center">
                                        {processing ? 'Salvataggio...' : 'Salva Transazione'}
                                    </PrimaryButton>
                                </FormActionsBar>
                            </form>
                        )}
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
