import IndexListRow from '@/Components/Index/IndexListRow';
import { formatCurrency, formatDate } from '@/utils/format';
import { ReactNode } from 'react';

interface Household {
    id: number;
    name: string;
}

interface Account {
    id: number;
    name: string;
}

export interface InterHouseholdTransferListRowTransfer {
    id: number;
    source_household: Household;
    destination_household: Household;
    source_account: Account;
    destination_account: Account;
    source_amount: number;
    source_currency: string;
    dest_amount: number;
    dest_currency: string;
    fee: number | null;
    description: string | null;
    transfer_date: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled' | 'completed';
}

const STATUS_LABELS: Record<InterHouseholdTransferListRowTransfer['status'], string> = {
    pending: 'In attesa',
    approved: 'Approvato',
    rejected: 'Rifiutato',
    cancelled: 'Annullato',
    completed: 'Completato',
};

interface InterHouseholdTransferListRowProps {
    transfer: InterHouseholdTransferListRowTransfer;
}

export default function InterHouseholdTransferListRow({
    transfer,
}: InterHouseholdTransferListRowProps): ReactNode {
    const isSameCurrency = transfer.source_currency === transfer.dest_currency;

    const title = (
        <>
            {transfer.source_household.name}
            <span className="mx-1 text-gray-400">→</span>
            {transfer.destination_household.name}
        </>
    );

    const subtitleParts = [
        transfer.description,
        `${transfer.source_account.name} → ${transfer.destination_account.name}`,
        formatDate(transfer.transfer_date),
        STATUS_LABELS[transfer.status],
    ].filter(Boolean);

    const subtitle = subtitleParts.join(' · ');

    const amount = formatCurrency(transfer.source_amount, transfer.source_currency);

    const amountDetail = (
        <>
            {!isSameCurrency && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    → {formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                </p>
            )}
            {transfer.fee && transfer.fee > 0 ? (
                <p className="text-xs text-gray-400 dark:text-gray-500">
                    Comm. {formatCurrency(transfer.fee, transfer.source_currency)}
                </p>
            ) : null}
        </>
    );

    return (
        <IndexListRow
            href={route('inter-household-transfers.show', transfer.id)}
            ariaLabel={`Trasferimento da ${transfer.source_household.name} a ${transfer.destination_household.name}`}
            avatar="🏠"
            avatarClassName="bg-indigo-100 dark:bg-indigo-900/30"
            title={title}
            subtitle={subtitle}
            amount={amount}
            amountDetail={amountDetail}
        />
    );
}
