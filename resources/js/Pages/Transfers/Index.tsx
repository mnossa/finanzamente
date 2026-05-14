import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
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
        <div className="flex items-center border-b border-gray-100 py-3 last:border-0 -mx-4 px-3 sm:px-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
            <div className="mr-3 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-lg dark:bg-emerald-900/30">
                🔄
            </div>
            <Link href={route('transfers.show', transfer.id)} className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-gray-900 dark:text-white">
                    {transfer.source_account?.name || 'Conto eliminato'}
                    <span className="mx-1 text-gray-400">→</span>
                    {transfer.destination_account?.name || 'Conto eliminato'}
                </p>
                <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {transfer.created_at}{transfer.user && ` • ${transfer.user.name}`}
                </p>
            </Link>
            <div className="ml-2 flex shrink-0 items-center gap-1 sm:gap-2">
                <div className="text-right">
                    <p className="text-sm font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(transfer.source_amount, transfer.source_currency)}
                    </p>
                    {!isSameCurrency && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            → {formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                        </p>
                    )}
                    {transfer.fee && transfer.fee > 0 && (
                        <p className="text-xs text-orange-500">
                            +{formatCurrency(transfer.fee, transfer.source_currency)}
                        </p>
                    )}
                </div>
                <div className="hidden sm:flex items-center gap-1">
                    <Link
                        href={route('transfers.show', transfer.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={16} />
                    </Link>
                    <button
                        onClick={() => onDeleteClick(transfer.id, `il trasferimento da ${transfer.source_account?.name || 'conto'} a ${transfer.destination_account?.name || 'conto'}`)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </button>
                </div>
                <span className="sm:hidden text-gray-300 dark:text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </span>
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

            <PageContent maxWidth="7xl">
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Trasferimenti" icon={<span className="text-sm leading-none">🔄</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Sposta fondi tra conti con tracciamento completo importi, valute e commissioni.
                            </p>
                        </div>
                    </SectionCard>
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
