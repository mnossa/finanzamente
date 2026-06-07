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
    attachments_count?: number;
    category: TransactionListRowCategory | null;
    account: TransactionListRowAccount;
    tags: TransactionListRowTag[];
    recurring_transaction_id: number | null;
    investment_id: number | null;
    is_investment?: boolean;
}

export type TransactionListIndexQuery = Record<string, string | number>;

interface TransactionListRowProps {
    transaction: TransactionListRowTransaction;
    onDeleteClick: (target: { id: number; description: string; isInvestment: boolean }) => void;
    isSelected: boolean;
    onToggleSelect: (id: number) => void;
    indexQuery: TransactionListIndexQuery;
}

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
                'inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs',
                className,
            )}
        >
            {children}
        </span>
    );
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
    const isInvestment = transaction.investment_id !== null;
    const hasAttachments = (transaction.attachments_count ?? 0) > 0;
    const hasTags = transaction.tags.length > 0;

    const title = transaction.description || transaction.category?.name || 'Transazione';

    const indicators = [
        isRecurring ? (
            <RowIndicator key="recurring" label="Generata da ricorrenza" className="bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                🔁
            </RowIndicator>
        ) : null,
        isInvestment ? (
            <RowIndicator key="investment" label="Collegata a un investimento" className="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                📈
            </RowIndicator>
        ) : null,
        isTransfer ? (
            <RowIndicator key="transfer" label="Trasferimento tra conti" className="bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                🔄
            </RowIndicator>
        ) : null,
        isRefund ? (
            <RowIndicator key="refund" label="Rimborso" className="bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                💸
            </RowIndicator>
        ) : null,
        hasRefunds ? (
            <RowIndicator
                key="refunded"
                label={transaction.is_fully_refunded ? 'Rimborsato per intero' : 'Rimborso parziale'}
                className={clsx(
                    transaction.is_fully_refunded
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                        : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                )}
            >
                {transaction.is_fully_refunded ? '✓' : '◐'}
            </RowIndicator>
        ) : null,
        transaction.is_private ? (
            <RowIndicator key="private" label="Transazione privata" className="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                🔒
            </RowIndicator>
        ) : null,
        transaction.is_tax_deductible ? (
            <RowIndicator key="tax" label="Detraibile fiscalmente" className="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                📋
            </RowIndicator>
        ) : null,
        hasAttachments ? (
            <RowIndicator key="attachments" label="Allegati presenti" className="bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                📎
            </RowIndicator>
        ) : null,
        hasTags ? (
            <RowIndicator key="tags" label={`${transaction.tags.length} etichett${transaction.tags.length === 1 ? 'a' : 'e'}`} className="bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">
                🏷️
            </RowIndicator>
        ) : null,
    ].filter(Boolean);

    return (
        <div
            className={clsx(
                'group grid min-h-[4.25rem] grid-cols-[auto_auto_1fr_auto] items-center gap-x-2 border-b border-gray-100 py-2 transition-colors last:border-0 -mx-4 px-3 sm:grid-cols-[auto_auto_1fr_auto_auto] sm:gap-x-3 sm:px-4',
                isRecurring && 'border-l-4 border-l-violet-500 pl-2 dark:border-l-violet-400',
                isInvestment && !isRecurring && 'border-l-4 border-l-indigo-500 pl-2 dark:border-l-indigo-400',
                isSelected
                    ? 'bg-emerald-50 dark:bg-emerald-900/20'
                    : 'hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50',
            )}
            {...(isRecurring ? { title: 'Generata da ricorrenza' } : {})}
        >
            <input
                type="checkbox"
                checked={isSelected}
                onChange={() => onToggleSelect(transaction.id)}
                className="h-4 w-4 shrink-0 cursor-pointer rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
                onClick={(e) => e.stopPropagation()}
                aria-label={`Seleziona ${title}`}
            />

            <div
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base sm:h-10 sm:w-10"
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
                className="min-w-0"
                aria-label={isRecurring ? `${title}, generata da ricorrenza` : title}
            >
                <p className="truncate text-sm font-medium text-gray-900 dark:text-white">{title}</p>
                <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                    {formatDate(transaction.date)} · {transaction.account.name}
                </p>
                {indicators.length > 0 && (
                    <div className="mt-1 flex items-center gap-1 sm:hidden">{indicators}</div>
                )}
            </Link>

            <div className="hidden min-h-6 min-w-0 items-center gap-1 sm:flex">
                {indicators}
            </div>

            <div className="flex shrink-0 items-center justify-end gap-1 sm:gap-2">
                <p
                    className={clsx(
                        'text-sm font-semibold tabular-nums sm:text-base',
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
                    {isInvestment ? (
                        <Link
                            href={route('investments.show', transaction.investment_id!)}
                            className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-indigo-600 dark:hover:bg-gray-700 dark:hover:text-indigo-400"
                            title="Gestisci dall'investimento collegato"
                        >
                            <span className="text-sm" aria-hidden>📈</span>
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
