import IndexListRow from '@/Components/Index/IndexListRow';
import { IndexRowActionButton, IndexRowActionLink, IndexRowActions } from '@/Components/Index/IndexRowActions';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { formatCurrency } from '@/utils/format';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface OriginalTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    account: Account | null;
    category: Category | null;
}

export interface RefundListRowRefund {
    id: number;
    amount: number;
    currency_code: string;
    status: string;
    description: string | null;
    original_transaction: OriginalTransaction | null;
}

interface RefundListRowProps {
    refund: RefundListRowRefund;
    onDeleteClick: (id: number, description: string) => void;
}

function RefundStatusBadge({ status }: { status: string }): ReactNode {
    return (
        <span
            className={clsx(
                'inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                status === 'completed'
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : status === 'pending'
                      ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                      : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            )}
        >
            {status === 'completed' ? '✓' : status === 'pending' ? '⏳' : '✗'}{' '}
            {status === 'completed' ? 'Completato' : status === 'pending' ? 'In attesa' : 'Annullato'}
        </span>
    );
}

export default function RefundListRow({ refund, onDeleteClick }: RefundListRowProps): ReactNode {
    const originalTx = refund.original_transaction;
    const label = refund.description || 'Rimborso';

    const title = (
        <span className="inline-flex min-w-0 items-center gap-1">
            <span className="truncate">{label}</span>
            <RefundStatusBadge status={refund.status} />
        </span>
    );

    const subtitle = originalTx
        ? `${originalTx.description || originalTx.category?.name || 'Transazione'}${originalTx.account ? ` · ${originalTx.account.name}` : ''}`
        : undefined;

    const amount = (
        <span className="text-green-600 dark:text-green-400">
            +{formatCurrency(refund.amount, refund.currency_code)}
        </span>
    );

    const amountDetail = originalTx ? (
        <p className="text-xs text-gray-500 dark:text-gray-400">
            su {formatCurrency(Math.abs(originalTx.amount), refund.currency_code)}
        </p>
    ) : null;

    return (
        <IndexListRow
            href={route('refunds.show', refund.id)}
            ariaLabel={label}
            avatar="💸"
            avatarClassName="bg-blue-100 dark:bg-blue-900/30"
            title={title}
            subtitle={subtitle}
            amount={amount}
            amountDetail={amountDetail}
            actions={
                <IndexRowActions>
                    <IndexRowActionLink href={route('refunds.show', refund.id)} title="Visualizza">
                        <EyeIcon size={16} />
                    </IndexRowActionLink>
                    <IndexRowActionLink
                        href={route('refunds.edit', refund.id)}
                        title="Modifica"
                        hoverClassName="hover:text-blue-600 dark:hover:text-blue-400"
                    >
                        <PencilIcon size={16} />
                    </IndexRowActionLink>
                    <IndexRowActionButton
                        onClick={() => onDeleteClick(refund.id, refund.description || 'questo rimborso')}
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </IndexRowActionButton>
                </IndexRowActions>
            }
        />
    );
}
