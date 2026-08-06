import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import MovementsHubNav from '@/Components/MovementsHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexListCard from '@/Components/Index/IndexListCard';
import TransferListRow from '@/Components/Transfers/TransferListRow';
import LinkButton from '@/Components/LinkButton';
import { Head, router } from '@inertiajs/react';
import { Pagination } from '@/Components/Pagination';
import PlusIcon from '@/Components/Icons/PlusIcon';
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

    const isEmpty = transfers.data.length === 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Trasferimenti"
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('transfers.create')} icon={<PlusIcon />}>
                                Nuovo Trasferimento
                            </LinkButton>
                        </IndexPageHeaderActions>
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
                <MovementsHubNav active="transfers" />
                <IndexListCard
                    isEmpty={isEmpty}
                    empty={
                        <IndexEmptyList
                            icon="🔄"
                            title="Nessun trasferimento"
                            description="Trasferisci fondi tra i tuoi conti in modo semplice e veloce."
                            createUrl={route('transfers.create')}
                            createLabel="Nuovo Trasferimento"
                        />
                    }
                    footer={!isEmpty ? <Pagination data={transfers} /> : undefined}
                >
                    {transfers.data.map((transfer) => (
                        <TransferListRow
                            key={transfer.id}
                            transfer={transfer}
                            onDeleteClick={openDeleteDialog}
                        />
                    ))}
                </IndexListCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
