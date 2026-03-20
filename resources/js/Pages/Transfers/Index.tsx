import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import CardBox from '@/Components/CardBox';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Account {
    id: number;
    name: string;
}

interface Transfer {
    id: number;
    uuid: string;
    source_amount: number;
    source_currency: string;
    dest_amount: number;
    dest_currency: string;
    exchange_rate: number | null;
    fee: number | null;
    status: string;
    created_at: string;
    source_account: Account | null;
    destination_account: Account | null;
    user: { id: number; name: string } | null;
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

interface IndexProps {
    transfers: PaginatedData<Transfer>;
}


function TransferRow({ transfer, onDeleteClick }: { transfer: Transfer; onDeleteClick: (id: number, description: string) => void }) {
    const isSameCurrency = transfer.source_currency === transfer.dest_currency;

    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-4 last:border-0 dark:border-gray-700">
            <div className="flex items-center space-x-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-2xl dark:bg-emerald-900/30">
                    🔄
                </div>
                <div>
                    <div className="flex items-center space-x-2">
                        <span className="font-medium text-gray-900 dark:text-white">
                            {transfer.source_account?.name || 'Conto eliminato'}
                        </span>
                        <span className="text-gray-400">→</span>
                        <span className="font-medium text-gray-900 dark:text-white">
                            {transfer.destination_account?.name || 'Conto eliminato'}
                        </span>
                    </div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {transfer.created_at}
                        {transfer.user && ` • ${transfer.user.name}`}
                    </p>
                </div>
            </div>
            <div className="flex items-center space-x-4">
                <div className="text-right">
                    <p className="font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(transfer.source_amount, transfer.source_currency)}
                    </p>
                    {!isSameCurrency && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            → {formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                        </p>
                    )}
                    {transfer.fee && transfer.fee > 0 && (
                        <p className="text-xs text-orange-500">
                            Comm. {formatCurrency(transfer.fee, transfer.source_currency)}
                        </p>
                    )}
                </div>
                <div className="flex space-x-2">
                    <Link
                        href={route('transfers.show', transfer.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={18} />
                    </Link>
                    <button
                        onClick={() => onDeleteClick(transfer.id, `il trasferimento da ${transfer.source_account?.name || 'conto'} a ${transfer.destination_account?.name || 'conto'}`)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                    >
                        <TrashIcon size={18} />
                    </button>
                </div>
            </div>
        </div>
    );
}


export default function Index({ transfers }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('transfers.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Trasferimenti"
                    actions={
                        <LinkButton
                            href={route('transfers.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo Trasferimento
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Trasferimenti" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma annullamento"
                description={deleteTarget ? `Sei sicuro di voler annullare ${deleteTarget.description}?` : undefined}
                confirmLabel="Annulla Trasferimento"
                cancelLabel="Chiudi"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
                variant="warning"
            />

            <PageContent>
                    <CardBox className="overflow-hidden shadow-sm">
                        {transfers.data.length > 0 ? (
                            <>
                                <div className="p-4">
                                    {transfers.data.map((transfer) => (
                                        <TransferRow key={transfer.id} transfer={transfer} onDeleteClick={openDeleteDialog} />
                                    ))}
                                </div>
                                <Pagination data={transfers} />
                            </>
                        ) : (
                            <EmptyState
                                icon="🔄"
                                title="Nessun trasferimento"
                                description="Trasferisci fondi tra i tuoi conti in modo semplice e veloce."
                                createUrl={route('transfers.create')}
                                createLabel="Nuovo Trasferimento"
                            />
                        )}
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
