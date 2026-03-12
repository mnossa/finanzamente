import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import { Head, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { PageProps } from '@/types';
import React, { useRef, useEffect } from 'react';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
    icon: string | null;
}

interface SessionTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    category: {
        id: number;
        name: string;
        color: string | null;
        icon: string | null;
        type: string;
    } | null;
    account: {
        id: number;
        name: string;
        currency_code: string;
    };
}

interface Props extends PageProps {
    accounts: Account[];
    categories: Category[];
    sessionTransactions: SessionTransaction[];
    defaultAccountId: string | null;
}

// ---------------------------------------------------------------------------
// Session Summary
// ---------------------------------------------------------------------------

function SessionSummary({ transactions }: { transactions: SessionTransaction[] }) {
    const income = transactions.filter(t => t.amount > 0).reduce((s, t) => s + t.amount, 0);
    const expenses = transactions.filter(t => t.amount < 0).reduce((s, t) => s + Math.abs(t.amount), 0);
    const net = income - expenses;

    return (
        <div className="grid grid-cols-3 gap-3 text-center">
            <div className="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 p-3">
                <p className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Entrate</p>
                <p className="text-base font-bold text-emerald-700 dark:text-emerald-300">
                    +{formatCurrency(income)}
                </p>
            </div>
            <div className="rounded-xl bg-red-50 dark:bg-red-900/20 p-3">
                <p className="text-xs text-red-600 dark:text-red-400 font-medium">Uscite</p>
                <p className="text-base font-bold text-red-700 dark:text-red-300">
                    -{formatCurrency(expenses)}
                </p>
            </div>
            <div className={clsx(
                'rounded-xl p-3',
                net >= 0
                    ? 'bg-blue-50 dark:bg-blue-900/20'
                    : 'bg-orange-50 dark:bg-orange-900/20'
            )}>
                <p className={clsx(
                    'text-xs font-medium',
                    net >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400'
                )}>
                    Netto sessione
                </p>
                <p className={clsx(
                    'text-base font-bold',
                    net >= 0 ? 'text-blue-700 dark:text-blue-300' : 'text-orange-700 dark:text-orange-300'
                )}>
                    {net >= 0 ? '+' : ''}{formatCurrency(net)}
                </p>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Quick Entry Form
// ---------------------------------------------------------------------------

interface FormState {
    account_id: string;
    category_id: string;
    amount: string;
    date: string;
    description: string;
    is_private: boolean;
}

function QuickEntryForm({ accounts, categories, defaultAccountId }: {
    accounts: Account[];
    categories: Category[];
    defaultAccountId: string | null;
}) {
    const amountRef = useRef<HTMLInputElement>(null);

    // useForm inferisce il tipo dai valori iniziali per compatibilità con il
    // constraint FormDataType<TForm> introdotto in Inertia v2.
    const { data, setData, post, processing, errors, reset } = useForm({
        account_id: defaultAccountId ?? (accounts[0]?.id?.toString() ?? ''),
        category_id: '',
        amount: '',
        date: new Date().toISOString().split('T')[0],
        description: '',
        is_private: false,
    });

    // Focus sull'importo al caricamento e dopo ogni submit riuscito
    useEffect(() => {
        amountRef.current?.focus();
    }, []);

    const incomeCategories = categories.filter(c => c.type === 'income');
    const expenseCategories = categories.filter(c => c.type === 'expense');

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(route('transactions.quick-store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset('amount', 'description', 'category_id');
                setTimeout(() => amountRef.current?.focus(), 100);
            },
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {/* Importo */}
                <div className="col-span-2 sm:col-span-1">
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        Importo <span className="text-red-500">*</span>
                    </label>
                    <input
                        ref={amountRef}
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={data.amount}
                        onChange={e => setData('amount', e.target.value)}
                        placeholder="0,00"
                        className={clsx(
                            'w-full px-3 py-2 rounded-lg border text-sm bg-white dark:bg-gray-800 dark:text-white transition-colors',
                            errors.amount
                                ? 'border-red-400 focus:ring-red-300'
                                : 'border-gray-300 dark:border-gray-600 focus:ring-emerald-300 focus:border-emerald-400'
                        )}
                    />
                    {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                </div>

                {/* Data */}
                <div>
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        Data <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        value={data.date}
                        onChange={e => setData('date', e.target.value)}
                        className={clsx(
                            'w-full px-3 py-2 rounded-lg border text-sm bg-white dark:bg-gray-800 dark:text-white',
                            errors.date
                                ? 'border-red-400'
                                : 'border-gray-300 dark:border-gray-600 focus:ring-emerald-300 focus:border-emerald-400'
                        )}
                    />
                    {errors.date && <p className="mt-1 text-xs text-red-500">{errors.date}</p>}
                </div>

                {/* Conto */}
                <div>
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        Conto <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.account_id}
                        onChange={e => setData('account_id', e.target.value)}
                        className={clsx(
                            'w-full px-3 py-2 rounded-lg border text-sm bg-white dark:bg-gray-800 dark:text-white',
                            errors.account_id
                                ? 'border-red-400'
                                : 'border-gray-300 dark:border-gray-600'
                        )}
                    >
                        <option value="">— Seleziona —</option>
                        {accounts.map(a => (
                            <option key={a.id} value={a.id}>{a.name}</option>
                        ))}
                    </select>
                    {errors.account_id && <p className="mt-1 text-xs text-red-500">{errors.account_id}</p>}
                </div>

                {/* Categoria */}
                <div>
                    <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        Categoria <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.category_id}
                        onChange={e => setData('category_id', e.target.value)}
                        className={clsx(
                            'w-full px-3 py-2 rounded-lg border text-sm bg-white dark:bg-gray-800 dark:text-white',
                            errors.category_id
                                ? 'border-red-400'
                                : 'border-gray-300 dark:border-gray-600'
                        )}
                    >
                        <option value="">— Seleziona —</option>
                        {expenseCategories.length > 0 && (
                            <optgroup label="Uscite">
                                {expenseCategories.map(c => (
                                    <option key={c.id} value={c.id}>{c.icon ? `${c.icon} ` : ''}{c.name}</option>
                                ))}
                            </optgroup>
                        )}
                        {incomeCategories.length > 0 && (
                            <optgroup label="Entrate">
                                {incomeCategories.map(c => (
                                    <option key={c.id} value={c.id}>{c.icon ? `${c.icon} ` : ''}{c.name}</option>
                                ))}
                            </optgroup>
                        )}
                    </select>
                    {errors.category_id && <p className="mt-1 text-xs text-red-500">{errors.category_id}</p>}
                </div>
            </div>

            {/* Descrizione */}
            <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    Descrizione
                </label>
                <input
                    type="text"
                    value={data.description}
                    onChange={e => setData('description', e.target.value)}
                    placeholder="es. Spesa al supermercato"
                    maxLength={1000}
                    className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm bg-white dark:bg-gray-800 dark:text-white focus:ring-emerald-300 focus:border-emerald-400 transition-colors"
                />
            </div>

            <div className="flex items-center justify-between gap-3 pt-1">
                <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={data.is_private}
                        onChange={e => setData('is_private', e.target.checked)}
                        className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                    />
                    Privata
                </label>
                <button
                    type="submit"
                    disabled={processing || !data.amount || !data.account_id || !data.category_id}
                    className={clsx(
                        'px-6 py-2.5 rounded-xl text-sm font-semibold transition-all',
                        processing || !data.amount || !data.account_id || !data.category_id
                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500'
                            : 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 shadow-sm'
                    )}
                >
                    {processing ? '⏳ Salvataggio...' : '+ Aggiungi'}
                </button>
            </div>
        </form>
    );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function QuickSession({ accounts, categories, sessionTransactions, defaultAccountId }: Props) {
    const hasSessions = sessionTransactions.length > 0;

    function handleEndSession() {
        if (!window.confirm('Terminare la sessione? La lista verrà svuotata (le transazioni rimarranno salvate).')) return;
        router.delete(route('transactions.quick-session.clear'), { preserveScroll: false });
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Sessione Rapida"
                    subtitle="Inserisci più transazioni consecutive in un'unica sessione"
                    backLink={route('transactions.index')}
                />
            }
        >
            <Head title="Sessione Rapida" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto space-y-6">

                {/* Istruzione */}
                <div className="flex items-start gap-3 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <span className="text-xl shrink-0">⚡</span>
                    <div className="text-sm text-blue-800 dark:text-blue-200">
                        <strong>Modalità batch:</strong> inserisci rapidamente tutte le transazioni accumulate.
                        Ogni invio salva la transazione e lascia il form aperto per la prossima.
                        Al termine clicca <em>Fine sessione</em> per chiudere la lista.
                    </div>
                </div>

                {/* Form di inserimento rapido */}
                <CardBox className="p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-gray-900 dark:text-white mb-4">
                        Nuova Transazione
                    </h2>
                    <QuickEntryForm
                        accounts={accounts}
                        categories={categories}
                        defaultAccountId={defaultAccountId}
                    />
                </CardBox>

                {/* Riepilogo sessione */}
                {hasSessions && (
                    <CardBox className="shadow-sm overflow-hidden">
                        <div className="p-5 border-b border-gray-100 dark:border-gray-700">
                            <div className="flex items-center justify-between mb-3">
                                <h2 className="text-base font-semibold text-gray-900 dark:text-white">
                                    Transazioni di questa sessione
                                </h2>
                                <span className="text-xs text-gray-400">
                                    {sessionTransactions.length} voce{sessionTransactions.length !== 1 ? 'i' : ''}
                                </span>
                            </div>
                            <SessionSummary transactions={sessionTransactions} />
                        </div>

                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {sessionTransactions.map(t => {
                                const isIncome = t.amount > 0;
                                return (
                                    <div key={t.id} className="flex items-center gap-3 px-5 py-3">
                                        {t.category && (
                                            <span
                                                className="h-2 w-2 rounded-full flex-shrink-0"
                                                style={{ backgroundColor: t.category.color ?? '#94a3b8' }}
                                            />
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {t.description ?? t.category?.name ?? '—'}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {formatDate(t.date)} · {t.account.name}
                                                {t.category && ` · ${t.category.name}`}
                                            </p>
                                        </div>
                                        <span className={clsx(
                                            'text-sm font-semibold flex-shrink-0',
                                            isIncome
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-red-600 dark:text-red-400'
                                        )}>
                                            {isIncome ? '+' : ''}{formatCurrency(t.amount)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Fine sessione */}
                        <div className="p-5 border-t border-gray-100 dark:border-gray-700">
                            <button
                                type="button"
                                onClick={handleEndSession}
                                className="w-full py-3 rounded-xl border-2 border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm font-semibold hover:bg-red-100 dark:hover:bg-red-900/40 active:scale-[0.98] transition-all"
                            >
                                ✓ Fine sessione — chiudi la lista
                            </button>
                        </div>
                    </CardBox>
                )}

                {!hasSessions && (
                    <div className="text-center py-8 text-gray-400 dark:text-gray-600 text-sm">
                        Le transazioni inserite in questa sessione appariranno qui
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
