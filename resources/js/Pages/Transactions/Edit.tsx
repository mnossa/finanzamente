import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import TagAutocomplete from '@/Components/TagAutocomplete';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import clsx from 'clsx';
import PageHeader from '@/Components/PageHeader';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import { useFxPreview } from '@/hooks/useFxPreview';
import { useFormTimer } from '@/hooks/useFormTimer';
import { tx } from '@/utils/analytics';
import { useState, useEffect, useRef } from 'react';

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

interface Transaction {
    id: number;
    account_id: number;
    category_id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    tax_deduction_rate: number | null;
    tax_deduction_type: string | null;
    tax_year: number | null;
    tag_ids: number[];
    tags: Tag[];
    transfer_id: number | null;
    debt_credit_id: number | null;
    is_inter_household_transfer?: boolean;
    currency_code?: string;
    original_amount?: number | null;
    original_currency_code?: string | null;
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

interface EditProps {
    transaction: Transaction;
    accounts: Account[];
    categories: Category[];
    debtsCredits: DebtCredit[];
    currencies: Currency[];
    userDefaultCurrency: string;
    indexQueryForReturn?: Record<string, string | number>;
}

function returnIndexQueryField(indexQueryForReturn?: Record<string, string | number>): string {
    if (!indexQueryForReturn || Object.keys(indexQueryForReturn).length === 0) {
        return '';
    }

    return JSON.stringify(indexQueryForReturn);
}

export default function Edit({
    transaction,
    accounts,
    categories,
    debtsCredits,
    currencies,
    userDefaultCurrency,
    indexQueryForReturn,
}: EditProps) {
    const indexReturn = indexQueryForReturn ?? {};
    const { data, setData, patch, processing, errors } = useForm({
        account_id: String(transaction.account_id),
        category_id: String(transaction.category_id),
        amount: String(transaction.amount),
        date: transaction.date,
        description: transaction.description || '',
        is_private: transaction.is_private,
        is_tax_deductible: transaction.is_tax_deductible || false,
        tax_deduction_rate: transaction.tax_deduction_rate ? String(transaction.tax_deduction_rate) : '19',
        tax_deduction_type: transaction.tax_deduction_type || '',
        tax_year: transaction.tax_year || new Date().getFullYear(),
        tag_ids: transaction.tag_ids || [],
        new_tag_names: [] as string[],
        debt_credit_id: transaction.debt_credit_id ? String(transaction.debt_credit_id) : '',
        original_amount: transaction.original_amount ? String(transaction.original_amount) : '',
        original_currency_code: transaction.original_currency_code ?? '',
        manual_rate: '',
        return_index_query: returnIndexQueryField(indexQueryForReturn),
    });

    const [showFx, setShowFx] = useState<boolean>(!!transaction.original_amount);
    const { getElapsedSeconds } = useFormTimer();
    const submitted = useRef(false);
    const fxTracked = useRef(false);
    const optionsTracked = useRef(false);
    const taxTracked = useRef(false);

    useEffect(() => {
        return () => {
            if (!submitted.current) {
                tx.formAbandoned('edit', getElapsedSeconds());
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);
    const selectedAccount = accounts.find((a) => a.id === Number(data.account_id));
    const accountCurrency = selectedAccount?.currency_code ?? transaction.currency_code ?? 'EUR';

    const fxPreview = useFxPreview({
        enabled: showFx && !!data.original_currency_code && !!accountCurrency && data.original_currency_code !== accountCurrency,
        from: data.original_currency_code,
        to: accountCurrency,
        date: data.date,
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';
    const isTransfer = transaction.transfer_id !== null;
    const isInterHouseholdTransfer = transaction.is_inter_household_transfer || false;

    // Filtra i debiti/crediti in base al tipo di categoria
    const filteredDebtsCredits = selectedCategory
        ? debtsCredits.filter((dc) => (isExpense ? dc.type === 'debt' : dc.type === 'credit'))
        : debtsCredits;

    const [selectedTagsList, setSelectedTagsList] = useState<Tag[]>(transaction.tags || []);

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

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('transactions.update', transaction.id), {
            onSuccess: () => {
                submitted.current = true;
                tx.edited({
                    type: selectedCategory?.type ?? 'expense',
                    has_tags: selectedTagsList.length > 0,
                    has_fx: showFx && !!data.original_currency_code,
                    form_seconds: getElapsedSeconds(),
                });
            },
            onError: (errors) => {
                tx.formError('edit', Object.keys(errors));
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Modifica Transazione"
                    backLink={route('transactions.index', indexReturn)}
                />
            }
        >
            <Head title="Modifica Transazione" />

            <PageContent maxWidth="2xl">
                    <SectionCard className="space-y-4">
                        {/* Titolo pagina visibile solo su desktop */}
                        <header className="hidden sm:block space-y-1">
                            <SectionBadge label="Transazioni" icon={<span className="text-sm leading-none">✏️</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Aggiorna transazione</h2>
                        </header>

                        {/* Avvisi trasferimento */}
                        {isInterHouseholdTransfer && (
                            <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                <span className="text-lg shrink-0">🚫</span>
                                <div>
                                    <p className="text-sm font-medium text-red-800 dark:text-red-200">
                                        Trasferimento tra Household — non modificabile
                                    </p>
                                    <p className="mt-0.5 text-xs text-red-700 dark:text-red-300">
                                        Per eliminare, vai alla lista dei trasferimenti inter-household.
                                    </p>
                                </div>
                            </div>
                        )}

                        {isTransfer && !isInterHouseholdTransfer && (
                            <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                                <span className="text-lg shrink-0">🔄</span>
                                <p className="text-xs text-amber-700 dark:text-amber-300">
                                    Fa parte di un trasferimento. Le modifiche verranno applicate anche alla transazione collegata.
                                </p>
                            </div>
                        )}

                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            {/* Importo e Data — visibili subito */}
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
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            className="block w-full pl-8 text-lg font-semibold"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            required
                                            disabled={isInterHouseholdTransfer}
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
                                        disabled={isInterHouseholdTransfer}
                                    />
                                    <InputError message={errors.date} className="mt-1" />
                                </div>
                            </div>

                            {/* Categoria */}
                            {!isInterHouseholdTransfer && (
                                <div>
                                    <InputLabel htmlFor="category_id" value="Categoria" />
                                    {isTransfer ? (
                                        <div className="mt-1">
                                            <div className={clsx(
                                                'flex items-center gap-3 rounded-xl border-2 p-3 cursor-not-allowed opacity-60',
                                                isExpense
                                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                                    : 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                            )}>
                                                <span className="text-2xl">{selectedCategory?.icon || (isExpense ? '💸' : '💰')}</span>
                                                <div>
                                                    <p className="font-medium text-gray-900 dark:text-white">{selectedCategory?.name}</p>
                                                    <p className={clsx('text-xs', isExpense ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                                                        {isExpense ? 'Uscita' : 'Entrata'} (non modificabile)
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <CategoryPicker
                                            categories={categories}
                                            value={data.category_id}
                                            onChange={(categoryId) => setData('category_id', categoryId)}
                                            error={errors.category_id}
                                            className="mt-1"
                                        />
                                    )}
                                </div>
                            )}

                            {/* Conto */}
                            <div>
                                <InputLabel htmlFor="account_id" value="Conto" />
                                <select
                                    id="account_id"
                                    className={clsx(
                                        'mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                        (isTransfer || isInterHouseholdTransfer) && 'cursor-not-allowed opacity-60'
                                    )}
                                    value={data.account_id}
                                    onChange={(e) => setData('account_id', e.target.value)}
                                    required
                                    disabled={isTransfer || isInterHouseholdTransfer}
                                >
                                    {accounts.map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.name} ({account.currency_code})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.account_id} className="mt-1" />
                            </div>

                            {/* Descrizione */}
                            <div>
                                <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                                <textarea
                                    id="description"
                                    className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={2}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    disabled={isInterHouseholdTransfer}
                                />
                                <InputError message={errors.description} className="mt-1" />
                            </div>

                            {/* Opzioni extra — collassabili */}
                            {!isInterHouseholdTransfer && (
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
                                        {/* Privata */}
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
                                                            placeholder="vuoto = tasso BCE"
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

                                        {/* Debito/Credito */}
                                        {debtsCredits.length > 0 && !isTransfer && (
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
                            )}

                            {/* Detrazione Fiscale */}
                            {isExpense && !isInterHouseholdTransfer && (
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
                                                    onChange={(e) => {
                                                        setData('tax_deduction_type', e.target.value);
                                                        const selectedType = TAX_DEDUCTION_TYPES.find(t => t.value === e.target.value);
                                                        if (selectedType) {
                                                            setData('tax_deduction_rate', String(selectedType.defaultRate));
                                                        }
                                                    }}
                                                    required={data.is_tax_deductible}
                                                >
                                                    <option value="">Seleziona tipo</option>
                                                    {TAX_DEDUCTION_TYPES.map((type) => (
                                                        <option key={type.value} value={type.value}>
                                                            {type.label}
                                                        </option>
                                                    ))}
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
                                    href={route('transactions.index', indexReturn)}
                                    className="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    {isInterHouseholdTransfer ? 'Torna Indietro' : 'Annulla'}
                                </Link>
                                {!isInterHouseholdTransfer && (
                                    <PrimaryButton disabled={processing} className="flex-1 sm:flex-none justify-center">
                                        {processing ? 'Salvataggio...' : 'Salva Modifiche'}
                                    </PrimaryButton>
                                )}
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
