import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { mobileListRowInsetClass } from '@/Components/IndexPageListToolbars';
import { formatCurrency, formatDate, formatDateShort } from '@/utils/format';

export interface TransactionListRowCategory {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    type: 'income' | 'expense';
}

export interface TransactionListRowAccount {
    id: number;
    name: string;
    currency_code: string;
}

export interface TransactionListRowTag {
    id: number;
    name: string;
    color: string | null;
}

export interface TransactionListRowRecurringSummary {
    id: number;
    description: string | null;
    frequency: string;
}

export interface TransactionListRowPacSummary {
    id: number;
    asset_name: string | null;
}

export interface TransactionListRowTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    transfer_id: number | null;
    refund_id: number | null;
    has_refunds: boolean;
    is_fully_refunded: boolean;
    attachments_count?: number;
    category: TransactionListRowCategory | null;
    account: TransactionListRowAccount;
    tags: TransactionListRowTag[];
    recurring_transaction_id: number | null;
    recurring_summary?: TransactionListRowRecurringSummary | null;
    investment_id: number | null;
    is_investment?: boolean;
    is_pac?: boolean;
    pac_summary?: TransactionListRowPacSummary | null;
    is_future?: boolean;
    is_virtual?: boolean;
    virtual_source?: 'recurring' | 'pac' | null;
    virtual_source_id?: number | null;
    projected_balance_after?: number | null;
}

export type TransactionListIndexQuery = Record<string, string | number>;

interface TransactionListRowProps {
    transaction: TransactionListRowTransaction;
    onDeleteClick: (target: { id: number; description: string; isInvestment: boolean }) => void;
    isSelected: boolean;
    onToggleSelect: (id: number) => void;
    indexQuery: TransactionListIndexQuery;
}

const FREQUENCY_LABELS: Record<string, string> = {
    daily: 'Giornaliera',
    weekly: 'Settimanale',
    monthly: 'Mensile',
    yearly: 'Annuale',
};

const MAX_SECONDARY_INDICATORS = 2;

function RowIndicator({
    label,
    children,
    className,
}: {
    label: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <span
            title={label}
            aria-label={label}
            className={clsx(
                'inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full text-[9px] sm:h-6 sm:w-6 sm:text-xs',
                className,
            )}
        >
            {children}
        </span>
    );
}

function renderIndicatorOverflow(indicators: React.ReactNode[], hiddenLabels: string[]) {
    if (indicators.length === 0) {
        return null;
    }

    const visible = indicators.slice(0, MAX_SECONDARY_INDICATORS);
    const hiddenCount = indicators.length - visible.length;
    const overflowTitle = hiddenLabels.slice(MAX_SECONDARY_INDICATORS).join(', ');

    return (
        <>
            {visible}
            {hiddenCount > 0 ? (
                <span
                    title={overflowTitle}
                    aria-label={`Altri ${hiddenCount} indicatori: ${overflowTitle}`}
                    className="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded-full bg-gray-100 px-1 text-[9px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300 sm:h-6 sm:min-w-6 sm:px-1.5 sm:text-[10px]"
                >
                    +{hiddenCount}
                </span>
            ) : null}
        </>
    );
}

function rowBorderClasses(isPac: boolean, isRecurring: boolean, isManualInvestment: boolean) {
    return clsx(
        'border-l-[3px] sm:border-l-4',
        isPac && 'border-l-sky-500 dark:border-l-sky-400',
        isRecurring && !isPac && 'border-l-violet-500 dark:border-l-violet-400',
        isManualInvestment && !isPac && !isRecurring && 'border-l-indigo-500 dark:border-l-indigo-400',
        !isPac && !isRecurring && !isManualInvestment && 'border-l-transparent',
    );
}

export default function TransactionListRow({
    transaction,
    onDeleteClick,
    isSelected,
    onToggleSelect,
    indexQuery,
}: TransactionListRowProps) {
    const isFuture = transaction.is_future === true;
    const isVirtual = transaction.is_virtual === true;
    const isIncome = transaction.amount > 0;
    const isTransfer = transaction.transfer_id !== null;
    const isRefund = transaction.refund_id !== null;
    const hasRefunds = transaction.has_refunds;
    const isPac = transaction.is_pac === true;
    const isRecurring = transaction.recurring_transaction_id !== null;
    const isManualInvestment = transaction.investment_id !== null && !isPac;
    const isInvestment = transaction.investment_id !== null;
    const hasAttachments = (transaction.attachments_count ?? 0) > 0;
    const hasTags = transaction.tags.length > 0;

    const title = transaction.description || transaction.category?.name || 'Transazione';
    const recurringFrequencyLabel = transaction.recurring_summary?.frequency
        ? FREQUENCY_LABELS[transaction.recurring_summary.frequency] ?? transaction.recurring_summary.frequency
        : null;

    const titleClassName =
        'min-w-0 truncate text-xs font-medium leading-none text-gray-900 dark:text-white sm:text-sm sm:leading-5';
    const amountClassName = clsx(
        'shrink-0 text-xs font-semibold tabular-nums sm:text-base',
        isIncome ? 'text-green-500' : 'text-red-500',
    );
    const formattedAmount = `${isIncome ? '+' : ''}${formatCurrency(transaction.amount, transaction.account.currency_code)}`;

    const sourceIndicators = [
        isPac ? (
            <RowIndicator key="pac" label="Generata da PAC" className="bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                📊
            </RowIndicator>
        ) : null,
        isRecurring ? (
            <RowIndicator key="recurring" label="Generata da ricorrenza" className="bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                🔁
            </RowIndicator>
        ) : null,
        isManualInvestment ? (
            <RowIndicator key="investment" label="Collegata a un investimento" className="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                📈
            </RowIndicator>
        ) : null,
    ].filter(Boolean);

    const secondaryIndicatorEntries: { label: string; node: React.ReactNode }[] = [];

    if (isFuture) {
        secondaryIndicatorEntries.push({
            label: isVirtual ? 'Movimento previsto' : 'Transazione programmata',
            node: (
                <RowIndicator
                    key="future"
                    label={isVirtual ? 'Movimento previsto' : 'Transazione programmata'}
                    className="bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                >
                    {isVirtual ? '🔮' : '📅'}
                </RowIndicator>
            ),
        });
    }
    if (isTransfer) {
        secondaryIndicatorEntries.push({
            label: 'Trasferimento tra conti',
            node: (
                <RowIndicator key="transfer" label="Trasferimento tra conti" className="bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                    🔄
                </RowIndicator>
            ),
        });
    }
    if (isRefund) {
        secondaryIndicatorEntries.push({
            label: 'Rimborso',
            node: (
                <RowIndicator key="refund" label="Rimborso" className="bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                    💸
                </RowIndicator>
            ),
        });
    }
    if (hasRefunds) {
        const refundedLabel = transaction.is_fully_refunded ? 'Rimborsato per intero' : 'Rimborso parziale';
        secondaryIndicatorEntries.push({
            label: refundedLabel,
            node: (
                <RowIndicator
                    key="refunded"
                    label={refundedLabel}
                    className={clsx(
                        transaction.is_fully_refunded
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                    )}
                >
                    {transaction.is_fully_refunded ? '✓' : '◐'}
                </RowIndicator>
            ),
        });
    }
    if (transaction.is_private) {
        secondaryIndicatorEntries.push({
            label: 'Transazione privata',
            node: (
                <RowIndicator key="private" label="Transazione privata" className="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    🔒
                </RowIndicator>
            ),
        });
    }
    if (transaction.is_tax_deductible) {
        secondaryIndicatorEntries.push({
            label: 'Detraibile fiscalmente',
            node: (
                <RowIndicator key="tax" label="Detraibile fiscalmente" className="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                    📋
                </RowIndicator>
            ),
        });
    }
    if (hasAttachments) {
        secondaryIndicatorEntries.push({
            label: 'Allegati presenti',
            node: (
                <RowIndicator key="attachments" label="Allegati presenti" className="bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                    📎
                </RowIndicator>
            ),
        });
    }
    if (hasTags) {
        const tagsLabel = `${transaction.tags.length} etichett${transaction.tags.length === 1 ? 'a' : 'e'}`;
        secondaryIndicatorEntries.push({
            label: tagsLabel,
            node: (
                <RowIndicator key="tags" label={tagsLabel} className="bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                    🏷️
                </RowIndicator>
            ),
        });
    }

    const secondaryIndicators = secondaryIndicatorEntries.map((entry) => entry.node);
    const secondaryLabels = secondaryIndicatorEntries.map((entry) => entry.label);
    const sourceIndicatorLabels = [
        ...(isPac ? ['Generata da PAC'] : []),
        ...(isRecurring ? ['Generata da ricorrenza'] : []),
        ...(isManualInvestment ? ['Collegata a un investimento'] : []),
    ];
    const mobileIndicators = renderIndicatorOverflow(
        [...sourceIndicators, ...secondaryIndicators],
        [...sourceIndicatorLabels, ...secondaryLabels],
    );
    const desktopIndicators = [...sourceIndicators, ...secondaryIndicators];

    const rowTitle = isPac
        ? 'Generata da PAC'
        : isRecurring
          ? 'Generata da ricorrenza'
          : isManualInvestment
            ? 'Collegata a un investimento'
            : undefined;

    const investmentActionHref = isPac && transaction.pac_summary
        ? route('investment-pacs.show', transaction.pac_summary.id)
        : isInvestment
          ? route('investments.show', transaction.investment_id!)
          : null;

    const detailHref = isVirtual
        ? transaction.virtual_source === 'pac' && transaction.virtual_source_id
            ? route('investment-pacs.show', transaction.virtual_source_id)
            : transaction.virtual_source === 'recurring' && transaction.virtual_source_id
              ? route('recurring-transactions.show', transaction.virtual_source_id)
              : route('transactions.index')
        : route('transactions.show', { transaction: transaction.id, ...indexQuery });
    const rowStateClass = isSelected
        ? 'bg-emerald-50 dark:bg-emerald-900/20'
        : 'hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50';
    const borderClasses = rowBorderClasses(isPac, isRecurring, isManualInvestment);

    const avatarStyle = {
        backgroundColor: isTransfer
            ? '#f59e0b20'
            : isRefund
              ? '#3b82f620'
              : transaction.category?.color
                ? `${transaction.category.color}20`
                : isIncome
                  ? '#22c55e20'
                  : '#ef444420',
    };

    const metaLabel = `${formatDate(transaction.date)} · ${transaction.account.name}`;

    const checkbox = isVirtual ? (
        <span className="inline-block h-4 w-4 shrink-0" aria-hidden />
    ) : (
        <input
            type="checkbox"
            checked={isSelected}
            onChange={() => onToggleSelect(transaction.id)}
            className="h-4 w-4 shrink-0 cursor-pointer self-center rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
            onClick={(e) => e.stopPropagation()}
            aria-label={`Seleziona ${title}`}
        />
    );

    return (
        <div
            className={clsx(
                'border-b border-gray-100 transition-colors last:border-0',
                mobileListRowInsetClass,
                rowStateClass,
                borderClasses,
                isFuture && 'opacity-60',
            )}
            title={rowTitle ? `${title} — ${rowTitle}` : title}
        >
            {/* Mobile: griglia con offset fisso — titoli allineati tra tutte le righe */}
            <div className="grid min-h-[3.25rem] grid-cols-[auto_minmax(0,1fr)] grid-rows-[auto_auto] gap-x-2 gap-y-1 py-2.5 sm:hidden">
                <div className="row-span-2 flex items-center self-center">{checkbox}</div>
                <Link
                    href={detailHref}
                    className="contents active:opacity-80"
                    aria-label={
                        isPac
                            ? `${title}, generata da PAC`
                            : isRecurring
                              ? `${title}, generata da ricorrenza`
                              : title
                    }
                >
                    <div className="col-start-2 flex min-h-4 min-w-0 items-center justify-between gap-2 overflow-hidden">
                        <p className={titleClassName} title={title}>
                            {title}
                        </p>
                        <p className={amountClassName}>{formattedAmount}</p>
                    </div>
                    {isFuture && transaction.projected_balance_after != null ? (
                        <p className="col-start-2 text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                            Saldo previsto dopo: {formatCurrency(transaction.projected_balance_after, transaction.account.currency_code)}
                        </p>
                    ) : null}
                    <div className="col-start-2 flex min-h-4 min-w-0 items-center gap-1 overflow-hidden">
                        <span
                            className="min-w-0 truncate text-[11px] leading-none text-gray-500 dark:text-gray-400"
                            title={metaLabel}
                        >
                            {formatDateShort(transaction.date)} · {transaction.account.name}
                        </span>
                        {mobileIndicators ? (
                            <div className="flex shrink-0 items-center gap-1">{mobileIndicators}</div>
                        ) : null}
                    </div>
                </Link>
            </div>

            {/* Desktop */}
            <div className="hidden min-h-[4.25rem] grid-cols-[auto_auto_1fr_auto_auto] items-center gap-x-3 px-4 py-2 sm:grid">
                {checkbox}

                <div
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-base"
                    style={avatarStyle}
                    aria-hidden
                >
                    {isTransfer ? '🔄' : isRefund ? '💸' : transaction.category?.icon || (isIncome ? '💰' : '💸')}
                </div>

                <Link
                    href={detailHref}
                    className="min-w-0"
                    aria-label={
                        isPac
                            ? `${title}, generata da PAC`
                            : isRecurring
                              ? `${title}, generata da ricorrenza`
                              : title
                    }
                >
                    <p className={titleClassName} title={title}>
                        {title}
                    </p>
                    <p className="mt-0.5 truncate text-xs leading-4 text-gray-500 dark:text-gray-400">
                        {formatDate(transaction.date)} · {transaction.account.name}
                    </p>
                </Link>

                <div className="flex min-h-6 min-w-0 items-center gap-1">
                    {desktopIndicators}
                    {isRecurring && recurringFrequencyLabel ? (
                        <span className="hidden rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-200 lg:inline">
                            {recurringFrequencyLabel}
                        </span>
                    ) : null}
                </div>

                <div className="flex shrink-0 flex-col items-end justify-center gap-0.5">
                    <p className={amountClassName}>{formattedAmount}</p>
                    {isFuture && transaction.projected_balance_after != null ? (
                        <p className="text-[10px] leading-tight text-gray-500 dark:text-gray-400">
                            Saldo previsto: {formatCurrency(transaction.projected_balance_after, transaction.account.currency_code)}
                        </p>
                    ) : null}
                    <div className="flex items-center gap-1">
                        <Link
                            href={detailHref}
                            className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                            title="Visualizza"
                        >
                            <EyeIcon size={16} />
                        </Link>
                        {!isVirtual ? (
                            <>
                                <Link
                                    href={route('transactions.edit', { transaction: transaction.id, ...indexQuery })}
                                    className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                                    title="Modifica"
                                >
                                    <PencilIcon size={16} />
                                </Link>
                                {investmentActionHref ? (
                                    <Link
                                        href={investmentActionHref}
                                        className={clsx(
                                            'rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 dark:hover:bg-gray-700',
                                            isPac
                                                ? 'hover:text-sky-600 dark:hover:text-sky-400'
                                                : 'hover:text-indigo-600 dark:hover:text-indigo-400',
                                        )}
                                        title={isPac ? 'Apri il piano PAC collegato' : "Gestisci dall'investimento collegato"}
                                    >
                                        <span className="text-sm" aria-hidden>
                                            {isPac ? '📊' : '📈'}
                                        </span>
                                    </Link>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onDeleteClick({
                                                id: transaction.id,
                                                description: title,
                                                isInvestment,
                                            })
                                        }
                                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                                        title="Elimina"
                                    >
                                        <TrashIcon size={16} />
                                    </button>
                                )}
                            </>
                        ) : null}
                    </div>
                </div>
            </div>
        </div>
    );
}
