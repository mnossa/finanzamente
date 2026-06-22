import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import OrganizationHubNav from '@/Components/OrganizationHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexInfoBanner from '@/Components/Index/IndexInfoBanner';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexListCard from '@/Components/Index/IndexListCard';
import RefundListRow from '@/Components/Refunds/RefundListRow';
import LinkButton from '@/Components/LinkButton';
import { Head, router } from '@inertiajs/react';
import { Pagination } from '@/Components/Pagination';
import PlusIcon from '@/Components/Icons/PlusIcon';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

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

interface RefundTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
}

interface Refund {
    id: number;
    uuid: string;
    amount: number;
    currency_code: string;
    status: string;
    description: string | null;
    created_at: string;
    original_transaction: OriginalTransaction | null;
    refund_transaction: RefundTransaction | null;
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
    refunds: PaginatedData<Refund>;
}

export default function Index({ refunds }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('refunds.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const isEmpty = refunds.data.length === 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Rimborsi"
                    backLink={route('categories.index')}
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('refunds.create')} icon={<PlusIcon />}>
                                Nuovo Rimborso
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Rimborsi" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare ${deleteTarget.description}?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <PageContent maxWidth="7xl">
                <OrganizationHubNav active="refunds" />
                <IndexIntroSection
                    label="Rimborsi"
                    icon={<span className="text-sm leading-none">💸</span>}
                    description="Traccia i rimborsi ricevuti e il loro impatto sulle spese originarie."
                />
                <IndexInfoBanner
                    title="Cosa sono i rimborsi?"
                    description="I rimborsi ti permettono di tracciare quando ricevi indietro soldi per una spesa già effettuata. Ad esempio: resi di prodotti, rimborsi assicurativi, o restituzione di depositi."
                />
                <IndexListCard
                    isEmpty={isEmpty}
                    empty={
                        <IndexEmptyList
                            icon="💸"
                            title="Nessun rimborso registrato"
                            description="Registra un rimborso quando ricevi indietro soldi per una spesa."
                            createUrl={route('refunds.create')}
                            createLabel="Nuovo Rimborso"
                        />
                    }
                    footer={!isEmpty ? <Pagination data={refunds} /> : undefined}
                >
                    {refunds.data.map((refund) => (
                        <RefundListRow key={refund.id} refund={refund} onDeleteClick={openDeleteDialog} />
                    ))}
                </IndexListCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
