import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import OrganizationHubNav from '@/Components/OrganizationHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions, IndexPageMobileToolbar, MobileCreateLinkButton } from '@/Components/IndexPageListToolbars';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexListCard from '@/Components/Index/IndexListCard';
import IndexListHeader from '@/Components/Index/IndexListHeader';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import RecurringFrequencyBadge from '@/Components/RecurringTransactions/RecurringFrequencyBadge';
import RecurringListRow from '@/Components/RecurringTransactions/RecurringListRow';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import CardBox from '@/Components/CardBox';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { formatCurrency } from '@/utils/format';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    type: 'income' | 'expense';
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Frequencies {
    [key: string]: string;
}

interface RecurringTransaction {
    id: number;
    amount: number;
    frequency: string;
    frequency_label: string;
    day_of_month_mode: 'start_date' | 'fixed' | 'last_day';
    day_of_month: number | null;
    non_working_day_policy: 'postpone' | 'anticipate' | 'keep';
    start_date: string;
    end_date: string | null;
    description: string | null;
    next_due_date: string | null;
    is_active: boolean;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
}

interface IndexProps {
    recurringTransactions: RecurringTransaction[];
    frequencies: Frequencies;
}

export default function Index({ recurringTransactions, frequencies }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('recurring-transactions.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const activeTransactions = recurringTransactions.filter((rt) => rt.is_active);
    const inactiveTransactions = recurringTransactions.filter((rt) => !rt.is_active);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Transazioni Ricorrenti"
                    mobileTitle="Ricorrenti"
                    backLink={route('categories.index')}
                    hideSubtitleOnMobile
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('recurrence-detection.index')}>
                                🔍 Ricorrenze Rilevate
                            </LinkButton>
                            <LinkButton href={route('recurring-transactions.create')} icon={<PlusIcon />}>
                                Nuova Ricorrenza
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Transazioni Ricorrenti" />

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
                <OrganizationHubNav active="recurring" />
                <IndexIntroSection
                    label="Ricorrenze"
                    icon={<span className="text-sm leading-none">🔁</span>}
                    description="Gestisci operazioni periodiche automatiche e controlla le prossime scadenze."
                />
                <IndexPageMobileToolbar>
                    <LinkButton href={route('recurrence-detection.index')} variant="secondary" size="sm">
                        🔍 Rilevate
                    </LinkButton>
                    <MobileCreateLinkButton href={route('recurring-transactions.create')} icon={<PlusIcon />} size="sm">
                        Nuova
                    </MobileCreateLinkButton>
                </IndexPageMobileToolbar>

                <IndexKpiStrip>
                    {Object.entries(frequencies).map(([key, label]) => {
                        const count = activeTransactions.filter((rt) => rt.frequency === key).length;
                        const total = activeTransactions
                            .filter((rt) => rt.frequency === key)
                            .reduce((sum, rt) => sum + rt.amount, 0);

                        return (
                            <CardBox key={key} className="p-3 shadow-sm sm:p-4">
                                <RecurringFrequencyBadge frequency={key} frequencyLabel={label} />
                                <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{count}</p>
                                <p className={clsx('text-sm font-medium', total >= 0 ? 'text-green-500' : 'text-red-500')}>
                                    {formatCurrency(total)}
                                </p>
                            </CardBox>
                        );
                    })}
                </IndexKpiStrip>

                <IndexListCard
                    header={<IndexListHeader title={`Attive (${activeTransactions.length})`} />}
                    isEmpty={activeTransactions.length === 0}
                    empty={
                        <IndexEmptyList
                            icon="🔄"
                            title="Nessuna transazione ricorrente"
                            description="Crea una transazione ricorrente per automatizzare le operazioni periodiche."
                            createUrl={route('recurring-transactions.create')}
                            createLabel="Nuova Ricorrenza"
                        />
                    }
                >
                    {activeTransactions.map((rt) => (
                        <RecurringListRow key={rt.id} rt={rt} onDeleteClick={openDeleteDialog} />
                    ))}
                </IndexListCard>

                {inactiveTransactions.length > 0 && (
                    <IndexListCard
                        header={
                            <IndexListHeader
                                title={`Terminate (${inactiveTransactions.length})`}
                                titleClassName="text-gray-500 dark:text-gray-400"
                            />
                        }
                    >
                        {inactiveTransactions.map((rt) => (
                            <RecurringListRow key={rt.id} rt={rt} onDeleteClick={openDeleteDialog} />
                        ))}
                    </IndexListCard>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
