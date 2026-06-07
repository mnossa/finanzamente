import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions, IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TransactionListRow from '@/Components/TransactionListRow';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import { type FormDataConvertible } from '@inertiajs/core';
import clsx from 'clsx';
import { Pagination } from '@/Components/Pagination';
import CardBox from '@/Components/CardBox';
import React, { useEffect, useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import axios from 'axios';
import { filtersAnalytics, tx } from '@/utils/analytics';
import { formatCurrency } from '@/utils/format';

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
    recurring_transaction_id: number | null;
    recurring_summary: {
        id: number;
        description: string | null;
        frequency: string;
    } | null;
    investment_id: number | null;
    is_investment: boolean;
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
    is_tax_deductible?: string;
    description?: string;
    description_regex?: string;
    amount_min?: string;
    amount_max?: string;
    currency_code?: string;
}

/** Parametri ammessi per redirect verso indice (allineati al backend). */
type IndexReturnQuery = Record<string, string | number>;

function buildReturnIndexQuery(filters: Filters, currentPage: number, includePage = true): IndexReturnQuery {
    const q: IndexReturnQuery = {};
    if (filters.account_id) {
        q.account_id = filters.account_id;
    }
    if (filters.category_id !== undefined && filters.category_id !== '') {
        q.category_id = filters.category_id === '__none__' ? '__none__' : Number(filters.category_id);
    }
    if (filters.type) {
        q.type = filters.type;
    }
    if (filters.from) {
        q.from = filters.from;
    }
    if (filters.to) {
        q.to = filters.to;
    }
    if (filters.tag_id) {
        q.tag_id = Number(filters.tag_id);
    }
    if (filters.is_tax_deductible !== undefined && filters.is_tax_deductible !== '') {
        q.is_tax_deductible = filters.is_tax_deductible;
    }
    if (filters.description) {
        q.description = filters.description;
    }
    if (filters.description_regex === '1') {
        q.description_regex = '1';
    }
    if (filters.amount_min) {
        q.amount_min = filters.amount_min;
    }
    if (filters.amount_max) {
        q.amount_max = filters.amount_max;
    }
    if (filters.currency_code) {
        q.currency_code = filters.currency_code;
    }
    if (includePage && currentPage > 1) {
        q.page = currentPage;
    }

    return q;
}

function returnIndexQueryJson(filters: Filters, currentPage: number, includePage = false): string {
    const payload = buildReturnIndexQuery(filters, currentPage, includePage);

    return Object.keys(payload).length === 0 ? '' : JSON.stringify(payload);
}

function filtersToQueryParams(f: Filters): Record<string, string> {
    const o: Record<string, string> = {};
    (Object.entries(f) as [keyof Filters, string | undefined][]).forEach(([k, v]) => {
        if (k === 'description_regex') {
            return;
        }
        if (v !== undefined && v !== '') {
            o[k] = v;
        }
    });
    if (f.description_regex === '1' && f.description) {
        o.description_regex = '1';
    }

    return o;
}

function filtersQuerySignature(f: Filters): string {
    const params = filtersToQueryParams(f);
    const keys = Object.keys(params).sort();
    const sorted: Record<string, string> = {};
    keys.forEach((k) => {
        sorted[k] = params[k];
    });

    return JSON.stringify(sorted);
}

function countActiveFilters(f: Filters): number {
    return (Object.entries(f) as [keyof Filters, string | undefined][]).filter(([k, v]) => {
        if (k === 'description_regex') {
            return false;
        }

        return v !== undefined && v !== '';
    }).length;
}

function computeNextFilters(filters: Filters, categories: Category[], key: string, value: string): Filters {
    const next: Filters = { ...filters };
    if (!value) {
        delete (next as Record<string, unknown>)[key];
    } else {
        (next as Record<string, unknown>)[key] = value;
    }
    if (key === 'category_id' && value && value !== '__none__') {
        const cat = categories.find((c) => String(c.id) === value);
        if (cat) {
            next.type = cat.type;
        }
    }
    if (key === 'type' && value) {
        const cid = next.category_id;
        if (cid && cid !== '__none__') {
            const cat = categories.find((c) => String(c.id) === String(cid));
            if (cat && cat.type !== value) {
                delete next.category_id;
            }
        }
    }

    return next;
}

interface DebtCredit {
    id: number;
    description: string;
    counterparty: string;
    type: 'debt' | 'credit';
}

function formatDebtCreditOptionLabel(dc: DebtCredit): string {
    const icon = dc.type === 'debt' ? '🔴' : '🟢';
    const party = dc.counterparty.trim();
    const desc = dc.description?.trim() ?? '';
    if (desc && desc !== party) {
        return `${icon} ${party} — ${desc}`;
    }

    return `${icon} ${party}`;
}

interface IndexProps {
    transactions: PaginatedData<Transaction>;
    accounts: Array<{ id: number; name: string }>;
    categories: Category[];
    debtCredits: DebtCredit[];
    tags: Array<{ id: number; name: string; color: string | null }>;
    filters: Filters;
    activeImports: Array<{ id: number; status: string; rows_total: number; rows_imported: number; created_at: string }>;
    summary: { count: number; income: number; expenses: number; net: number };
    currencies: Array<{ code: string; name: string; symbol: string | null }>;
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
    tag_ids: string[];
    new_tag_names: string;
}

const defaultBulkEdit: BulkEditState = {
    category_id: UNCHANGED,
    is_private: UNCHANGED,
    debt_credit_id: UNCHANGED,
    is_tax_deductible: UNCHANGED,
    account_id: UNCHANGED,
    tag_ids: [],
    new_tag_names: '',
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
    tags,
    onClose,
    onConfirm,
}: {
    open: boolean;
    count: number;
    categories: Category[];
    accounts: Array<{ id: number; name: string }>;
    debtCredits: DebtCredit[];
    tags: Array<{ id: number; name: string; color: string | null }>;
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
        state.account_id !== UNCHANGED ||
        state.tag_ids.length > 0 ||
        state.new_tag_names.trim() !== '';

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto p-4">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/40"
                onClick={onClose}
            />
            <div className="flex min-h-full items-center justify-center">
            <div
                className="relative z-10 flex w-full max-w-lg max-h-[calc(100dvh-2rem)] flex-col rounded-xl bg-white dark:bg-gray-800 shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="bulk-edit-modal-title"
            >
                <div className="flex shrink-0 items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h2
                        id="bulk-edit-modal-title"
                        className="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Modifica in massa — {count} transazioni
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        aria-label="Chiudi"
                    >
                        ✕
                    </button>
                </div>

                <div className="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain p-6 scrollbar-hide">
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
                                    {formatDebtCreditOptionLabel(dc)}
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

                    <div className="space-y-1">
                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Tag esistenti</label>
                        <select
                            multiple
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            value={state.tag_ids}
                            onChange={(e) => set('tag_ids', Array.from(e.target.selectedOptions).map((o) => o.value))}
                        >
                            {tags.map((tag) => (
                                <option key={tag.id} value={String(tag.id)}>
                                    {tag.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1">
                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Nuovi tag (separati da virgola)</label>
                        <input
                            type="text"
                            value={state.new_tag_names}
                            onChange={(e) => set('new_tag_names', e.target.value)}
                            placeholder="es. lavoro, rimborsi"
                            className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                    </div>
                </div>

                <div className="flex shrink-0 justify-end gap-3 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
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
    summary,
    currencies,
}: IndexProps) {
    const summaryCurrency = filters.currency_code || 'EUR';

    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{
        id: number;
        description: string;
        isInvestment: boolean;
    } | null>(null);
    const [selectedIds, setSelectedIds] = React.useState<Set<number>>(new Set());
    const [bulkDeleteDialogOpen, setBulkDeleteDialogOpen] = React.useState(false);
    const [bulkEditOpen, setBulkEditOpen] = React.useState(false);
    const [draftFilters, setDraftFilters] = React.useState<Filters>({ ...filters });

    useEffect(() => {
        setDraftFilters({ ...filters });
    }, [filters]);

    const returnQuery = useMemo(
        () => buildReturnIndexQuery(filters, transactions.current_page),
        [filters, transactions.current_page],
    );

    const visibleCategories = useMemo(() => {
        const base = draftFilters.type ? categories.filter((c) => c.type === draftFilters.type) : categories;
        if (!draftFilters.category_id || draftFilters.category_id === '__none__') {
            return base;
        }
        const selected = categories.find((c) => String(c.id) === String(draftFilters.category_id));
        if (!selected) {
            return base;
        }
        if (base.some((c) => c.id === selected.id)) {
            return base;
        }

        return [...base, selected];
    }, [categories, draftFilters.type, draftFilters.category_id]);

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

    const openDeleteDialog = (target: { id: number; description: string; isInvestment: boolean }) => {
        if (target.isInvestment) {
            return;
        }
        setDeleteTarget(target);
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            const rq = returnIndexQueryJson(filters, transactions.current_page);
            router.delete(route('transactions.destroy', deleteTarget.id), {
                ...(rq ? { data: { return_index_query: rq } } : {}),
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

    const selectedInvestmentCount = transactions.data.filter(
        (transaction) => selectedIds.has(transaction.id) && transaction.is_investment,
    ).length;

    const handleBulkDelete = () => {
        if (selectedInvestmentCount > 0) {
            return;
        }
        setBulkDeleteDialogOpen(true);
    };

    const handleConfirmBulkDelete = () => {
        const rq = returnIndexQueryJson(filters, transactions.current_page);
        router.delete(route('transactions.bulk-destroy'), {
            data: {
                ids: Array.from(selectedIds),
                ...(rq ? { return_index_query: rq } : {}),
            },
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
        if (state.tag_ids.length > 0) {
            payload.tag_ids = state.tag_ids.map((id) => Number(id));
        }
        if (state.new_tag_names.trim() !== '') {
            payload.new_tag_names = state.new_tag_names
                .split(',')
                .map((name) => name.trim())
                .filter(Boolean);
        }

        const rq = returnIndexQueryJson(filters, transactions.current_page);
        if (rq) {
            payload.return_index_query = rq;
        }

        router.patch(route('transactions.bulk-update'), payload, {
            onSuccess: () => {
                setSelectedIds(new Set());
                setBulkEditOpen(false);
            },
        });
    };

    const updateDraftFilter = (key: string, value: string) => {
        setDraftFilters((prev) => computeNextFilters(prev, categories, key, value));
    };

    const hasFilters = countActiveFilters(filters) > 0;
    const hasDraftFilters = countActiveFilters(draftFilters) > 0;
    const hasPendingFilterChanges = filtersQuerySignature(draftFilters) !== filtersQuerySignature(filters);

    const applyFilters = () => {
        const filterMap: Record<string, Parameters<typeof filtersAnalytics.applied>[0]> = {
            account_id: 'account',
            category_id: 'category',
            type: 'type',
            from: 'date_from',
            to: 'date_to',
            tag_id: 'tag',
            description: 'description',
            amount_min: 'amount',
            amount_max: 'amount',
            currency_code: 'type',
        };
        (Object.keys(filterMap) as Array<keyof typeof filterMap>).forEach((key) => {
            const draftVal = draftFilters[key as keyof Filters];
            const appliedVal = filters[key as keyof Filters];
            if (draftVal && draftVal !== appliedVal && filterMap[key]) {
                filtersAnalytics.applied(filterMap[key]);
            }
        });

        router.get(route('transactions.index'), filtersToQueryParams(draftFilters), {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        if (hasFilters || hasDraftFilters) {
            filtersAnalytics.cleared();
        }
        setDraftFilters({});
        router.get(route('transactions.index'));
    };

    const exportHref = useMemo(
        () => route('transactions.export', filtersToQueryParams(filters)),
        [filters],
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Transazioni"
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton
                                href={route('transactions.import')}
                                variant="secondary"
                                size="sm"
                            >
                                Importa
                            </LinkButton>
                            <LinkButton native href={exportHref} variant="secondary" size="sm">
                                Esporta
                            </LinkButton>
                            <LinkButton
                                href={route('transactions.create')}
                                icon={<PlusIcon />}
                                size="sm"
                            >
                                Nuova Transazione
                            </LinkButton>
                        </IndexPageHeaderActions>
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
                tags={tags}
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

                    <IndexPageMobileToolbar>
                        <LinkButton
                            href={route('transactions.import')}
                            variant="secondary"
                            size="sm"
                            className="w-full justify-center sm:w-auto"
                        >
                            Importa
                        </LinkButton>
                        <LinkButton
                            native
                            data-testid="transactions-export"
                            href={exportHref}
                            variant="secondary"
                            size="sm"
                            className="w-full justify-center sm:w-auto"
                        >
                            Esporta
                        </LinkButton>
                    </IndexPageMobileToolbar>

                    {/* Filtri */}
                    <CardBox className="overflow-hidden p-0 shadow-sm">
                        {/* Header filtri — sempre visibile */}
                        <details className="group" {...(hasFilters || hasDraftFilters ? { open: true } : {})}>
                            <summary data-testid="filter-summary" className="flex cursor-pointer select-none items-center justify-between px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span className="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                    </svg>
                                    Filtri
                                    {hasFilters && (
                                        <span className="inline-flex items-center justify-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                                            {countActiveFilters(filters)}
                                        </span>
                                    )}
                                    {hasPendingFilterChanges && (
                                        <span className="inline-flex items-center justify-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                            da applicare
                                        </span>
                                    )}
                                </span>
                                <svg className="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>

                            {/* Corpo filtri — 2 colonne su mobile, flex-wrap su sm+ */}
                            <div className="border-t border-gray-100 px-4 pb-4 pt-3 dark:border-gray-700">
                                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
                                    <select
                                        className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={draftFilters.account_id || ''}
                                        onChange={(e) => updateDraftFilter('account_id', e.target.value)}
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
                                        value={draftFilters.type || ''}
                                        onChange={(e) => updateDraftFilter('type', e.target.value)}
                                    >
                                        <option value="">Tutti i tipi</option>
                                        <option value="income">Entrate</option>
                                        <option value="expense">Uscite</option>
                                    </select>
                                    <select
                                        className="col-span-2 w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 sm:col-span-1 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={draftFilters.category_id || ''}
                                        onChange={(e) => updateDraftFilter('category_id', e.target.value)}
                                    >
                                        <option value="">Tutte le categorie</option>
                                        <option value="__none__">— Senza categoria</option>
                                        {visibleCategories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.icon} {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <div className="col-span-2 flex w-full flex-col gap-1 sm:col-span-1 sm:max-w-44">
                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">Data da</span>
                                        <input
                                            type="date"
                                            className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={draftFilters.from || ''}
                                            onChange={(e) => updateDraftFilter('from', e.target.value)}
                                            aria-label="Data da"
                                        />
                                    </div>
                                    <div className="col-span-2 flex w-full flex-col gap-1 sm:col-span-1 sm:max-w-44">
                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">Data a</span>
                                        <input
                                            type="date"
                                            className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={draftFilters.to || ''}
                                            onChange={(e) => updateDraftFilter('to', e.target.value)}
                                            aria-label="Data a"
                                        />
                                    </div>
                                    {tags.length > 0 && (
                                        <select
                                            className="col-span-2 w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 sm:col-span-1 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={draftFilters.tag_id || ''}
                                            onChange={(e) => updateDraftFilter('tag_id', e.target.value)}
                                        >
                                            <option value="">Tutti i tag</option>
                                            {tags.map((tag) => (
                                                <option key={tag.id} value={tag.id}>
                                                    {tag.name}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                    <div className="col-span-2 flex w-full flex-col gap-1 sm:col-span-2">
                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">Cerca nella descrizione</span>
                                        <input
                                            type="search"
                                            className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={draftFilters.description || ''}
                                            onChange={(e) => updateDraftFilter('description', e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    e.preventDefault();
                                                    applyFilters();
                                                }
                                            }}
                                            placeholder={
                                                draftFilters.description_regex === '1'
                                                    ? 'es. ^Pagamento|carte'
                                                    : 'es. supermercato coop'
                                            }
                                            aria-label="Cerca nella descrizione"
                                        />
                                        <label className="mt-1 flex cursor-pointer items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                            <input
                                                id="filter-description-regex"
                                                type="checkbox"
                                                checked={draftFilters.description_regex === '1'}
                                                onChange={(e) => {
                                                    setDraftFilters((prev) => {
                                                        const next = { ...prev };
                                                        if (e.target.checked) {
                                                            next.description_regex = '1';
                                                        } else {
                                                            delete next.description_regex;
                                                        }

                                                        return next;
                                                    });
                                                }}
                                                className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
                                            />
                                            Usa espressione regolare
                                        </label>
                                        {draftFilters.description_regex === '1' && (
                                            <p className="text-[11px] leading-snug text-gray-500 dark:text-gray-500">
                                                Esempi: <code className="text-gray-600 dark:text-gray-400">^Bolletta</code>,{' '}
                                                <code className="text-gray-600 dark:text-gray-400">carte|pos</code>,{' '}
                                                <code className="text-gray-600 dark:text-gray-400">ess.*unga</code>
                                            </p>
                                        )}
                                    </div>
                                    <div className="col-span-2 flex w-full flex-col gap-1 sm:col-span-1 sm:max-w-36">
                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">Importo da (€)</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={draftFilters.amount_min || ''}
                                            onChange={(e) => updateDraftFilter('amount_min', e.target.value)}
                                            aria-label="Importo minimo"
                                        />
                                    </div>
                                    <div className="col-span-2 flex w-full flex-col gap-1 sm:col-span-1 sm:max-w-36">
                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">Importo a (€)</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className="w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={draftFilters.amount_max || ''}
                                            onChange={(e) => updateDraftFilter('amount_max', e.target.value)}
                                            aria-label="Importo massimo"
                                        />
                                    </div>
                                    <select
                                        className="col-span-2 w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 sm:col-span-1 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={draftFilters.currency_code || ''}
                                        onChange={(e) => updateDraftFilter('currency_code', e.target.value)}
                                    >
                                        <option value="">Tutte le valute</option>
                                        {currencies.map((currency) => (
                                            <option key={currency.code} value={currency.code}>
                                                {currency.code} - {currency.name}
                                            </option>
                                        ))}
                                    </select>
                                    <div className="col-span-2 flex w-full flex-col gap-2 border-t border-gray-100 pt-3 sm:col-span-full sm:flex-row sm:items-center sm:justify-end dark:border-gray-700">
                                        <button
                                            type="button"
                                            data-testid="apply-filters"
                                            onClick={applyFilters}
                                            disabled={!hasPendingFilterChanges}
                                            className="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto dark:focus:ring-offset-gray-900"
                                        >
                                            Applica filtri
                                        </button>
                                        {(hasFilters || hasDraftFilters) && (
                                            <button
                                                type="button"
                                                onClick={clearFilters}
                                                className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800 sm:w-auto"
                                            >
                                                Pulisci filtri
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </details>
                    </CardBox>

                    {/* Lista Transazioni */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="grid grid-cols-2 gap-3 border-b border-gray-100 px-4 py-3 text-sm sm:grid-cols-4 dark:border-gray-700">
                            <div className="rounded-lg bg-gray-50 p-2 dark:bg-gray-800">
                                <p className="text-xs text-gray-500 dark:text-gray-400">Transazioni</p>
                                <p className="font-semibold text-gray-900 dark:text-white">{summary.count}</p>
                            </div>
                            <div className="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-900/20">
                                <p className="text-xs text-emerald-600 dark:text-emerald-400">Entrate</p>
                                <p className="font-semibold text-emerald-700 dark:text-emerald-300">{formatCurrency(summary.income, summaryCurrency)}</p>
                            </div>
                            <div className="rounded-lg bg-red-50 p-2 dark:bg-red-900/20">
                                <p className="text-xs text-red-600 dark:text-red-400">Uscite</p>
                                <p className="font-semibold text-red-700 dark:text-red-300">{formatCurrency(summary.expenses, summaryCurrency)}</p>
                            </div>
                            <div className="rounded-lg bg-blue-50 p-2 dark:bg-blue-900/20">
                                <p className="text-xs text-blue-600 dark:text-blue-400">Saldo</p>
                                <p className="font-semibold text-blue-700 dark:text-blue-300">{formatCurrency(summary.net, summaryCurrency)}</p>
                            </div>
                        </div>
                        {transactions.data.length > 0 ? (
                            <>
                                {/* Barra selezione multipla */}
                                <div className="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                                    <label className="flex shrink-0 items-center gap-2 cursor-pointer select-none">
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
                                        <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                                            <button
                                                type="button"
                                                onClick={() => setBulkEditOpen(true)}
                                                className="flex w-full items-center justify-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 sm:w-auto sm:py-1.5"
                                            >
                                                <PencilIcon size={15} />
                                                Modifica selezionate
                                            </button>
                                            <button
                                                type="button"
                                                onClick={handleBulkDelete}
                                                disabled={selectedInvestmentCount > 0}
                                                title={
                                                    selectedInvestmentCount > 0
                                                        ? 'Deseleziona le transazioni collegate a investimenti prima di eliminare'
                                                        : undefined
                                                }
                                                className="flex w-full items-center justify-center gap-1.5 rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:py-1.5"
                                            >
                                                <TrashIcon size={15} />
                                                Elimina selezionate
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <div className="p-4">
                                    {transactions.data.map((transaction) => (
                                        <TransactionListRow
                                            key={transaction.id}
                                            transaction={transaction}
                                            onDeleteClick={openDeleteDialog}
                                            isSelected={selectedIds.has(transaction.id)}
                                            onToggleSelect={toggleSelect}
                                            indexQuery={returnQuery}
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
