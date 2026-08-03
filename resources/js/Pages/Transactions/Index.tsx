import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import MovementsHubNav from '@/Components/MovementsHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions, IndexPageMobileToolbar, mobileFilterBodyClass, mobileLegendClass, mobileListPanelClass } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TransactionListRow, { type TransactionListRowTransaction } from '@/Components/TransactionListRow';
import TransactionSlideOver, {
    type TransactionSlideOverTransaction,
} from '@/Components/TransactionSlideOver';
import TrashIcon from '@/Components/Icons/TrashIcon';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexListCard from '@/Components/Index/IndexListCard';
import MobileBottomSheet from '@/Components/MobileBottomSheet';
import TransactionFiltersFields from '@/Components/TransactionFiltersFields';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { type FormDataConvertible } from '@inertiajs/core';
import clsx from 'clsx';
import { Pagination } from '@/Components/Pagination';
import CardBox from '@/Components/CardBox';
import React, { useEffect, useMemo, useState } from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import axios from 'axios';
import { filtersAnalytics, tx } from '@/utils/analytics';
import { formatCurrency } from '@/utils/format';
import type { PageProps } from '@/types';
import useMdUp from '@/hooks/useMdUp';
import useLgUp from '@/hooks/useLgUp';

const UPCOMING_EXPANDED_STORAGE_PREFIX = 'finanzamente.upcomingMovements.expanded';

function readUpcomingExpandedPreference(userId: number | undefined): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const key = userId != null
        ? `${UPCOMING_EXPANDED_STORAGE_PREFIX}.${userId}`
        : UPCOMING_EXPANDED_STORAGE_PREFIX;

    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function writeUpcomingExpandedPreference(userId: number | undefined, expanded: boolean): void {
    if (typeof window === 'undefined') {
        return;
    }

    const key = userId != null
        ? `${UPCOMING_EXPANDED_STORAGE_PREFIX}.${userId}`
        : UPCOMING_EXPANDED_STORAGE_PREFIX;

    try {
        window.localStorage.setItem(key, expanded ? '1' : '0');
    } catch {
        // ignore quota / private mode
    }
}

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
    is_pac?: boolean;
    pac_summary?: {
        id: number;
        asset_name: string | null;
    } | null;
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

type ActiveFilterChip = { key: keyof Filters; label: string };

function buildActiveFilterChips(
    f: Filters,
    accounts: Array<{ id: number; name: string }>,
    categories: Category[],
    tags: Array<{ id: number; name: string }>,
): ActiveFilterChip[] {
    const chips: ActiveFilterChip[] = [];

    if (f.type === 'income') {
        chips.push({ key: 'type', label: 'Entrate' });
    } else if (f.type === 'expense') {
        chips.push({ key: 'type', label: 'Uscite' });
    }

    if (f.account_id) {
        const account = accounts.find((a) => String(a.id) === String(f.account_id));
        chips.push({ key: 'account_id', label: account?.name ?? `Conto #${f.account_id}` });
    }

    if (f.category_id === '__none__') {
        chips.push({ key: 'category_id', label: 'Senza categoria' });
    } else if (f.category_id) {
        const category = categories.find((c) => String(c.id) === String(f.category_id));
        chips.push({ key: 'category_id', label: category?.name ?? `Categoria #${f.category_id}` });
    }

    if (f.from || f.to) {
        const from = f.from ? f.from.split('-').reverse().join('/') : '…';
        const to = f.to ? f.to.split('-').reverse().join('/') : '…';
        chips.push({ key: f.from ? 'from' : 'to', label: `${from} – ${to}` });
    }

    if (f.tag_id) {
        const tag = tags.find((t) => String(t.id) === String(f.tag_id));
        chips.push({ key: 'tag_id', label: tag?.name ?? `Tag #${f.tag_id}` });
    }

    if (f.description) {
        chips.push({ key: 'description', label: `«${f.description}»` });
    }

    if (f.amount_min || f.amount_max) {
        chips.push({
            key: f.amount_min ? 'amount_min' : 'amount_max',
            label: `${f.amount_min || '0'}–${f.amount_max || '∞'} €`,
        });
    }

    if (f.currency_code) {
        chips.push({ key: 'currency_code', label: f.currency_code });
    }

    if (f.is_tax_deductible) {
        chips.push({ key: 'is_tax_deductible', label: 'Detraibile' });
    }

    return chips;
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
    upcomingMovements: TransactionListRowTransaction[];
    projectedHouseholdBalance: number | null;
}

// Sentinel: campo non modificato
const UNCHANGED = '__unchanged__';
// Sentinel: rimuovi collegamento (per campi nullable)
const REMOVE = '__remove__';

type TriState = '__unchanged__' | 'true' | 'false';

interface BulkEditState {
    date: string;
    category_id: string;      // UNCHANGED | REMOVE | '<number>'
    is_private: TriState;
    debt_credit_id: string;   // UNCHANGED | REMOVE | '<number>'
    is_tax_deductible: TriState;
    account_id: string;       // UNCHANGED | '<number>'
    tag_ids: string[];
    new_tag_names: string;
}

const defaultBulkEdit: BulkEditState = {
    date: UNCHANGED,
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
    selectedPacCount,
    categories,
    accounts,
    debtCredits,
    tags,
    onClose,
    onConfirm,
}: {
    open: boolean;
    count: number;
    selectedPacCount: number;
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
        state.date !== UNCHANGED ||
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

                    {state.date !== UNCHANGED && selectedPacCount > 0 && (
                        <div className="rounded-lg border border-sky-200 bg-sky-50/60 p-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200">
                            {selectedPacCount === 1
                                ? '1 transazione è generata da un piano PAC: la nuova data verrà sincronizzata con il movimento d\'investimento collegato.'
                                : `${selectedPacCount} transazioni sono generate da piani PAC: la nuova data verrà sincronizzata con i movimenti d'investimento collegati.`}
                        </div>
                    )}

                    <div className="space-y-1">
                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Data</label>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() => set('date', UNCHANGED)}
                                className={clsx(
                                    'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                    state.date === UNCHANGED
                                        ? 'border-emerald-600 bg-emerald-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                )}
                            >
                                — Invariata —
                            </button>
                            <input
                                type="date"
                                value={state.date === UNCHANGED ? '' : state.date}
                                onChange={(e) => set('date', e.target.value)}
                                className="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            />
                        </div>
                    </div>

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
    upcomingMovements = [],
    projectedHouseholdBalance = null,
}: IndexProps) {
    const { auth } = usePage<PageProps>().props;
    const summaryCurrency = filters.currency_code || 'EUR';
    const userId = auth.user?.id;

    const [upcomingExpanded, setUpcomingExpanded] = useState(false);

    useEffect(() => {
        setUpcomingExpanded(readUpcomingExpandedPreference(userId));
    }, [userId]);

    const upcomingSummary = useMemo(() => {
        let income = 0;
        let expenses = 0;
        for (const movement of upcomingMovements) {
            const amount = Number(movement.amount) || 0;
            if (amount > 0) {
                income += amount;
            } else {
                expenses += Math.abs(amount);
            }
        }

        return {
            count: upcomingMovements.length,
            income,
            expenses,
            net: income - expenses,
        };
    }, [upcomingMovements]);

    const toggleUpcomingExpanded = () => {
        setUpcomingExpanded((prev) => {
            const next = !prev;
            writeUpcomingExpandedPreference(userId, next);
            return next;
        });
    };

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
    const [filtersOpen, setFiltersOpen] = React.useState(false);
    const preferSlideOver = useMdUp();
    const isLgUp = useLgUp();
    const [slideOverOpen, setSlideOverOpen] = React.useState(false);
    const [slideOverLoading, setSlideOverLoading] = React.useState(false);
    const [slideOverError, setSlideOverError] = React.useState<string | null>(null);
    const [slideOverTransaction, setSlideOverTransaction] =
        React.useState<TransactionSlideOverTransaction | null>(null);

    const closeSlideOver = () => {
        setSlideOverOpen(false);
        setSlideOverLoading(false);
        setSlideOverError(null);
        setSlideOverTransaction(null);
    };

    const openSlideOver = async (transactionId: number) => {
        setSlideOverOpen(true);
        setSlideOverLoading(true);
        setSlideOverError(null);
        setSlideOverTransaction(null);
        try {
            const resp = await axios.get<{ transaction: TransactionSlideOverTransaction }>(
                route('transactions.show', { transaction: transactionId, ...returnQuery }),
                { headers: { Accept: 'application/json' } },
            );
            setSlideOverTransaction(resp.data.transaction);
        } catch {
            setSlideOverError('Impossibile caricare il dettaglio della transazione.');
        } finally {
            setSlideOverLoading(false);
        }
    };

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
    const isEmpty = transactions.data.length === 0;

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

    const selectedPacCount = transactions.data.filter(
        (transaction) => selectedIds.has(transaction.id) && transaction.is_pac === true,
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
        if (state.date !== UNCHANGED) {
            payload.date = state.date;
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
        setFiltersOpen(false);
        router.get(route('transactions.index'));
    };

    const applyFiltersAndClose = () => {
        applyFilters();
        setFiltersOpen(false);
    };

    const activeFilterChips = useMemo(
        () => buildActiveFilterChips(filters, accounts, categories, tags),
        [filters, accounts, categories, tags],
    );

    const applyQuickType = (type: '' | 'income' | 'expense') => {
        const next = computeNextFilters(filters, categories, 'type', type);
        setDraftFilters(next);
        router.get(route('transactions.index'), filtersToQueryParams(next), {
            preserveState: true,
            replace: true,
        });
    };

    const removeAppliedFilter = (key: keyof Filters) => {
        const next: Filters = { ...filters };
        if (key === 'from' || key === 'to') {
            delete next.from;
            delete next.to;
        } else if (key === 'amount_min' || key === 'amount_max') {
            delete next.amount_min;
            delete next.amount_max;
        } else if (key === 'description') {
            delete next.description;
            delete next.description_regex;
        } else {
            delete next[key];
        }
        setDraftFilters(next);
        router.get(route('transactions.index'), filtersToQueryParams(next), {
            preserveState: true,
            replace: true,
        });
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
                selectedPacCount={selectedPacCount}
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

            <TransactionSlideOver
                open={slideOverOpen}
                loading={slideOverLoading}
                error={slideOverError}
                transaction={slideOverTransaction}
                indexQuery={returnQuery}
                onClose={closeSlideOver}
            />

            <PageContent maxWidth="7xl">
                    <MovementsHubNav active="transactions" />
                    {/* Banner importazioni in corso */}
                    {pendingImports.length > 0 && (
                        <div className="mb-2 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 sm:mb-3 sm:gap-3 sm:px-4 sm:py-3">
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
                        >
                            Importa
                        </LinkButton>
                        <LinkButton
                            native
                            data-testid="transactions-export"
                            href={exportHref}
                            variant="secondary"
                            size="sm"
                        >
                            Esporta
                        </LinkButton>
                    </IndexPageMobileToolbar>

                    {/* Filtri — chip + quick type; pannello desktop / sheet mobile */}
                    <div className="space-y-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                data-testid="filter-summary"
                                onClick={() => setFiltersOpen((open) => !open)}
                                aria-expanded={filtersOpen}
                                className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400" aria-hidden="true">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                                </svg>
                                Filtri
                                {hasFilters && (
                                    <span className="inline-flex items-center justify-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">
                                        {countActiveFilters(filters)}
                                    </span>
                                )}
                                {hasPendingFilterChanges && filtersOpen && (
                                    <span className="inline-flex items-center justify-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        da applicare
                                    </span>
                                )}
                            </button>
                            <div className="flex items-center gap-1.5" role="group" aria-label="Filtro rapido tipo">
                                <button
                                    type="button"
                                    onClick={() => applyQuickType(filters.type === 'expense' ? '' : 'expense')}
                                    className={clsx(
                                        'rounded-full px-3 py-1.5 text-xs font-medium transition-colors',
                                        filters.type === 'expense'
                                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                                    )}
                                >
                                    Uscite
                                </button>
                                <button
                                    type="button"
                                    onClick={() => applyQuickType(filters.type === 'income' ? '' : 'income')}
                                    className={clsx(
                                        'rounded-full px-3 py-1.5 text-xs font-medium transition-colors',
                                        filters.type === 'income'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                                    )}
                                >
                                    Entrate
                                </button>
                            </div>
                        </div>

                        {activeFilterChips.length > 0 && (
                            <div className="flex flex-wrap gap-1.5" aria-label="Filtri attivi">
                                {activeFilterChips.map((chip) => (
                                    <button
                                        key={`${chip.key}-${chip.label}`}
                                        type="button"
                                        onClick={() => removeAppliedFilter(chip.key)}
                                        className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-200 dark:ring-emerald-800"
                                    >
                                        <span className="max-w-[10rem] truncate">{chip.label}</span>
                                        <span aria-hidden="true" className="text-emerald-600 dark:text-emerald-300">×</span>
                                        <span className="sr-only">Rimuovi filtro {chip.label}</span>
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="rounded-full px-2.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                >
                                    Pulisci
                                </button>
                            </div>
                        )}

                        {filtersOpen && isLgUp && (
                            <CardBox className="overflow-hidden p-0 shadow-sm">
                                <div className={clsx('border-gray-100 dark:border-gray-700', mobileFilterBodyClass)}>
                                    <TransactionFiltersFields
                                        draftFilters={draftFilters}
                                        updateDraftFilter={updateDraftFilter}
                                        setDraftFilters={setDraftFilters}
                                        accounts={accounts}
                                        visibleCategories={visibleCategories}
                                        tags={tags}
                                        currencies={currencies}
                                        applyFilters={applyFiltersAndClose}
                                        clearFilters={clearFilters}
                                        hasPendingFilterChanges={hasPendingFilterChanges}
                                        hasFilters={hasFilters}
                                        hasDraftFilters={hasDraftFilters}
                                    />
                                </div>
                            </CardBox>
                        )}

                        {!isLgUp && (
                            <MobileBottomSheet
                                open={filtersOpen}
                                onClose={() => setFiltersOpen(false)}
                                title="Filtri"
                                footer={(
                                    <div className="flex flex-col gap-2">
                                        <button
                                            type="button"
                                            data-testid="apply-filters"
                                            onClick={applyFiltersAndClose}
                                            disabled={!hasPendingFilterChanges}
                                            className="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-gray-900"
                                        >
                                            Applica filtri
                                        </button>
                                        {(hasFilters || hasDraftFilters) && (
                                            <button
                                                type="button"
                                                onClick={clearFilters}
                                                className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                                            >
                                                Pulisci filtri
                                            </button>
                                        )}
                                    </div>
                                )}
                            >
                                <TransactionFiltersFields
                                    draftFilters={draftFilters}
                                    updateDraftFilter={updateDraftFilter}
                                    setDraftFilters={setDraftFilters}
                                    accounts={accounts}
                                    visibleCategories={visibleCategories}
                                    tags={tags}
                                    currencies={currencies}
                                    applyFilters={applyFiltersAndClose}
                                    clearFilters={clearFilters}
                                    hasPendingFilterChanges={hasPendingFilterChanges}
                                    hasFilters={hasFilters}
                                    hasDraftFilters={hasDraftFilters}
                                    hideActions
                                />
                            </MobileBottomSheet>
                        )}
                    </div>

                    <p className={mobileLegendClass}>
                        <span className="font-medium text-gray-600 dark:text-gray-300">Bordo sinistro:</span>
                        {' '}
                        <span className="text-sky-600 dark:text-sky-400">PAC</span>
                        {' · '}
                        <span className="text-violet-600 dark:text-violet-400">Ricorrente</span>
                        {' · '}
                        <span className="text-indigo-600 dark:text-indigo-400">Investimento</span>
                    </p>

                    {upcomingMovements.length > 0 ? (
                        <IndexListCard
                            appearance="flush"
                            header={(
                                <div className="border-b border-gray-100 dark:border-gray-700">
                                    <button
                                        type="button"
                                        onClick={toggleUpcomingExpanded}
                                        aria-expanded={upcomingExpanded}
                                        aria-controls="upcoming-movements-list"
                                        data-testid="upcoming-movements-toggle"
                                        className="flex w-full items-center gap-4 px-2 py-3 text-left transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500 dark:hover:bg-gray-800/60 sm:px-3"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                                <h2 className="text-sm font-semibold text-gray-900 dark:text-white">
                                                    Prossimi movimenti
                                                </h2>
                                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    {upcomingSummary.count}
                                                </span>
                                            </div>
                                            {upcomingExpanded ? (
                                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Ricorrenze e PAC previsti (non inclusi nel saldo attuale).
                                                </p>
                                            ) : null}
                                            {upcomingExpanded ? (
                                                <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-300 sm:mt-1.5">
                                                    <span>
                                                        Entrate{' '}
                                                        <span className="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                                            {formatCurrency(upcomingSummary.income, summaryCurrency)}
                                                        </span>
                                                    </span>
                                                    <span>
                                                        Uscite{' '}
                                                        <span className="font-semibold tabular-nums text-red-600 dark:text-red-400">
                                                            {formatCurrency(upcomingSummary.expenses, summaryCurrency)}
                                                        </span>
                                                    </span>
                                                    <span>
                                                        Netto{' '}
                                                        <span className="font-semibold tabular-nums">
                                                            {formatCurrency(upcomingSummary.net, summaryCurrency)}
                                                        </span>
                                                    </span>
                                                    {projectedHouseholdBalance != null ? (
                                                        <span>
                                                            Dopo 90 gg:{' '}
                                                            <span className="font-semibold tabular-nums">
                                                                {formatCurrency(projectedHouseholdBalance, summaryCurrency)}
                                                            </span>
                                                        </span>
                                                    ) : null}
                                                </div>
                                            ) : null}
                                        </div>
                                        <span
                                            className="ml-auto flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-600"
                                            aria-hidden
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                                className={clsx('h-6 w-6 transition-transform', upcomingExpanded && 'rotate-180')}
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            )}
                            isEmpty={false}
                            bodyClassName="!px-0"
                        >
                            {upcomingExpanded ? (
                                <div id="upcoming-movements-list">
                                    {upcomingMovements.map((movement) => (
                                        <TransactionListRow
                                            key={movement.id}
                                            transaction={movement}
                                            onDeleteClick={() => undefined}
                                            isSelected={false}
                                            onToggleSelect={() => undefined}
                                            indexQuery={returnQuery}
                                            preferSlideOver={preferSlideOver}
                                            onOpenDetail={openSlideOver}
                                            hideCheckbox
                                        />
                                    ))}
                                </div>
                            ) : null}
                        </IndexListCard>
                    ) : null}

                    {/* Lista Transazioni */}
                    <IndexListCard
                        appearance="flush"
                        kpi={(
                            <div className="border-b border-gray-100 px-3 py-1.5 sm:px-4 sm:py-2 dark:border-gray-700">
                                <p className="text-xs text-gray-600 dark:text-gray-300">
                                    Saldo nel periodo:{' '}
                                    <span
                                        className={clsx(
                                            'font-semibold tabular-nums',
                                            summary.net >= 0
                                                ? 'text-gray-900 dark:text-white'
                                                : 'text-red-600 dark:text-red-400',
                                        )}
                                    >
                                        {formatCurrency(summary.net, summaryCurrency)}
                                    </span>
                                </p>
                            </div>
                        )}
                        toolbar={
                            !isEmpty ? (
                                <div className={clsx('flex flex-col gap-2 border-b border-gray-100 sm:flex-row sm:items-center sm:justify-between sm:gap-3 dark:border-gray-700', mobileListPanelClass)}>
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
                            ) : undefined
                        }
                        isEmpty={isEmpty}
                        empty={
                            <IndexEmptyList
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
                        }
                        footer={!isEmpty ? <Pagination data={transactions} /> : undefined}
                    >
                        {transactions.data.map((transaction) => (
                            <TransactionListRow
                                key={transaction.id}
                                transaction={transaction}
                                onDeleteClick={openDeleteDialog}
                                isSelected={selectedIds.has(transaction.id)}
                                onToggleSelect={toggleSelect}
                                indexQuery={returnQuery}
                                preferSlideOver={preferSlideOver}
                                onOpenDetail={openSlideOver}
                            />
                        ))}
                    </IndexListCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
