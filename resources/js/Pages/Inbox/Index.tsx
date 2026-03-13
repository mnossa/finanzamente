import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import EmptyState from '@/Components/EmptyState';
import { Head, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import { PageProps } from '@/types';
import React, { useState, useEffect } from 'react';

// -------------------------------------------------------------------------
// Types
// -------------------------------------------------------------------------

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface InboxItem {
    id: number;
    status: 'draft' | 'needs_review' | 'confirmed' | 'rejected';
    source: 'telegram_text' | 'telegram_photo' | 'manual';
    raw_text: string | null;
    image_path: string | null;
    ai_payload: { amt: number | null; shop: string | null; dt: string | null } | null;
    amount: string | null;
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

interface Props extends PageProps {
    items: PaginatedData<InboxItem>;
    accounts: Account[];
    categories: Category[];
    pendingCount: number;
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
        description: item.description ?? '',
        transaction_date: item.transaction_date ?? '',
        category_id: item.category?.id?.toString() ?? '',
        account_id: item.account?.id?.toString() ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(route('inbox.update', item.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    }

    return (
        <form onSubmit={submit} className="mt-4 space-y-3 border-t border-slate-200 dark:border-slate-700 pt-4">
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
                        {accounts.map(a => (
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
    const isPending = item.status === 'draft' || item.status === 'needs_review';

    // Riaggancia lo stato editing se il server aggiorna amount a null
    useEffect(() => {
        if (item.amount === null && isPending) {
            setEditing(true);
        }
    }, [item.amount, isPending]);

    function confirmItem() {
        router.post(route('inbox.confirm', item.id), {}, { preserveScroll: true });
    }

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
        <div className={clsx(
            'p-4 rounded-xl border transition-colors',
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
                </div>

                <div className="flex items-center gap-2">
                    {item.amount !== null ? (
                        <span className="text-lg font-bold text-rose-600 dark:text-rose-400">
                            -{formatCurrency(Math.abs(parseFloat(item.amount)))}
                        </span>
                    ) : (
                        <span className="text-sm text-amber-600 font-medium">⚠ Importo mancante</span>
                    )}
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
                        onClick={confirmItem}
                        disabled={item.amount === null}
                        title={item.amount === null ? 'Inserisci l\'importo prima di confermare' : undefined}
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
    );
}

// -------------------------------------------------------------------------
// Pagina principale
// -------------------------------------------------------------------------

export default function InboxIndex({ items, accounts, categories, pendingCount }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Inbox"
                    subtitle={
                        pendingCount > 0
                            ? `${pendingCount} voce${pendingCount !== 1 ? 'i' : ''} da verificare`
                            : 'Tutte le voci sono state verificate'
                    }
                />
            }
        >
            <Head title="Inbox" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto space-y-4">

                {/* Banner se ci sono voci da verificare */}
                {pendingCount > 0 && (
                    <div className="flex items-center gap-3 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <span className="text-amber-500 text-xl">⚠</span>
                        <p className="text-sm text-amber-800 dark:text-amber-200">
                            Hai <strong>{pendingCount}</strong> voce{pendingCount !== 1 ? 'i' : ''} in attesa di verifica.
                            Revisionale prima che vengano conteggiate nei report.
                        </p>
                    </div>
                )}

                {items.data.length === 0 ? (
                    <CardBox>
                        <EmptyState
                            icon="📥"
                            title="Inbox vuota"
                            description="Le spese inviate via Telegram appariranno qui in attesa di conferma."
                            showCreateButton={false}
                        >
                            <a
                                href={route('telegram.link.show')}
                                className="mt-4 inline-flex items-center px-4 py-2 rounded-lg bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 transition-colors"
                            >
                                Collega Telegram →
                            </a>
                        </EmptyState>
                    </CardBox>
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
            </div>
        </AuthenticatedLayout>
    );
}
