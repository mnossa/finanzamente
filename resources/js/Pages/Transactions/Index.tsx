import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import { type FormDataConvertible } from '@inertiajs/core';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import CardBox from '@/Components/CardBox';
import React, { useEffect, useState } from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import axios from 'axios';
import { filtersAnalytics, tx } from '@/utils/analytics';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    type: 'income' | 'expense';
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
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    tax_deduction_type: string | null;
    attachments_count: number;
    transfer_id: number | null;
    refund_id: number | null;
    has_refunds: boolean;
    total_refunded_amount: number;
    is_fully_refunded: boolean;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
    tags: Tag[];
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Filters {
    account_id?: string;
    category_id?: string;
    type?: string;
    from?: string;
    to?: string;
    tag_id?: string;
}

interface DebtCredit {
    id: number;
    description: string;
    type: 'debt' | 'credit';
}

interface IndexProps {
    transactions: PaginatedData<Transaction>;
    accounts: Array<{ id: number; name: string }>;
    categories: Category[];
    debtCredits: DebtCredit[];
    tags: Array<{ id: number; name: string; color: string | null }>;
    filters: Filters;
    activeImports: Array<{ id: number; status: string; rows_total: number; rows_imported: number; created_at: string }>;
}

// Sentinel: campo non modificato
const UNCHANGED = '__unchanged__';
// Sentinel: rimuovi collegamento (per campi nullable)
const REMOVE = '__remove__';

type TriState = '__unchanged__' | 'true' | 'false';

interface BulkEditState {
    category_id: string;      // UNCHANGED | REMOVE | '<number>'
    is_private: TriState;
    debt_credit_id: string;   // UNCHANGED | REMOVE | '<number>'
    is_tax_deductible: TriState;
    account_id: string;       // UNCHANGED | '<number>'
}

const defaultBulkEdit: BulkEditState = {
    category_id: UNCHANGED,
    is_private: UNCHANGED,
    debt_credit_id: UNCHANGED,
    is_tax_deductible: UNCHANGED,
    account_id: UNCHANGED,
};

function TriStateButton({
    label,
    value,
    onChange,
}: {
    label: string;
    value: TriState;
    onChange: (v: TriState) => void;
}) {
    return (
        <div className="space-y-1">
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{label}</p>
            <div className="inline-flex rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden">
                {([UNCHANGED, 'true', 'false'] as TriState[]).map((v) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => onChange(v)}
                        className={clsx(
                            'px-3 py-1.5 text-sm transition-colors',
                            value === v
                                ? 'bg-emerald-600 text-white'
                                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                        )}
                    >
                        {v === UNCHANGED ? '—' : v === 'true' ? 'Sì' : 'No'}
                    </button>
                ))}
            </div>
        </div>
    );
}

function BulkEditModal({
    open,
    count,
    categories,
    accounts,
    debtCredits,
    onClose,
    onConfirm,
}: {
    open: boolean;
    count: number;
    categories: Category[];
    accounts: Array<{ id: number; name: string }>;
    debtCredits: DebtCredit[];
    onClose: () => void;
    onConfirm: (state: BulkEditState) => void;
}) {
    const [state, setState] = React.useState<BulkEditState>(defaultBulkEdit);

    React.useEffect(() => {
        if (open) setState(defaultBulkEdit);
    }, [open]);

    if (!open) return null;

    const set = <K extends keyof BulkEditState>(key: K, val: BulkEditState[K]) =>
        setState((prev) => ({ ...prev, [key]: val }));

    const hasChanges =
        state.category_id !== UNCHANGED ||
        state.is_private !== UNCHANGED ||
        state.debt_credit_id !== UNCHANGED ||
        state.is_tax_deductible !== UNCHANGED ||
        state.account_id !== UNCHANGED;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/40"
                onClick={onClose}
            />
            <div className="relative z-10 w-full max-w-lg rounded-xl bg-white dark:bg-gray-800 shadow-xl">
                <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Modifica in massa — {count} transazioni
                    </h2>
                    <button
                        onClick={onClose}
                        className="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>

                <div className="p-6 space-y-5">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Solo i campi che modifichi verranno aggiornati. I campi su «—» rimarranno invariati per ogni transazione.
                    </p>

                    {/* Categoria */}
                    <div className="space-y-1">
                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</label>
                        <select
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            value={state.category_id}
                            onChange={(e) => set('category_id', e.target.value)}
                        >
                            <option value={UNCHANGED}>— Invariata —</option>
                            <option value={REMOVE}>Nessuna categoria (rimuovi)</option>
                            {['income' as const, 'expense' as const].map((type) => {
                                const list = categories.filter((c) => c.type === type);
                                if (!list.length) return null;
                                return (
                                    <optgroup key={type} label={type === 'income' ? 'Entrate' : 'Uscite'}>
                                        {list.map((c) => (
                                            <option key={c.id} value={String(c.id)}>
                                                {c.icon} {c.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                );
                            })}
                        </select>
                    </div>

                    {/* Privacy */}
                    <TriStateButton
                        label="Privata"
                        value={state.is_private}
                        onChange={(v) => set('is_private', v)}
                    />

                    {/* Deducibilità */}
                    <TriStateButton
                        label="Detraibile fiscalmente"
                        value={state.is_tax_deductible}
                        onChange={(v) => set('is_tax_deductible', v)}
                    />

                    {/* Debito/Credito */}
                    <div className="space-y-1">
                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Debito / Credito</label>
                        <select
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            value={state.debt_credit_id}
                            onChange={(e) => set('debt_credit_id', e.target.value)}
                        >
                            <option value={UNCHANGED}>— Invariato —</option>
                            <option value={REMOVE}>Rimuovi collegamento</option>
                            {debtCredits.map((dc) => (
                                <option key={dc.id} value={String(dc.id)}>
                                    {dc.type === 'debt' ? '🔴' : '🟢'} {dc.description}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Conto */}
                    <div className="space-y-1">
                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Conto</label>
                        <select
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            value={state.account_id}
                            onChange={(e) => set('account_id', e.target.value)}
                        >
                            <option value={UNCHANGED}>— Invariato —</option>
                            {accounts.map((a) => (
                                <option key={a.id} value={String(a.id)}>
                                    {a.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex justify-end gap-3 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Annulla
                    </button>
                    <button
                        type="button"
                        disabled={!hasChanges}
                        onClick={() => onConfirm(state)}
                        className={clsx(
                            'rounded-md px-4 py-2 text-sm font-medium text-white transition-colors',
                            hasChanges
                                ? 'bg-emerald-600 hover:bg-emerald-700'
                                : 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed',
                        )}
                    >
                        Applica modifiche
                    </button>
                </div>
            </div>
        </div>
    );
}


function TransactionRow({ transaction, onDeleteClick, isSelected, onToggleSelect }: {
    transaction: Transaction;
    onDeleteClick: (id: number, description: string) => void;
    isSelected: boolean;
    onToggleSelect: (id: number) => void;
}) {
    const isIncome = transaction.amount > 0;
    const isTransfer = transaction.transfer_id !== null;
    const isRefund = transaction.refund_id !== null;
    const hasRefunds = transaction.has_refunds;

    return (
        <div className={clsx(
            "group flex items-center border-b border-gray-100 py-3 last:border-0 -mx-4 px-3 sm:px-4 transition-colors",
            isSelected ? "bg-emerald-50 dark:bg-emerald-900/20" : "hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
        )}>
            {/* Checkbox */}
            <input
                type="checkbox"
                checked={isSelected}
                onChange={() => onToggleSelect(transaction.id)}
                className="mr-2 h-4 w-4 shrink-0 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 cursor-pointer"
                onClick={(e) => e.stopPropagation()}
            />

            {/* Icona categoria */}
            <div
                className="mr-3 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base"
                style={{
                    backgroundColor: isTransfer
                        ? '#f59e0b20'
                        : isRefund
                          ? '#3b82f620'
                          : transaction.category?.color
                            ? `${transaction.category.color}20`
                            : isIncome
                              ? '#22c55e20'
                              : '#ef444420',
                }}
            >
                {isTransfer ? '🔄' : isRefund ? '💸' : transaction.category?.icon || (isIncome ? '💰' : '💸')}
            </div>

            {/* Corpo — link su tutta la riga su mobile */}
            <Link
                href={route('transactions.show', transaction.id)}
                className="min-w-0 flex-1"
            >
                <p className="truncate text-sm font-medium text-gray-900 dark:text-white">
                    {transaction.description || transaction.category?.name || 'Transazione'}
                    {transaction.is_private && (
                        <span className="ml-1.5 inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                            🔒
                        </span>
                    )}
                    {transaction.is_tax_deductible && (
                        <span className="ml-1.5 inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                            📋
                        </span>
                    )}
                </p>
                <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                    {transaction.account.name} · {formatDate(transaction.date)}
                    {isTransfer && <span className="ml-1 text-amber-500">· Trasferimento</span>}
                    {isRefund && <span className="ml-1 text-blue-500">· Rimborso</span>}
                    {hasRefunds && (
                        <span className={clsx('ml-1', transaction.is_fully_refunded ? 'text-green-500' : 'text-amber-500')}>
                            · {transaction.is_fully_refunded ? '✓ Rimborsato' : '◐ Parz. rimborsato'}
                        </span>
                    )}
                </p>
                {transaction.tags && transaction.tags.length > 0 && (
                    <div className="mt-1 flex flex-wrap gap-1">
                        {transaction.tags.map((tag) => (
                            <span
                                key={tag.id}
                                className="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                style={{
                                    backgroundColor: tag.color ? `${tag.color}20` : '#e5e7eb',
                                    color: tag.color || '#374151',
                                }}
                            >
                                {tag.name}
                            </span>
                        ))}
                    </div>
                )}
            </Link>

            {/* Importo + azioni */}
            <div className="ml-2 flex shrink-0 items-center gap-1 sm:gap-2">
                <p className={clsx('text-base font-semibold tabular-nums', isIncome ? 'text-green-500' : 'text-red-500')}>
                    {isIncome ? '+' : ''}
                    {formatCurrency(transaction.amount, transaction.account.currency_code)}
                </p>
                {/* Azioni — visibili solo su desktop o al hover */}
                <div className="hidden sm:flex items-center gap-1">
                    <Link
                        href={route('transactions.show', transaction.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={16} />
                    </Link>
                    <Link
                        href={route('transactions.edit', transaction.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                    >
                        <PencilIcon size={16} />
                    </Link>
                    <button
                        onClick={() => onDeleteClick(transaction.id, transaction.description || transaction.category?.name || 'questa transazione')}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </button>
                </div>
                {/* Freccia chevron su mobile */}
                <span className="sm:hidden text-gray-300 dark:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </span>
            </div>
        </div>
    );
}


export default function Index({
    transactions,
    accounts,
    categories,
    debtCredits,
    tags,
    filters,
    activeImports,
}: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());
    const [bulkDeleteDialogOpen, setBulkDeleteDialogOpen] = React.useState(false);
    const [bulkEditOpen, setBulkEditOpen] = React.useState(false);

    // Polling import attivi: aggiorna il banner e ricarica la pagina quando terminano
    type ActiveImport = { id: number; status: string; rows_total: number; rows_imported: number; created_at: string };
    const [pendingImports, setPendingImports] = useState<ActiveImport[]>(activeImports);

    useEffect(() => {
        // Se non ci sono import attivi non serve polling
        if (activeImports.length === 0) {
            setPendingImports([]);
            return;
        }

        setPendingImports(activeImports);

        let stopped = false;
        let timerId: ReturnType<typeof setTimeout>;

        const poll = async () => {
            if (stopped) return;
            try {
                const resp = await axios.get<{ activeImports: ActiveImport[] }>(
                    route('transactions.import.status'),
                    { headers: { Accept: 'application/json' } },
                );
                if (stopped) return;
                const still = resp.data.activeImports;
                setPendingImports(still);
                if (still.length === 0) {
                    // Job terminato: ricarica solo le props necessarie
                    router.reload({ only: ['transactions', 'activeImports'] });
                    return;
                }
            } catch {
                // noop — riprova al prossimo tick
            }
            if (!stopped) timerId = setTimeout(poll, 4000);
        };

        timerId = setTimeout(poll, 4000);
        return () => {
            stopped = true;
            clearTimeout(timerId);
        };
    // activeImports.length: usa length non il riferimento per evitare re-fire inutili
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeImports.length]);

    const allIds = transactions.data.map((t) => t.id);
    const isAllSelected = allIds.length > 0 && allIds.every((id) => selectedIds.has(id));
    const isIndeterminate = selectedIds.size > 0 && !isAllSelected;

    const toggleSelectAll = () => {
        if (isAllSelected) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(allIds));
        }
    };

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('transactions.destroy', deleteTarget.id), {
                onSuccess: () => tx.deleted(),
            });
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleBulkDelete = () => {
        setBulkDeleteDialogOpen(true);
    };

    const handleConfirmBulkDelete = () => {
        router.delete(route('transactions.bulk-destroy'), {
            data: { ids: Array.from(selectedIds) },
            onFinish: () => {
                setSelectedIds(new Set());
            },
        });
        setBulkDeleteDialogOpen(false);
    };

    const handleCancelBulkDelete = () => {
        setBulkDeleteDialogOpen(false);
    };

    const handleBulkEdit = (state: BulkEditState) => {
        const payload: Record<string, FormDataConvertible> = { ids: Array.from(selectedIds) };

        if (state.category_id !== UNCHANGED) {
            payload.category_id = state.category_id === REMOVE ? null : Number(state.category_id);
        }
        if (state.is_private !== UNCHANGED) {
            payload.is_private = state.is_private === 'true';
        }
        if (state.debt_credit_id !== UNCHANGED) {
            payload.debt_credit_id = state.debt_credit_id === REMOVE ? null : Number(state.debt_credit_id);
        }
        if (state.is_tax_deductible !== UNCHANGED) {
            payload.is_tax_deductible = state.is_tax_deductible === 'true';
        }
        if (state.account_id !== UNCHANGED) {
            payload.account_id = Number(state.account_id);
        }

        router.patch(route('transactions.bulk-update'), payload, {
            onSuccess: () => {
                setSelectedIds(new Set());
                setBulkEditOpen(false);
            },
        });
    };

    const handleFilterChange = (key: string, value: string) => {
        if (value) {
            const filterMap: Record<string, Parameters<typeof filtersAnalytics.applied>[0]> = {
                account_id: 'account',
                category_id: 'category',
                type: 'type',
                from: 'date_from',
                to: 'date_to',
                tag_id: 'tag',
            };
            if (filterMap[key]) filtersAnalytics.applied(filterMap[key]);
        }
        router.get(
            route('transactions.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    const clearFilters = () => {
        if (Object.values(filters).some(Boolean)) filtersAnalytics.cleared();
        router.get(route('transactions.index'));
    };

    const hasFilters = Object.values(filters).some((v) => v);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Transazioni"
                    actions={
                        <div className="flex items-center gap-2">
                            <LinkButton
                                href={route('transactions.import')}
                                variant="secondary"
                                size="sm"
                            >
                                Importa
                            </LinkButton>
                            <LinkButton
                                href={route('transactions.create')}
                                icon={<PlusIcon />}
                                size="sm"
                                className="hidden sm:inline-flex"
                            >
                                Nuova Transazione
                            </LinkButton>
                        </div>
                    }
                />
            }
        >
            <Head title="Transazioni" />

            <BulkEditModal
                open={bulkEditOpen}
                count={selectedIds.size}
                categories={categories}
                accounts={accounts}
                debtCredits={debtCredits}
                onClose={() => setBulkEditOpen(false)}
                onConfirm={handleBulkEdit}
            />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare ${deleteTarget.description}?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <ConfirmDeleteDialog
                open={bulkDeleteDialogOpen}
                title="Conferma eliminazione multipla"
                description={`Sei sicuro di voler eliminare ${selectedIds.size} transazioni selezionate? Questa azione non può essere annullata.`}
                confirmLabel={`Elimina ${selectedIds.size} transazioni`}
                cancelLabel="Annulla"
                onConfirm={handleConfirmBulkDelete}
                onCancel={handleCancelBulkDelete}
            />

            <PageContent maxWidth="7xl">
                    {/* Intro decorativa — solo su desktop */}
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge
                                label="Registro transazioni"
                                icon={<span className="text-sm leading-none">📒</span>}
                            />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Filtra, seleziona e gestisci i movimenti con operazioni singole o massive.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Banner importazioni in corso */}
                    {pendingImports.length > 0 && (
                        <div className="mb-4 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            <svg className="h-5 w-5 shrink-0 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <span>
                                {pendingImports.length === 1
                                    ? `Importazione in corso (${pendingImports[0].rows_total} transazioni)\u2026 Riceverai una notifica al termine.`
                                    : `${pendingImports.length} importazioni in corso\u2026 Riceverai una notifica al termine.`}
                            </span>
                        </div>
                    )}

                    {/* Filtri */}
                    <CardBox className="overflow-hidden p-0 shadow-sm">
                        {/* Header filtri — sempre visibile */}
                        <details className="group" {...(hasFilters ? { open: true } : {})}>
                            <summary data-testid="filter-summary" className="flex cursor-pointer select-none items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span className="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                    </svg>
                                    Filtri
                                    {hasFilters && (
                                        <span className="inline-flex items-center justify-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                                            {Object.values(filters).filter(Boolean).length}
                                        </span>
                                    )}
                                </span>
                                <svg className="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>

                            {/* Corpo filtri — 2 colonne su mobile, flex-wrap su sm+ */}
                            <div className="border-t border-gray-100 px-4 pb-4 pt-3 dark:border-gray-700">
                                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
                                    <select
                                        className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={filters.account_id || ''}
                                        onChange={(e) => handleFilterChange('account_id', e.target.value)}
                                    >
                                        <option value="">Tutti i conti</option>
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={filters.category_id || ''}
                                        onChange={(e) => handleFilterChange('category_id', e.target.value)}
                                    >
                                        <option value="">Tutte le categorie</option>
                                        <option value="__none__">— Senza categoria</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.icon} {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={filters.type || ''}
                                        onChange={(e) => handleFilterChange('type', e.target.value)}
                                    >
                                        <option value="">Tipo</option>
                                        <option value="income">Entrate</option>
                                        <option value="expense">Uscite</option>
                                    </select>
                                    <input
                                        type="date"
                                        className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={filters.from || ''}
                                        onChange={(e) => handleFilterChange('from', e.target.value)}
                                        title="Da"
                                    />
                                    <input
                                        type="date"
                                        className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={filters.to || ''}
                                        onChange={(e) => handleFilterChange('to', e.target.value)}
                                        title="A"
                                    />
                                    {tags.length > 0 && (
                                        <select
                                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={filters.tag_id || ''}
                                            onChange={(e) => handleFilterChange('tag_id', e.target.value)}
                                        >
                                            <option value="">Tutti i tag</option>
                                            {tags.map((tag) => (
                                                <option key={tag.id} value={tag.id}>
                                                    {tag.name}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                    {hasFilters && (
                                        <button
                                            onClick={clearFilters}
                                            className="col-span-2 sm:col-span-1 text-sm font-medium text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 py-2 sm:py-0"
                                        >
                                            Pulisci filtri
                                        </button>
                                    )}
                                </div>
                            </div>
                        </details>
                    </CardBox>

                    {/* Lista Transazioni */}
                    <CardBox className="overflow-hidden shadow-sm">
                        {transactions.data.length > 0 ? (
                            <>
                                {/* Barra selezione multipla */}
                                <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                                    <label className="flex items-center gap-2 cursor-pointer select-none">
                                        <input
                                            type="checkbox"
                                            checked={isAllSelected}
                                            ref={(el) => {
                                                if (el) el.indeterminate = isIndeterminate;
                                            }}
                                            onChange={toggleSelectAll}
                                            className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
                                        />
                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                            {selectedIds.size > 0
                                                ? `${selectedIds.size} selezionate`
                                                : 'Seleziona tutto'}
                                        </span>
                                    </label>
                                    {selectedIds.size > 0 && (
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => setBulkEditOpen(true)}
                                                className="flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                            >
                                                <PencilIcon size={15} />
                                                Modifica selezionate
                                            </button>
                                            <button
                                                onClick={handleBulkDelete}
                                                className="flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                            >
                                                <TrashIcon size={15} />
                                                Elimina selezionate
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <div className="p-4">
                                    {transactions.data.map((transaction) => (
                                        <TransactionRow
                                            key={transaction.id}
                                            transaction={transaction}
                                            onDeleteClick={openDeleteDialog}
                                            isSelected={selectedIds.has(transaction.id)}
                                            onToggleSelect={toggleSelect}
                                        />
                                    ))}
                                </div>
                                <Pagination data={transactions} />
                            </>
                        ) : (
                            <EmptyState
                                icon="💸"
                                title="Nessuna transazione trovata"
                                description={
                                    hasFilters
                                        ? 'Prova a modificare i filtri di ricerca.'
                                        : 'Registra la tua prima transazione per iniziare.'
                                }
                                createUrl={!hasFilters ? route('transactions.create') : undefined}
                                createLabel="Nuova Transazione"
                                showCreateButton={!hasFilters}
                            />
                        )}
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
