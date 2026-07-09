import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import OrganizationHubNav from '@/Components/OrganizationHubNav';
import PageHeader from '@/Components/PageHeader';
import { mobileFilterBodyClass, mobileFilterSummaryClass } from '@/Components/IndexPageListToolbars';
import CardBox from '@/Components/CardBox';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexInfoBanner from '@/Components/Index/IndexInfoBanner';
import IndexListCard from '@/Components/Index/IndexListCard';
import { Head, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import { PageProps } from '@/types';
import React, { useState, useEffect, useMemo } from 'react';
import {
    accountsForTransactionType,
    resolveTransactionAccountId,
    type TransactionAccount,
} from '@/utils/transactionAccounts';

// -------------------------------------------------------------------------
// Types
// -------------------------------------------------------------------------

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
}

interface Account extends TransactionAccount {}

interface InboxItem {
    id: number;
    status: 'draft' | 'needs_review' | 'confirmed' | 'rejected';
    source: 'telegram_text' | 'telegram_photo' | 'manual';
    type: 'income' | 'expense';
    raw_text: string | null;
    image_path: string | null;
    ai_payload: { amt: number | null; shop: string | null; dt: string | null } | null;
    amount: string | null;
    currency_code: string | null;
    exchange_rate_to_base: string | null;
    amount_base: string | null;
    original_amount: string | null;
    original_currency_code: string | null;
    description: string | null;
    transaction_date: string | null;
    category: Category | null;
    account: Account | null;
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface ArchiveItem {
    id: number;
    status: 'confirmed' | 'rejected';
    type: 'income' | 'expense';
    amount: string | null;
    currency_code: string | null;
    description: string | null;
    transaction_date: string | null;
    category: { id: number; name: string } | null;
    updated_at: string | null;
}

interface Props extends PageProps {
    items: PaginatedData<InboxItem>;
    accounts: Account[];
    categories: Category[];
    pendingCount: number;
    archiveCount: number;
    recentArchive: ArchiveItem[];
    telegramLinked: boolean;
    telegramBotUsername: string | null;
}

function ArchiveRow({ item }: { item: ArchiveItem }) {
    const isIncome = item.type === 'income';
    const label = item.status === 'confirmed' ? 'Confermata' : 'Scartata';
    const badgeClass = item.status === 'confirmed'
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
        : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';

    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/50">
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                    {item.description || item.category?.name || 'Voce inbox'}
                </p>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                    {item.transaction_date ? formatDate(item.transaction_date) : '—'}
                    {item.category ? ` · ${item.category.name}` : ''}
                </p>
            </div>
            <div className="flex shrink-0 flex-col items-end gap-1">
                {item.amount !== null && (
                    <span className={clsx('text-sm font-semibold', isIncome ? 'text-emerald-600' : 'text-rose-600')}>
                        {isIncome ? '+' : '−'}{formatCurrency(Math.abs(parseFloat(item.amount)))}
                    </span>
                )}
                <span className={clsx('inline-flex rounded-full px-2 py-0.5 text-xs font-medium', badgeClass)}>
                    {label}
                </span>
            </div>
        </div>
    );
}

// -------------------------------------------------------------------------
// Badge stato voce
// -------------------------------------------------------------------------

function StatusBadge({ status }: { status: InboxItem['status'] }) {
    const config = {
        draft: { label: 'Da Verificare', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
        needs_review: { label: 'Da Verificare', className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
        confirmed: { label: 'Confermata', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
        rejected: { label: 'Scartata', className: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400' },
    };
    const { label, className } = config[status] ?? config.draft;
    return (
        <span className={clsx('inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', className)}>
            {(status === 'draft' || status === 'needs_review') && (
                <span className="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1 animate-pulse" />
            )}
            {label}
        </span>
    );
}

function SourceBadge({ source }: { source: InboxItem['source'] }) {
    if (source === 'telegram_text') {
        return <span className="text-xs text-sky-500 font-medium">✈ Telegram testo</span>;
    }
    if (source === 'telegram_photo') {
        return <span className="text-xs text-sky-500 font-medium">📸 Telegram foto</span>;
    }
    return <span className="text-xs text-slate-400">Manuale</span>;
}

// -------------------------------------------------------------------------
// Helper formattazione importo multi-currency
// -------------------------------------------------------------------------

const CURRENCY_SYMBOLS: Record<string, string> = {
    EUR: '€',
    GBP: '£',
    USD: '$',
    JPY: '¥',
    CHF: 'CHF',
};

function formatAmountWithCurrency(value: number, code: string | null | undefined): string {
    const formatted = formatCurrency(value);
    if (!code || code === 'EUR') {
        return formatted;
    }
    const symbol = CURRENCY_SYMBOLS[code];
    // formatCurrency restituisce "1.234,56 €" per EUR italiano. Per valute diverse
    // sostituiamo il simbolo €/append con il simbolo/codice della valuta.
    const numericPart = formatted.replace(/[€\s]/g, '');
    return symbol ? `${symbol}${numericPart}` : `${numericPart} ${code}`;
}

/**
 * Renderizza l'importo dell'inbox item nella sua valuta nativa, e — se diversa
 * da EUR — anche l'equivalente in EUR sotto. Se l'utente ha digitato la spesa
 * in valuta diversa dal conto (`original_*`), mostriamo come info ulteriore
 * "originale: 30 GBP" sotto l'importo principale.
 */
function ItemAmount({ item }: { item: InboxItem }) {
    if (item.amount === null) {
        return <span className="text-sm text-amber-600 font-medium">⚠ Importo mancante</span>;
    }
    const sign = item.type === 'income' ? '+' : '−';
    const amountValue = Math.abs(parseFloat(item.amount));
    const currency = item.currency_code ?? 'EUR';
    const colorClass = item.type === 'income'
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';

    const showEurEquivalent = currency !== 'EUR' && item.amount_base !== null;
    const showOriginal = item.original_amount !== null && item.original_currency_code && item.original_currency_code !== currency;

    return (
        <div className="flex flex-col items-end leading-tight">
            <span className={clsx('text-base font-bold whitespace-nowrap', colorClass)}>
                {sign}{formatAmountWithCurrency(amountValue, currency)}
            </span>
            {showEurEquivalent && (
                <span className="text-xs text-slate-500 dark:text-slate-400">
                    ≈ {sign}{formatCurrency(Math.abs(parseFloat(item.amount_base!)))}
                </span>
            )}
            {showOriginal && (
                <span className="text-xs text-slate-400 dark:text-slate-500" title="Importo originale digitato">
                    orig. {formatAmountWithCurrency(Math.abs(parseFloat(item.original_amount!)), item.original_currency_code)}
                </span>
            )}
        </div>
    );
}

// -------------------------------------------------------------------------
// Form di modifica inline
// -------------------------------------------------------------------------

interface EditFormProps {
    item: InboxItem;
    accounts: Account[];
    categories: Category[];
    onClose: () => void;
}

function EditForm({ item, accounts, categories, onClose }: EditFormProps) {
    const { data, setData, put, processing, errors } = useForm({
        amount: item.amount ?? '',
        type: item.type ?? 'expense',
        description: item.description ?? '',
        transaction_date: item.transaction_date ?? '',
        category_id: item.category?.id?.toString() ?? '',
        account_id: item.account?.id?.toString() ?? '',
    });

    const selectableAccounts = useMemo(
        () => accountsForTransactionType(accounts, data.type, { keepAccountId: item.account?.id }),
        [accounts, data.type, item.account?.id],
    );

    useEffect(() => {
        const nextAccountId = resolveTransactionAccountId(selectableAccounts, data.account_id);
        if (nextAccountId !== data.account_id) {
            setData('account_id', nextAccountId);
        }
    }, [selectableAccounts, data.account_id, setData]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(route('inbox.update', item.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <form onSubmit={submit} className="mt-4 space-y-3 border-t border-slate-200 dark:border-slate-700 pt-4">
            {/* Toggle Entrata / Uscita */}
            <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Tipo</label>
                <div className="flex rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden w-fit">
                    <button
                        type="button"
                        onClick={() => setData('type', 'expense')}
                        className={clsx(
                            'px-4 py-1.5 text-sm font-medium transition-colors',
                            data.type === 'expense'
                                ? 'bg-rose-600 text-white'
                                : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-rose-50 dark:hover:bg-rose-900/20'
                        )}
                    >
                        💸 Uscita
                    </button>
                    <button
                        type="button"
                        onClick={() => setData('type', 'income')}
                        className={clsx(
                            'px-4 py-1.5 text-sm font-medium transition-colors',
                            data.type === 'income'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                        )}
                    >
                        📈 Entrata
                    </button>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                        Importo (€) <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={data.amount}
                        onChange={e => setData('amount', e.target.value)}
                        className={clsx(
                            'w-full px-3 py-2 rounded-lg border text-sm bg-white dark:bg-slate-800 dark:text-white',
                            errors.amount
                                ? 'border-red-400 focus:ring-red-300'
                                : 'border-slate-300 dark:border-slate-600 focus:ring-emerald-300'
                        )}
                        placeholder="0.00"
                    />
                    {errors.amount && <p className="mt-1 text-xs text-red-500">{errors.amount}</p>}
                </div>
                <div>
                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Data</label>
                    <input
                        type="date"
                        value={data.transaction_date}
                        onChange={e => setData('transaction_date', e.target.value)}
                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-800 dark:text-white focus:ring-emerald-300"
                    />
                </div>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Descrizione</label>
                <input
                    type="text"
                    value={data.description}
                    onChange={e => setData('description', e.target.value)}
                    className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-800 dark:text-white focus:ring-emerald-300"
                    placeholder="es. Pizza da Rosario"
                />
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Categoria</label>
                    <select
                        value={data.category_id}
                        onChange={e => setData('category_id', e.target.value)}
                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-800 dark:text-white"
                    >
                        <option value="">— Nessuna —</option>
                        {categories.map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Conto</label>
                    <select
                        value={data.account_id}
                        onChange={e => setData('account_id', e.target.value)}
                        className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-800 dark:text-white"
                    >
                        <option value="">— Nessuno —</option>
                        {selectableAccounts.map(a => (
                            <option key={a.id} value={a.id}>{a.name}</option>
                        ))}
                    </select>
                </div>
            </div>
            <div className="flex justify-end gap-2 pt-1">
                <button
                    type="button"
                    onClick={onClose}
                    className="px-3 py-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors"
                >
                    Annulla
                </button>
                <button
                    type="submit"
                    disabled={processing}
                    className="px-4 py-1.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                >
                    {processing ? 'Salvataggio...' : 'Salva'}
                </button>
            </div>
        </form>
    );
}

// -------------------------------------------------------------------------
// Modal di conferma con selezione conto/categoria
// -------------------------------------------------------------------------

interface ConfirmModalProps {
    item: InboxItem;
    accounts: Account[];
    categories: Category[];
    onClose: () => void;
}

function ConfirmModal({ item, accounts, categories, onClose }: ConfirmModalProps) {
    const selectableAccounts = useMemo(
        () => accountsForTransactionType(accounts, item.type, { keepAccountId: item.account?.id }),
        [accounts, item.type, item.account?.id],
    );

    // Precedence: (1) item's existing account, (2) single account auto-select, (3) empty for manual selection
    const defaultAccountId = item.account?.id?.toString()
        ?? (selectableAccounts.length === 1 ? selectableAccounts[0].id.toString() : '');

    const { data, setData, post, processing } = useForm({
        account_id: defaultAccountId,
        category_id: item.category?.id?.toString() ?? '',
    });

    useEffect(() => {
        const nextAccountId = resolveTransactionAccountId(selectableAccounts, data.account_id);
        if (nextAccountId !== data.account_id) {
            setData('account_id', nextAccountId);
        }
    }, [selectableAccounts, data.account_id, setData]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('inbox.confirm', item.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    // Filtra categorie per tipo della voce; mostra lista vuota se non ce ne sono di quel tipo
    const filteredCategories = categories.filter(c => c.type === item.type);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
                        ✓ Conferma voce
                    </h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xl leading-none"
                        aria-label="Chiudi"
                    >
                        ×
                    </button>
                </div>

                {/* Riepilogo voce */}
                <div className="rounded-xl bg-slate-50 dark:bg-slate-700/50 p-3 space-y-1">
                    <div className="flex items-start justify-between gap-3">
                        <span className="text-sm text-slate-600 dark:text-slate-400">
                            {item.description ?? item.raw_text ?? '(nessuna descrizione)'}
                        </span>
                        <ItemAmount item={item} />
                    </div>
                    {item.transaction_date && (
                        <p className="text-xs text-slate-500">{formatDate(item.transaction_date)}</p>
                    )}
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                            Conto <span className="text-slate-400">(opzionale — usa il predefinito se vuoto)</span>
                        </label>
                        <select
                            value={data.account_id}
                            onChange={e => setData('account_id', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">— Predefinito —</option>
                            {selectableAccounts.map(a => (
                                <option key={a.id} value={a.id}>{a.name} ({a.currency_code})</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                            Categoria <span className="text-slate-400">(opzionale)</span>
                        </label>
                        <select
                            value={data.category_id}
                            onChange={e => setData('category_id', e.target.value)}
                            className="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm bg-white dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">— Nessuna —</option>
                            {filteredCategories.map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="flex gap-2 pt-1">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                        >
                            Annulla
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="flex-1 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors"
                        >
                            {processing ? 'Conferma...' : '✓ Conferma'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// -------------------------------------------------------------------------
// Riga singola
// -------------------------------------------------------------------------

interface InboxRowProps {
    item: InboxItem;
    accounts: Account[];
    categories: Category[];
    forceEdit: boolean;
}

function InboxRow({ item, accounts, categories, forceEdit }: InboxRowProps) {
    // Se amount è null, il form si apre automaticamente (UX guidata)
    const [editing, setEditing] = useState(forceEdit || item.amount === null);
    const [showImage, setShowImage] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const isPending = item.status === 'draft' || item.status === 'needs_review';

    // Riaggancia lo stato editing se il server aggiorna amount a null
    useEffect(() => {
        if (item.amount === null && isPending) {
            setEditing(true);
        }
    }, [item.amount, isPending]);

    function reject() {
        if (window.confirm('Scartare questa voce?')) {
            router.post(route('inbox.reject', item.id), {}, { preserveScroll: true });
        }
    }

    function destroy() {
        if (window.confirm('Eliminare definitivamente questa voce?')) {
            router.delete(route('inbox.destroy', item.id), { preserveScroll: true });
        }
    }

    return (
        <>
            {showConfirmModal && (
                <ConfirmModal
                    item={item}
                    accounts={accounts}
                    categories={categories}
                    onClose={() => setShowConfirmModal(false)}
                />
            )}

            <div className={clsx(
                'rounded-xl border p-3 transition-colors sm:p-4',
                isPending
                    ? 'border-amber-200 bg-amber-50/50 dark:border-amber-800/50 dark:bg-amber-900/10'
                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'
            )}>
                {/* Header riga */}
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="flex flex-col gap-1 min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={item.status} />
                            <SourceBadge source={item.source} />
                        </div>
                        <p className="text-sm font-medium text-slate-800 dark:text-white truncate">
                            {item.description ?? item.raw_text ?? '(nessuna descrizione)'}
                        </p>
                        {item.transaction_date && (
                            <p className="text-xs text-slate-500">{formatDate(item.transaction_date)}</p>
                        )}
                        {/* Conto e categoria assegnati */}
                        {(item.account || item.category) && (
                            <div className="flex flex-wrap gap-2 mt-0.5">
                                {item.account && (
                                    <span className="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                        🏦 {item.account.name}
                                    </span>
                                )}
                                {item.category && (
                                    <span className="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                        🏷️ {item.category.name}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <ItemAmount item={item} />
                    </div>
                </div>

                {/* Dati AI estratti */}
                {item.ai_payload && isPending && (
                    <div className="mt-2 p-2 rounded-lg bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 text-xs text-sky-700 dark:text-sky-300">
                        <span className="font-semibold">🤖 AI ha estratto:</span>{' '}
                        {item.ai_payload.amt != null && <>€{item.ai_payload.amt}{' '}</>}
                        {item.ai_payload.shop && <>{item.ai_payload.shop}{' '}</>}
                        {item.ai_payload.dt && <>{item.ai_payload.dt}</>}
                    </div>
                )}

                {/* Immagine scontrino */}
                {item.image_path && (
                    <div className="mt-2">
                        <button
                            type="button"
                            onClick={() => setShowImage(v => !v)}
                            className="text-xs text-sky-600 dark:text-sky-400 underline"
                        >
                            {showImage ? 'Nascondi scontrino' : 'Mostra scontrino'}
                        </button>
                        {showImage && (
                            <img
                                src={route('inbox.image', item.id)}
                                alt="Scontrino"
                                className="mt-2 max-h-64 rounded-lg border border-slate-200 dark:border-slate-600 object-contain"
                                onError={e => {
                                    (e.target as HTMLImageElement).style.display = 'none';
                                }}
                            />
                        )}
                    </div>
                )}

                {/* Form di modifica */}
                {editing && (
                    <EditForm
                        item={item}
                        accounts={accounts}
                        categories={categories}
                        onClose={() => setEditing(false)}
                    />
                )}

                {/* Azioni */}
                {isPending && !editing && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                        >
                            ✏ Modifica
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowConfirmModal(true)}
                            disabled={item.amount === null}
                            title={item.amount === null ? "Inserisci l'importo prima di confermare" : undefined}
                            className={clsx(
                                'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                                item.amount !== null
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                    : 'bg-slate-200 text-slate-400 cursor-not-allowed dark:bg-slate-700 dark:text-slate-500'
                            )}
                        >
                            ✓ Conferma
                        </button>
                        <button
                            type="button"
                            onClick={reject}
                            className="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                        >
                            ✕ Scarta
                        </button>
                    </div>
                )}

                {item.status === 'confirmed' && (
                    <div className="mt-2 flex justify-end">
                        <button
                            type="button"
                            onClick={destroy}
                            className="text-xs text-slate-400 hover:text-red-500 transition-colors"
                        >
                            Elimina
                        </button>
                    </div>
                )}
            </div>
        </>
    );
}

// -------------------------------------------------------------------------
// Pagina principale
// -------------------------------------------------------------------------

export default function InboxIndex({
    items,
    accounts,
    categories,
    pendingCount,
    archiveCount,
    recentArchive,
    telegramLinked,
    telegramBotUsername,
}: Props) {
    const [confirmingAll, setConfirmingAll] = useState(false);
    const [rejectingAll, setRejectingAll] = useState(false);

    function confirmAll() {
        if (window.confirm(`Confermare tutte le ${pendingCount} voci in attesa? Verrà usato il conto predefinito per chi non ne ha uno assegnato.`)) {
            setConfirmingAll(true);
            router.post(route('inbox.confirm-all'), {}, {
                preserveScroll: true,
                onFinish: () => setConfirmingAll(false),
            });
        }
    }

    function rejectAll() {
        if (window.confirm(`Scartare tutte le ${pendingCount} voci in attesa? L'operazione non è reversibile.`)) {
            setRejectingAll(true);
            router.post(route('inbox.reject-all'), {}, {
                preserveScroll: true,
                onFinish: () => setRejectingAll(false),
            });
        }
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Inbox"
                    backLink={route('categories.index')}
                    hideSubtitleOnMobile
                    subtitle={
                        pendingCount > 0
                            ? `${pendingCount} voce${pendingCount !== 1 ? 'i' : ''} da verificare`
                            : 'Tutte le voci sono state verificate'
                    }
                />
            }
        >
            <Head title="Inbox" />

            <PageContent>
                <OrganizationHubNav active="inbox" />
                {pendingCount > 0 && (
                    <IndexInfoBanner
                        icon="⚠"
                        title={`${pendingCount} in attesa`}
                        description="Revisiona le voci prima di consultare i report."
                        variant="warning"
                        hideOnMobile={false}
                        actions={
                            <>
                                <button
                                    type="button"
                                    onClick={confirmAll}
                                    disabled={confirmingAll}
                                    className="min-w-0 flex-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50 sm:flex-none sm:px-4"
                                >
                                    {confirmingAll ? 'Conferma...' : '✓ Conferma tutte'}
                                </button>
                                <button
                                    type="button"
                                    onClick={rejectAll}
                                    disabled={rejectingAll}
                                    className="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700 sm:flex-none sm:px-4"
                                >
                                    {rejectingAll ? 'Scarto...' : '✕ Scarta tutte'}
                                </button>
                            </>
                        }
                    />
                )}

                {items.data.length === 0 ? (
                    <IndexListCard
                        isEmpty
                        empty={
                            <IndexEmptyList
                                icon="📥"
                                title="Inbox vuota"
                                description={
                                    telegramLinked
                                        ? 'Invia una spesa al bot Telegram: comparirà qui in attesa di conferma.'
                                        : 'Le spese inviate via Telegram appariranno qui in attesa di conferma.'
                                }
                                showCreateButton={false}
                            >
                                {telegramLinked ? (
                                    <div className="mt-4 space-y-3 text-left text-sm text-gray-600 dark:text-gray-300">
                                        {telegramBotUsername && (
                                            <p>
                                                Bot collegato: <strong>@{telegramBotUsername}</strong>
                                            </p>
                                        )}
                                        <p className="font-medium text-gray-800 dark:text-gray-100">Esempi di messaggio:</p>
                                        <ul className="list-disc space-y-1 pl-5">
                                            <li><code className="text-xs">15.50 Supermercato</code></li>
                                            <li><code className="text-xs">15 Pizza @Corrente #Alimentari</code></li>
                                            <li><code className="text-xs">+1500 Stipendio</code></li>
                                        </ul>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            Comandi utili: /lista, /saldo, /ultime
                                        </p>
                                    </div>
                                ) : (
                                    <a
                                        href={route('telegram.link.show')}
                                        className="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 transition-colors"
                                    >
                                        Collega Telegram →
                                    </a>
                                )}
                            </IndexEmptyList>
                        }
                    />
                ) : (
                    <div className="space-y-3">
                        {items.data.map(item => (
                            <InboxRow
                                key={item.id}
                                item={item}
                                accounts={accounts}
                                categories={categories}
                                forceEdit={false}
                            />
                        ))}
                    </div>
                )}

                {items.last_page > 1 && (
                    <Pagination
                        data={items}
                    />
                )}

                {archiveCount > 0 && (
                    <CardBox className="mt-4 overflow-hidden p-0">
                        <details className="group">
                            <summary className={clsx('flex cursor-pointer list-none items-center justify-between gap-2 text-sm font-medium text-slate-700 dark:text-slate-200', mobileFilterSummaryClass)}>
                                <span>Archivio ({archiveCount})</span>
                                <span className="hidden text-xs font-normal text-slate-500 dark:text-slate-400 sm:inline">
                                    Ultime voci confermate o scartate
                                </span>
                                <svg className="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>
                            <div className={clsx('space-y-2 border-t border-slate-100 dark:border-slate-700', mobileFilterBodyClass)}>
                                {recentArchive.map((item) => (
                                    <ArchiveRow key={item.id} item={item} />
                                ))}
                                {archiveCount > recentArchive.length && (
                                    <p className="text-center text-xs text-slate-500 dark:text-slate-400">
                                        Mostrate le ultime {recentArchive.length} di {archiveCount} voci in archivio.
                                    </p>
                                )}
                            </div>
                        </details>
                    </CardBox>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
