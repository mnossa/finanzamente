import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { formatCurrency, formatDate } from '@/utils/format';

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
    category: TransactionListRowCategory | null;
    account: TransactionListRowAccount;
    tags: TransactionListRowTag[];
    recurring_transaction_id: number | null;
}

export type TransactionListIndexQuery = Record<string, string | number>;

interface TransactionListRowProps {
    transaction: TransactionListRowTransaction;
    onDeleteClick: (id: number, description: string) => void;
    isSelected: boolean;
    onToggleSelect: (id: number) => void;
    indexQuery: TransactionListIndexQuery;
}

export default function TransactionListRow({
    transaction,
    onDeleteClick,
    isSelected,
    onToggleSelect,
    indexQuery,
}: TransactionListRowProps) {
    const isIncome = transaction.amount > 0;
    const isTransfer = transaction.transfer_id !== null;
    const isRefund = transaction.refund_id !== null;
    const hasRefunds = transaction.has_refunds;
    const isRecurring = transaction.recurring_transaction_id !== null;

    const title = transaction.description || transaction.category?.name || 'Transazione';

    return (
        <div
            className={clsx(
                'group border-b border-gray-100 py-2.5 transition-colors last:border-0 -mx-4 px-3 sm:flex sm:flex-row sm:items-center sm:py-3 sm:px-4',
                isRecurring && 'border-l-4 border-violet-500 pl-2 dark:border-violet-400',
                isSelected
                    ? 'bg-emerald-50 dark:bg-emerald-900/20'
                    : 'hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50',
            )}
            {...(isRecurring ? { title: 'Generata da ricorrenza' } : {})}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-1 sm:contents">
                <div className="flex min-w-0 items-start gap-2">
                    <input
                        type="checkbox"
                        checked={isSelected}
                        onChange={() => onToggleSelect(transaction.id)}
                        className="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 sm:mt-0 sm:mr-2"
                        onClick={(e) => e.stopPropagation()}
                        aria-label={`Seleziona ${title}`}
                    />

                    <div
                        className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-base sm:mr-3 sm:h-9 sm:w-9"
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
                        aria-hidden
                    >
                        {isTransfer ? '🔄' : isRefund ? '💸' : transaction.category?.icon || (isIncome ? '💰' : '💸')}
                    </div>

                    <Link
                        href={route('transactions.show', { transaction: transaction.id, ...indexQuery })}
                        className="min-h-[44px] min-w-0 flex-1 sm:min-h-0"
                        aria-label={isRecurring ? `${title}, generata da ricorrenza` : title}
                    >
                        <div className="flex items-start justify-between gap-2 sm:block">
                            <p className="line-clamp-2 text-[15px] font-medium leading-snug text-gray-900 dark:text-white sm:truncate sm:text-sm">
                                {title}
                                {transaction.is_private && (
                                    <span className="ml-1.5 inline-flex shrink-0 items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        🔒
                                    </span>
                                )}
                                {transaction.is_tax_deductible && (
                                    <span className="ml-1.5 inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        📋
                                    </span>
                                )}
                            </p>
                            <p
                                className={clsx(
                                    'shrink-0 text-sm font-semibold tabular-nums sm:hidden',
                                    isIncome ? 'text-green-500' : 'text-red-500',
                                )}
                            >
                                {isIncome ? '+' : ''}
                                {formatCurrency(transaction.amount, transaction.account.currency_code)}
                            </p>
                        </div>

                        <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400 sm:truncate">
                            {transaction.account.name} · {formatDate(transaction.date)}
                            {isTransfer && <span className="ml-1 text-amber-500">· Trasferimento</span>}
                            {isRefund && <span className="ml-1 text-blue-500">· Rimborso</span>}
                            {hasRefunds && (
                                <span
                                    className={clsx(
                                        'ml-1',
                                        transaction.is_fully_refunded ? 'text-green-500' : 'text-amber-500',
                                    )}
                                >
                                    · {transaction.is_fully_refunded ? '✓ Rimborsato' : '◐ Parz. rimborsato'}
                                </span>
                            )}
                        </p>

                        {transaction.tags && transaction.tags.length > 0 && (
                            <div className="mt-1 flex gap-1 overflow-hidden sm:flex-wrap sm:overflow-visible">
                                {transaction.tags.map((tag) => (
                                    <span
                                        key={tag.id}
                                        className="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium sm:shrink"
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
                </div>
            </div>

            <div className="ml-10 flex shrink-0 items-center justify-end gap-1 sm:ml-2 sm:gap-2">
                <p
                    className={clsx(
                        'hidden text-base font-semibold tabular-nums sm:block',
                        isIncome ? 'text-green-500' : 'text-red-500',
                    )}
                >
                    {isIncome ? '+' : ''}
                    {formatCurrency(transaction.amount, transaction.account.currency_code)}
                </p>
                <div className="hidden items-center gap-1 sm:flex">
                    <Link
                        href={route('transactions.show', { transaction: transaction.id, ...indexQuery })}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={16} />
                    </Link>
                    <Link
                        href={route('transactions.edit', { transaction: transaction.id, ...indexQuery })}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                    >
                        <PencilIcon size={16} />
                    </Link>
                    <button
                        type="button"
                        onClick={() =>
                            onDeleteClick(transaction.id, title)
                        }
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </button>
                </div>
                <span className="text-gray-300 dark:text-gray-600 sm:hidden" aria-hidden>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </span>
            </div>
        </div>
    );
}
