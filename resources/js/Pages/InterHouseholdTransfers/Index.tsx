import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import CashflowHubNav from '@/Components/CashflowHubNav';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import IndexFiltersPanel from '@/Components/Index/IndexFiltersPanel';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexListCard from '@/Components/Index/IndexListCard';
import InterHouseholdTransferListRow from '@/Components/InterHouseholdTransfers/InterHouseholdTransferListRow';
import { IndexPageHeaderActions, IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import PlusIcon from '@/Components/Icons/PlusIcon';
import { Pagination } from '@/Components/Pagination';
import { useState } from 'react';

interface Household {
    id: number;
    name: string;
}

interface Account {
    id: number;
    name: string;
}

interface User {
    id: number;
    first_name: string;
    last_name: string;
}

interface InterHouseholdTransfer {
    id: number;
    uuid: string;
    source_household: Household;
    destination_household: Household;
    source_account: Account;
    destination_account: Account;
    source_user: User;
    destination_user: User | null;
    source_amount: number;
    source_currency: string;
    dest_amount: number;
    dest_currency: string;
    exchange_rate: number | null;
    fee: number | null;
    description: string | null;
    transfer_date: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled' | 'completed';
    approved_at: string | null;
    approved_by: User | null;
    rejected_at: string | null;
    rejected_by: User | null;
    rejection_reason: string | null;
    created_at: string;
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
    transfers: PaginatedData<InterHouseholdTransfer>;
    filters: {
        status?: string;
        direction?: 'sent' | 'received' | 'all';
        sort_by?: string;
        sort_direction?: 'asc' | 'desc';
    };
}

export default function Index({ transfers, filters }: IndexProps) {
    const [activeStatus, setActiveStatus] = useState(filters.status || 'all');
    const [activeDirection, setActiveDirection] = useState(filters.direction || 'all');

    const hasFilters = activeDirection !== 'all' || activeStatus !== 'all';
    const isEmpty = transfers.data.length === 0;

    const handleFilterChange = (type: 'status' | 'direction', value: 'sent' | 'received' | 'all') => {
        const newFilters = { ...filters };

        if (type === 'status') {
            setActiveStatus(value);
            if (value === 'all') {
                delete newFilters.status;
            } else {
                newFilters.status = value;
            }
        } else {
            setActiveDirection(value);
            if (value === 'all') {
                delete newFilters.direction;
            } else {
                newFilters.direction = value as 'sent' | 'received';
            }
        }

        router.get(route('inter-household-transfers.index'), newFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Trasferimenti tra Households"
                    mobileTitle="Trasf. HH"
                    backLink={route('transactions.index')}
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('inter-household-transfers.create')}>
                                <PlusIcon className="mr-2 h-4 w-4" />
                                Nuovo Trasferimento
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Trasferimenti tra Households" />

            <PageContent maxWidth="7xl">
                <CashflowHubNav active="inter-household" />
                <IndexIntroSection
                    label="Trasferimenti household"
                    icon={<span className="text-sm leading-none">🏠</span>}
                    description="Supervisiona movimenti tra household con stato e direzione sempre chiari."
                />
                <IndexPageMobileToolbar>
                    <LinkButton href={route('inter-household-transfers.create')} size="sm">
                        <PlusIcon className="mr-2 h-4 w-4" />
                        Nuovo Trasferimento
                    </LinkButton>
                </IndexPageMobileToolbar>

                <IndexFiltersPanel
                    defaultOpen={hasFilters}
                    activeBadge={
                        hasFilters ? (
                            <span className="inline-flex items-center justify-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400">
                                attivi
                            </span>
                        ) : undefined
                    }
                >
                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Direzione
                        </label>
                        <div className="flex flex-wrap gap-1.5 sm:gap-2">
                            {[
                                { value: 'all', label: 'Tutti' },
                                { value: 'sent', label: 'Inviati' },
                                { value: 'received', label: 'Ricevuti' },
                            ].map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => handleFilterChange('direction', option.value as 'sent' | 'received' | 'all')}
                                    className={clsx(
                                        'rounded-lg px-3 py-1.5 text-sm font-medium transition sm:px-4 sm:py-2',
                                        activeDirection === option.value
                                            ? 'bg-indigo-600 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </IndexFiltersPanel>

                <IndexListCard
                    isEmpty={isEmpty}
                    empty={
                        <IndexEmptyList
                            icon="🏠"
                            title="Nessun trasferimento"
                            description="Non hai ancora effettuato trasferimenti tra households diverse."
                            showCreateButton={false}
                        >
                            <LinkButton href={route('inter-household-transfers.create')} className="mt-4">
                                <PlusIcon className="mr-2 h-4 w-4" />
                                Crea il tuo primo trasferimento
                            </LinkButton>
                        </IndexEmptyList>
                    }
                    footer={!isEmpty ? <Pagination data={transfers} /> : undefined}
                >
                    {transfers.data.map((transfer) => (
                        <InterHouseholdTransferListRow key={transfer.id} transfer={transfer} />
                    ))}
                </IndexListCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
