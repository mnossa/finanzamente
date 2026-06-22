import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import PacListCard from '@/Components/InvestmentPacs/PacListCard';
import LinkButton from '@/Components/LinkButton';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import InvestmentHubNav from '@/Components/InvestmentHubNav';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import PlusIcon from '@/Components/Icons/PlusIcon';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, router } from '@inertiajs/react';
import React from 'react';

interface Pac {
    id: number;
    amount: number;
    adjust_for_inflation: boolean;
    inflation_rate_annual: number | null;
    currency_code: string;
    frequency: string;
    start_date: string;
    end_date: string | null;
    last_executed_at: string | null;
    next_execution_date?: string | null;
    status: string;
    notes: string | null;
    investments_count: number;
    asset: { id: number; name: string; symbol: string; isin: string | null };
    account: { id: number; name: string } | null;
}

export default function InvestmentPacIndex({ pacs }: { pacs: Pac[] }) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const closeDeleteDialog = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('investment-pacs.destroy', deleteTarget.id));
        closeDeleteDialog();
    };

    const runNow = (pacId: number) => {
        router.post(route('investment-pacs.run-now', pacId));
    };

    const toggleStatus = (pacId: number) => {
        router.post(route('investment-pacs.toggle-status', pacId));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="PAC — Piani di accumulo"
                    mobileTitle="PAC"
                    backLink={route('investments.index')}
                    subtitle="Versamenti ricorrenti su ETF, fondi e altri strumenti"
                    hideSubtitleOnMobile
                    actions={<LinkButton href={route('investment-pacs.create')}>Nuovo PAC</LinkButton>}
                />
            }
        >
            <Head title="PAC Investimenti" />
            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Elimina PAC"
                description={deleteTarget ? `Confermi l'eliminazione di ${deleteTarget.description}? I movimenti già generati restano disponibili in Investimenti.` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={confirmDelete}
                onCancel={closeDeleteDialog}
            />
            <PageContent>
                <InvestmentHubNav active="pacs" />
                <IndexIntroSection
                    label="PAC"
                    icon={<span className="text-sm leading-none">📈</span>}
                    description="Versamenti ricorrenti su ETF, fondi e altri strumenti. Ogni esecuzione genera un movimento nello storico investimenti."
                />
                <IndexPageMobileToolbar>
                    <LinkButton
                        href={route('investment-pacs.create')}
                        icon={<PlusIcon />}
                        size="sm"
                    >
                        Nuovo PAC
                    </LinkButton>
                </IndexPageMobileToolbar>

                {pacs.length === 0 ? (
                    <IndexEmptyList
                        icon="📈"
                        title="Nessun PAC configurato"
                        description="Crea il primo piano di accumulo per automatizzare i versamenti."
                        createUrl={route('investment-pacs.create')}
                        createLabel="Nuovo PAC"
                    />
                ) : (
                    <div className="space-y-3">
                        {pacs.map((pac) => (
                            <PacListCard
                                key={pac.id}
                                pac={pac}
                                onRunNow={runNow}
                                onToggleStatus={toggleStatus}
                                onDelete={openDeleteDialog}
                            />
                        ))}
                    </div>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
