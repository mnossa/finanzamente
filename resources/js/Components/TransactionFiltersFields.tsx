import clsx from 'clsx';
import type { Dispatch, SetStateAction } from 'react';
import ItalianDateInput from '@/Components/ItalianDateInput';

export interface TransactionFiltersState {
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

interface CategoryOption {
    id: number;
    name: string;
    icon?: string | null;
    type: string;
}

interface TransactionFiltersFieldsProps {
    draftFilters: TransactionFiltersState;
    updateDraftFilter: (key: string, value: string) => void;
    setDraftFilters: Dispatch<SetStateAction<TransactionFiltersState>>;
    accounts: Array<{ id: number; name: string }>;
    visibleCategories: CategoryOption[];
    tags: Array<{ id: number; name: string }>;
    currencies: Array<{ code: string; name: string }>;
    applyFilters: () => void;
    clearFilters: () => void;
    hasPendingFilterChanges: boolean;
    hasFilters: boolean;
    hasDraftFilters: boolean;
    /** Nasconde CTA Applica/Pulisci (es. footer dello sheet mobile). */
    hideActions?: boolean;
}

const fieldClass =
    'w-full rounded-lg border border-gray-200 bg-white py-2 pl-2.5 pr-8 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';

const inputClass =
    'w-full rounded-lg border border-gray-200 bg-white py-2 px-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';

export default function TransactionFiltersFields({
    draftFilters,
    updateDraftFilter,
    setDraftFilters,
    accounts,
    visibleCategories,
    tags,
    currencies,
    applyFilters,
    clearFilters,
    hasPendingFilterChanges,
    hasFilters,
    hasDraftFilters,
    hideActions = false,
}: TransactionFiltersFieldsProps) {
    return (
        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-end sm:gap-3">
            <select
                className={fieldClass}
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
                className={fieldClass}
                value={draftFilters.type || ''}
                onChange={(e) => updateDraftFilter('type', e.target.value)}
            >
                <option value="">Tutti i tipi</option>
                <option value="income">Entrate</option>
                <option value="expense">Uscite</option>
            </select>
            <select
                className={clsx(fieldClass, 'col-span-2 sm:col-span-1')}
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
                <ItalianDateInput
                    value={draftFilters.from || ''}
                    onChange={(iso) => updateDraftFilter('from', iso)}
                    aria-label="Data da"
                />
            </div>
            <div className="col-span-2 flex w-full flex-col gap-1 sm:col-span-1 sm:max-w-44">
                <span className="text-xs font-medium text-gray-600 dark:text-gray-400">Data a</span>
                <ItalianDateInput
                    value={draftFilters.to || ''}
                    onChange={(iso) => updateDraftFilter('to', iso)}
                    aria-label="Data a"
                />
            </div>
            {tags.length > 0 && (
                <select
                    className={clsx(fieldClass, 'col-span-2 sm:col-span-1')}
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
                    className={inputClass}
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
                    className={inputClass}
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
                    className={inputClass}
                    value={draftFilters.amount_max || ''}
                    onChange={(e) => updateDraftFilter('amount_max', e.target.value)}
                    aria-label="Importo massimo"
                />
            </div>
            <select
                className={clsx(fieldClass, 'col-span-2 sm:col-span-1')}
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
            {!hideActions && (
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
            )}
        </div>
    );
}
