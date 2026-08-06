import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface RefundableTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    already_refunded: number;
    max_refundable: number;
    account: Account;
    category: Category | null;
}

interface Props {
    originalTransaction: RefundableTransaction | null;
    refundableTransactions: RefundableTransaction[];
    categories: Category[];
    totalRefundableCount?: number;
}

function formatCurrency(amount: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(amount);
}

function useDebounce<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState<T>(value);
    useEffect(() => {
        const handler = setTimeout(() => setDebouncedValue(value), delay);
        return () => clearTimeout(handler);
    }, [value, delay]);
    return debouncedValue;
}

export default function RefundCreateGuided({ originalTransaction, refundableTransactions, categories, totalRefundableCount }: Props) {
    const [step, setStep] = useState(0);
    const [searchTerm, setSearchTerm] = useState('');
    const [transactions, setTransactions] = useState<RefundableTransaction[]>(refundableTransactions);
    const [isSearching, setIsSearching] = useState(false);
    const abortControllerRef = useRef<AbortController | null>(null);
    const debouncedSearchTerm = useDebounce(searchTerm, 300);
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        original_transaction_id: originalTransaction?.id?.toString() || '',
        amount: originalTransaction?.max_refundable?.toString() || '',
        category_id: '',
        date: today,
        description: '',
        is_private: false,
    });

    useEffect(() => {
        const searchTransactions = async () => {
            if (abortControllerRef.current) abortControllerRef.current.abort();

            if (!debouncedSearchTerm.trim()) {
                setTransactions(refundableTransactions);
                setIsSearching(false);
                return;
            }

            setIsSearching(true);
            abortControllerRef.current = new AbortController();

            try {
                const response = await axios.get(
                    `${route('refunds.search-transactions')}?search=${encodeURIComponent(debouncedSearchTerm)}&limit=30`,
                    {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: abortControllerRef.current.signal,
                    },
                );
                setTransactions(response.data.transactions);
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    console.error('Errore nella ricerca:', error);
                }
            } finally {
                setIsSearching(false);
            }
        };

        searchTransactions();
        return () => {
            if (abortControllerRef.current) abortControllerRef.current.abort();
        };
    }, [debouncedSearchTerm, refundableTransactions]);

    const selectedTransaction = useMemo(() => {
        if (!data.original_transaction_id) return null;
        const id = Number(data.original_transaction_id);
        if (originalTransaction?.id === id) return originalTransaction;
        return transactions.find((tx) => tx.id === id) || refundableTransactions.find((tx) => tx.id === id) || null;
    }, [data.original_transaction_id, originalTransaction, transactions, refundableTransactions]);

    const handleSelectTransaction = useCallback((tx: RefundableTransaction) => {
        setData((prev) => ({
            ...prev,
            original_transaction_id: tx.id.toString(),
            amount: tx.max_refundable.toFixed(2),
        }));
    }, [setData]);

    const visualSteps = useMemo(() => {
        if (originalTransaction) return [1, 2, 3];
        return [0, 1, 2, 3];
    }, [originalTransaction]);
    const visualStep = visualSteps[step] ?? 0;
    const visualCount = visualSteps.length;

    const canNext = (): boolean => {
        if (visualStep === 0) return Boolean(data.original_transaction_id);
        if (visualStep === 1) {
            return Boolean(data.amount) && Number(data.amount) > 0 &&
                (!selectedTransaction || Number(data.amount) <= selectedTransaction.max_refundable);
        }
        if (visualStep === 2) return Boolean(data.category_id);
        return true;
    };

    const goNext = () => {
        if (step < visualCount - 1 && canNext()) setStep((s) => s + 1);
    };

    const stepMetaMap: Record<number, { title: string; subtitle: string }> = {
        0: { title: 'Scegli la spesa', subtitle: 'Cerca e seleziona la transazione da rimborsare.' },
        1: { title: 'Importo rimborso', subtitle: 'Totale o parziale, senza superare il residuo.' },
        2: { title: 'Categoria e dettagli', subtitle: 'Categoria obbligatoria, resto opzionale.' },
        3: { title: 'Conferma', subtitle: 'Controlla e registra il rimborso.' },
    };
    const meta = stepMetaMap[visualStep] ?? stepMetaMap[0];

    const shownCount = totalRefundableCount ?? refundableTransactions.length;

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(event) => {
                event.preventDefault();
                if (step < visualCount - 1) {
                    goNext();
                    return;
                }
                post(route('refunds.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(visualCount)}
                currentStep={step}
                title={meta.title}
                subtitle={meta.subtitle}
            >
                {visualStep === 0 && (
                    <div>
                        <InputLabel htmlFor="transaction_search" value="Cerca transazione" />
                        <TextInput
                            id="transaction_search"
                            className="mt-1 block w-full"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Descrizione, categoria o conto..."
                        />
                        {shownCount > 20 && !searchTerm.trim() && (
                            <p className="mt-2 text-xs text-gray-500">
                                Mostrate le ultime 20 transazioni su {shownCount} disponibili.
                            </p>
                        )}
                        <div className="mt-3 max-h-64 space-y-2 overflow-y-auto">
                            {transactions.map((tx) => (
                                <button
                                    key={tx.id}
                                    type="button"
                                    onClick={() => handleSelectTransaction(tx)}
                                    className={clsx(
                                        'w-full rounded-lg border p-3 text-left',
                                        data.original_transaction_id === String(tx.id)
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700',
                                    )}
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <p className="font-medium">{tx.description || tx.category?.name || 'Transazione'}</p>
                                            <p className="text-xs text-gray-500">{tx.account.name} - {formatItalianDate(tx.date)}</p>
                                        </div>
                                        <div className="text-right text-xs">
                                            <p className="font-semibold">{formatCurrency(tx.amount, tx.account.currency_code)}</p>
                                            <p className="text-gray-500">Max: {formatCurrency(tx.max_refundable, tx.account.currency_code)}</p>
                                        </div>
                                    </div>
                                </button>
                            ))}
                            {transactions.length === 0 && (
                                <p className="text-sm text-gray-500">{isSearching ? 'Ricerca in corso...' : 'Nessuna transazione trovata'}</p>
                            )}
                        </div>
                        <InputError message={errors.original_transaction_id} className="mt-2" />
                    </div>
                )}

                {visualStep === 1 && (
                    <div className="space-y-3">
                        {selectedTransaction && (
                            <div className="rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800/50">
                                <div className="font-medium">{selectedTransaction.description || selectedTransaction.category?.name || 'Transazione'}</div>
                                <div className="text-xs text-gray-500">
                                    Rimborsabile: {formatCurrency(selectedTransaction.max_refundable, selectedTransaction.account.currency_code)}
                                </div>
                            </div>
                        )}
                        <div>
                            <InputLabel htmlFor="amount" value="Importo del rimborso" />
                            <div className="mt-1 flex items-center gap-2">
                                <TextInput
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={selectedTransaction?.max_refundable}
                                    className="block w-full"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                />
                                {selectedTransaction && (
                                    <button
                                        type="button"
                                        onClick={() => setData('amount', selectedTransaction.max_refundable.toFixed(2))}
                                        className="rounded-md bg-gray-100 px-3 py-2 text-xs dark:bg-gray-700"
                                    >
                                        Max
                                    </button>
                                )}
                            </div>
                            <InputError message={errors.amount} className="mt-2" />
                        </div>
                    </div>
                )}

                {visualStep === 2 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="category_id" value="Categoria rimborso" />
                            <select
                                id="category_id"
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                            >
                                <option value="">Seleziona categoria</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.icon || '📁'} {category.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.category_id} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="date" value="Data rimborso" />
                            <TextInput
                                id="date"
                                type="date"
                                className="mt-1 block w-full"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                            />
                            <InputError message={errors.date} className="mt-2" />
                        </div>
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
                        <label className="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                                checked={data.is_private}
                                onChange={(e) => setData('is_private', e.target.checked)}
                            />
                            <span className="text-sm">Rimborso privato</span>
                        </label>
                    </div>
                )}

                {visualStep === 3 && (
                    <dl className="space-y-3 text-sm">
                        {selectedTransaction && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Transazione origine</dt>
                                <dd className="text-right">{selectedTransaction.description || selectedTransaction.category?.name || 'Transazione'}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Importo rimborso</dt>
                            <dd className="font-medium">
                                {selectedTransaction
                                    ? formatCurrency(Number(data.amount || 0), selectedTransaction.account.currency_code)
                                    : data.amount}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Categoria</dt>
                            <dd>{categories.find((category) => String(category.id) === data.category_id)?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Data</dt>
                            <dd>{formatItalianDate(data.date)}</dd>
                        </div>
                        {data.description && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Descrizione</dt>
                                <dd className="text-right">{data.description}</dd>
                            </div>
                        )}
                        {data.is_private && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Privacy</dt>
                                <dd>Privato</dd>
                            </div>
                        )}
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={visualCount}
                    processing={processing}
                    canNext={canNext()}
                    onBack={() => setStep((s) => Math.max(0, s - 1))}
                    onSkip={visualStep === 2 ? goNext : undefined}
                    cancelHref={route('refunds.index')}
                    submitLabel="Registra rimborso"
                />
            </GuidedFormWizard>
        </form>
    );
}
