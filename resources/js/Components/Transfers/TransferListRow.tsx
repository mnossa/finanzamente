import IndexListRow from '@/Components/Index/IndexListRow';
import { IndexRowActionButton, IndexRowActionLink, IndexRowActions } from '@/Components/Index/IndexRowActions';
import EyeIcon from '@/Components/Icons/EyeIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { formatCurrency } from '@/utils/format';
import { ReactNode } from 'react';

interface Account {
    id: number;
    name: string;
}

export interface TransferListRowTransfer {
    id: number;
    source_amount: number;
    source_currency: string;
    dest_amount: number;
    dest_currency: string;
    fee: number | null;
    created_at: string;
    source_account: Account | null;
    destination_account: Account | null;
    user: { id: number; name: string } | null;
}

interface TransferListRowProps {
    transfer: TransferListRowTransfer;
    onDeleteClick: (id: number, description: string) => void;
}

export default function TransferListRow({ transfer, onDeleteClick }: TransferListRowProps): ReactNode {
    const isSameCurrency = transfer.source_currency === transfer.dest_currency;
    const sourceName = transfer.source_account?.name || 'Conto eliminato';
    const destName = transfer.destination_account?.name || 'Conto eliminato';
    const title = (
        <>
            {sourceName}
            <span className="mx-1 text-gray-400">→</span>
            {destName}
        </>
    );
    const subtitle = `${transfer.created_at}${transfer.user ? ` • ${transfer.user.name}` : ''}`;
    const amount = formatCurrency(transfer.source_amount, transfer.source_currency);
    const deleteDescription = `il trasferimento da ${sourceName} a ${destName}`;

    const amountDetail = (
        <>
            {!isSameCurrency && (
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    → {formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                </p>
            )}
            {transfer.fee && transfer.fee > 0 ? (
                <p className="text-xs text-orange-500">
                    +{formatCurrency(transfer.fee, transfer.source_currency)}
                </p>
            ) : null}
        </>
    );

    return (
        <IndexListRow
            href={route('transfers.show', transfer.id)}
            ariaLabel={`Trasferimento da ${sourceName} a ${destName}`}
            avatar="🔄"
            avatarClassName="bg-emerald-100 dark:bg-emerald-900/30"
            title={title}
            subtitle={subtitle}
            amount={amount}
            amountDetail={amountDetail}
            actions={
                <IndexRowActions>
                    <IndexRowActionLink href={route('transfers.show', transfer.id)} title="Visualizza">
                        <EyeIcon size={16} />
                    </IndexRowActionLink>
                    <IndexRowActionButton
                        onClick={() => onDeleteClick(transfer.id, deleteDescription)}
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </IndexRowActionButton>
                </IndexRowActions>
            }
        />
    );
}
