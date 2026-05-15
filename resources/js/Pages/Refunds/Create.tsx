import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { refunds } from '@/utils/analytics';
import clsx from 'clsx';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import PageHeader from '@/Components/PageHeader';
import axios from 'axios';

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

interface CreateProps {
    originalTransaction: RefundableTransaction | null;
    refundableTransactions: RefundableTransaction[];
    totalRefundableCount: number;
    categories: Category[];
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

// Custom hook per debounce
function useDebounce<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState<T>(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
}

export default function Create({ originalTransaction, refundableTransactions, totalRefundableCount, categories }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];
    const [searchTerm, setSearchTerm] = useState('');
    const [transactions, setTransactions] = useState<RefundableTransaction[]>(refundableTransactions);
    const [isSearching, setIsSearching] = useState(false);
    const abortControllerRef = useRef<AbortController | null>(null);

    const debouncedSearchTerm = useDebounce(searchTerm, 300);

    const { data, setData, post, processing, errors } = useForm({
        original_transaction_id: originalTransaction?.id?.toString() || '',
        amount: originalTransaction?.max_refundable?.toString() || '',
        category_id: '',
        date: today,
        description: '',
        is_private: false,
    });

    // Effettua la ricerca quando cambia il termine di ricerca (debounced)
    useEffect(() => {
        const searchTransactions = async () => {
            // Annulla la richiesta precedente se esiste
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }

            // Se il campo è vuoto, ripristina le transazioni iniziali
            if (!debouncedSearchTerm.trim()) {
                setTransactions(refundableTransactions);
                setIsSearching(false);
                return;
            }

            setIsSearching(true);
            abortControllerRef.current = new AbortController();

            try {
                const response = await axios.get(
                    route('refunds.search-transactions') + `?search=${encodeURIComponent(debouncedSearchTerm)}&limit=30`,
                    {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: abortControllerRef.current.signal,
                    }
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
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
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

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('refunds.store'), {
            onSuccess: () => refunds.created(),
        });
    };

    const showMoreInfo = totalRefundableCount > 20 && !searchTerm.trim();

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuovo Rimborso"
                    backLink={route('refunds.index')}
                />
            }
        >
            <Head title="Nuovo Rimborso" />

            <PageContent>
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Rimborsi" icon={<span className="text-sm leading-none">➕</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Nuovo rimborso</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Seleziona una spesa e registra il rimborso ricevuto.</p>
                        </header>
                        {refundableTransactions.length === 0 && !originalTransaction ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">💸</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessuna spesa da rimborsare
                                </h3>
                                <p className="mb-4 text-gray-500 dark:text-gray-400">
                                    Non ci sono transazioni di spesa che possono essere rimborsate.
                                    Per creare un rimborso devi prima registrare una spesa.
                                </p>
                                <LinkButton href={route('transactions.create')}>
                                    Crea una Transazione
                                </LinkButton>
                            </div>
                        ) : (
                            <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                                {/* Selezione Transazione */}
                                {!originalTransaction && (
                                    <div>
                                        <InputLabel htmlFor="transaction_search" value="Seleziona la spesa da rimborsare" />
                                        
                                        {/* Ricerca */}
                                        <div className="relative mt-1 mb-3">
                                            <TextInput
                                                id="transaction_search"
                                                type="text"
                                                className="block w-full pr-10"
                                                placeholder="Cerca per descrizione, categoria o conto..."
                                                value={searchTerm}
                                                onChange={(e) => setSearchTerm(e.target.value)}
                                            />
                                            {isSearching && (
                                                <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                                                    <svg className="h-5 w-5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            )}
                                        </div>

                                        {/* Info sul numero di transazioni */}
                                        {showMoreInfo && (
                                            <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                                Mostrate le ultime 20 transazioni su {totalRefundableCount} disponibili. Usa la ricerca per trovare transazioni più vecchie.
                                            </p>
                                        )}

                                        {/* Lista transazioni */}
                                        <div className="max-h-64 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                            {transactions.length > 0 ? (
                                                transactions.map((tx) => (
                                                    <button
                                                        key={tx.id}
                                                        type="button"
                                                        onClick={() => handleSelectTransaction(tx)}
                                                        className={clsx(
                                                            'w-full border-b border-gray-100 p-3 text-left transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50',
                                                            data.original_transaction_id === tx.id.toString() &&
                                                                'bg-emerald-50 dark:bg-emerald-900/20'
                                                        )}
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center space-x-3">
                                                                <div
                                                                    className="flex h-8 w-8 items-center justify-center rounded-full text-sm"
                                                                    style={{
                                                                        backgroundColor: tx.category?.color
                                                                            ? `${tx.category.color}20`
                                                                            : '#ef444420',
                                                                    }}
                                                                >
                                                                    {tx.category?.icon || '💸'}
                                                                </div>
                                                                <div>
                                                                    <p className="font-medium text-gray-900 dark:text-white">
                                                                        {tx.description || tx.category?.name || 'Transazione'}
                                                                    </p>
                                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                        {tx.account.name} • {formatDate(tx.date)}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-red-600 dark:text-red-400">
                                                                    {formatCurrency(tx.amount, tx.account.currency_code)}
                                                                </p>
                                                                {tx.already_refunded > 0 && (
                                                                    <p className="text-xs text-green-600 dark:text-green-400">
                                                                        Già rimborsato: {formatCurrency(tx.already_refunded, tx.account.currency_code)}
                                                                    </p>
                                                                )}
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                    Max rimborsabile: {formatCurrency(tx.max_refundable, tx.account.currency_code)}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </button>
                                                ))
                                            ) : (
                                                <div className="p-4 text-center text-gray-500 dark:text-gray-400">
                                                    {isSearching ? 'Ricerca in corso...' : 'Nessuna transazione trovata'}
                                                </div>
                                            )}
                                        </div>
                                        <InputError message={errors.original_transaction_id} className="mt-2" />
                                    </div>
                                )}

                                {/* Transazione selezionata (preview) */}
                                {selectedTransaction && (
                                    <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                        <h4 className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Transazione selezionata:
                                        </h4>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center space-x-3">
                                                <div
                                                    className="flex h-10 w-10 items-center justify-center rounded-full text-lg"
                                                    style={{
                                                        backgroundColor: selectedTransaction.category?.color
                                                            ? `${selectedTransaction.category.color}20`
                                                            : '#ef444420',
                                                    }}
                                                >
                                                    {selectedTransaction.category?.icon || '💸'}
                                                </div>
                                                <div>
                                                    <p className="font-medium text-gray-900 dark:text-white">
                                                        {selectedTransaction.description || selectedTransaction.category?.name || 'Transazione'}
                                                    </p>
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                        {selectedTransaction.account.name} • {formatDate(selectedTransaction.date)}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-lg font-semibold text-red-600 dark:text-red-400">
                                                    {formatCurrency(selectedTransaction.amount, selectedTransaction.account.currency_code)}
                                                </p>
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    Rimborsabile: {formatCurrency(selectedTransaction.max_refundable, selectedTransaction.account.currency_code)}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Importo */}
                                <div>
                                    <InputLabel htmlFor="amount" value="Importo del rimborso" />
                                    <div className="mt-1 flex items-center space-x-2">
                                        <TextInput
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            max={selectedTransaction?.max_refundable}
                                            className="block flex-1"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            required
                                        />
                                        {selectedTransaction && (
                                            <>
                                                <span className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                    {selectedTransaction.account.currency_code}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() => setData('amount', selectedTransaction.max_refundable.toFixed(2))}
                                                    className="rounded-md bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                                >
                                                    Max
                                                </button>
                                            </>
                                        )}
                                    </div>
                                    <InputError message={errors.amount} className="mt-2" />
                                    {selectedTransaction && Number(data.amount) > selectedTransaction.max_refundable && (
                                        <p className="mt-1 text-sm text-red-500">
                                            L'importo supera il massimo rimborsabile ({formatCurrency(selectedTransaction.max_refundable, selectedTransaction.account.currency_code)})
                                        </p>
                                    )}
                                </div>

                                {/* Categoria */}
                                <div>
                                    <InputLabel htmlFor="category_id" value="Categoria del rimborso" />
                                    <select
                                        id="category_id"
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.category_id}
                                        onChange={(e) => setData('category_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Seleziona categoria</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.icon} {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.category_id} className="mt-2" />
                                </div>

                                {/* Data */}
                                <div>
                                    <InputLabel htmlFor="date" value="Data del rimborso" />
                                    <TextInput
                                        id="date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                    />
                                    <InputError message={errors.date} className="mt-2" />
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
                                        placeholder="es. Rimborso per reso prodotto"
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Privato */}
                                <div className="flex items-start rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                    <div className="flex h-6 items-center">
                                        <input
                                            id="is_private"
                                            type="checkbox"
                                            className="h-4 w-4 rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                            checked={data.is_private}
                                            onChange={(e) => setData('is_private', e.target.checked)}
                                        />
                                    </div>
                                    <div className="ml-3">
                                        <label
                                            htmlFor="is_private"
                                            className="text-sm font-medium text-gray-900 dark:text-white"
                                        >
                                            🔒 Rimborso privato
                                        </label>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Solo tu potrai vedere questo rimborso e la transazione collegata.
                                        </p>
                                    </div>
                                </div>

                                {/* Anteprima */}
                                {selectedTransaction && data.amount && Number(data.amount) > 0 && (
                                    <div className="rounded-xl bg-green-50 p-4 dark:bg-green-900/20">
                                        <p className="text-sm text-green-600 dark:text-green-400">
                                            Verrà creata una transazione di entrata:
                                        </p>
                                        <p className="text-2xl font-bold text-green-700 dark:text-green-300">
                                            +{formatCurrency(Number(data.amount), selectedTransaction.account.currency_code)}
                                        </p>
                                        <p className="text-sm text-green-600 dark:text-green-400">
                                            sul conto {selectedTransaction.account.name}
                                        </p>
                                    </div>
                                )}

                                {/* Azioni */}
                                <FormActionsBar className="justify-end">
                                    <Link
                                        href={route('refunds.index')}
                                        className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton
                                        disabled={
                                            processing ||
                                            !data.original_transaction_id ||
                                            !data.amount ||
                                            !data.category_id ||
                                            (selectedTransaction !== null && Number(data.amount) > selectedTransaction.max_refundable)
                                        }
                                    >
                                        {processing ? 'Registrazione in corso...' : 'Registra Rimborso'}
                                    </PrimaryButton>
                                </FormActionsBar>
                            </form>
                        )}
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
