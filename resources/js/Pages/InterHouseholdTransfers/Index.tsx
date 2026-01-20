import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import PlusIcon from '@/Components/Icons/PlusIcon';
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

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const STATUS_LABELS = {
    pending: 'In attesa',
    approved: 'Approvato',
    rejected: 'Rifiutato',
    cancelled: 'Annullato',
    completed: 'Completato',
};

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
};

function TransferRow({ transfer }: { transfer: InterHouseholdTransfer }) {
    return (
        <Link
            href={route('inter-household-transfers.show', transfer.id)}
            className="block border-b border-gray-100 py-4 transition hover:bg-gray-50 last:border-0 dark:border-gray-700 dark:hover:bg-gray-800/50"
        >
            <div className="flex items-start justify-between">
                <div className="flex items-start space-x-4">
                    <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-2xl dark:bg-indigo-900/30">
                        🏠
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="mb-1 flex items-center space-x-2 text-sm">
                            <span className="font-medium text-gray-900 dark:text-white">
                                {transfer.source_household.name}
                            </span>
                            <span className="text-gray-400">→</span>
                            <span className="font-medium text-gray-900 dark:text-white">
                                {transfer.destination_household.name}
                            </span>
                        </div>
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            <span>{transfer.source_account.name}</span>
                            <span className="mx-2">→</span>
                            <span>{transfer.destination_account.name}</span>
                        </div>
                        {transfer.description && (
                            <p className="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                                {transfer.description}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            {formatDate(transfer.transfer_date)}
                        </p>
                    </div>
                </div>
                <div className="ml-4 flex-shrink-0 text-right">
                    <div className="text-lg font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(transfer.source_amount, transfer.source_currency)}
                    </div>
                    {transfer.source_currency !== transfer.dest_currency && (
                        <div className="text-sm text-gray-500 dark:text-gray-400">
                            {formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                        </div>
                    )}
                    {transfer.fee && transfer.fee > 0 && (
                        <div className="text-xs text-gray-400 dark:text-gray-500">
                            Comm. {formatCurrency(transfer.fee, transfer.source_currency)}
                        </div>
                    )}
                </div>
            </div>
        </Link>
    );
}

export default function Index({ transfers, filters }: IndexProps) {
    const [activeStatus, setActiveStatus] = useState(filters.status || 'all');
    const [activeDirection, setActiveDirection] = useState(filters.direction || 'all');

    const handleFilterChange = (type: 'status' | 'direction', value: "sent" | "received" | "all") => {
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
                    actions={
                        <LinkButton href={route('inter-household-transfers.create')}>
                            <PlusIcon className="mr-2 h-4 w-4" />
                            Nuovo Trasferimento
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Trasferimenti tra Households" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Filtri */}
                    <div className="mb-6 overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Direzione
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {[
                                        { value: 'all', label: 'Tutti' },
                                        { value: 'sent', label: 'Inviati' },
                                        { value: 'received', label: 'Ricevuti' },
                                    ].map((option) => (
                                        <button
                                            key={option.value}
                                            onClick={() => handleFilterChange('direction', option.value as 'sent' | 'received' | 'all')}
                                            className={clsx(
                                                'rounded-lg px-4 py-2 text-sm font-medium transition',
                                                activeDirection === option.value
                                                    ? 'bg-indigo-600 text-white'
                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                            )}
                                        >
                                            {option.label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Lista trasferimenti */}
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            {transfers.data.length === 0 ? (
                                <div className="text-center">
                                    <EmptyState
                                        icon="🏠"
                                        title="Nessun trasferimento"
                                        description="Non hai ancora effettuato trasferimenti tra households diverse."
                                    />
                                    <div className="mt-6">
                                        <LinkButton href={route('inter-household-transfers.create')}>
                                            <PlusIcon className="mr-2 h-4 w-4" />
                                            Crea il tuo primo trasferimento
                                        </LinkButton>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                                        {transfers.data.map((transfer) => (
                                            <TransferRow key={transfer.id} transfer={transfer} />
                                        ))}
                                    </div>

                                    {/* Paginazione */}
                                    {transfers.last_page > 1 && (
                                        <div className="mt-6 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                                            <div className="text-sm text-gray-700 dark:text-gray-300">
                                                Mostrando {transfers.from} - {transfers.to} di {transfers.total}{' '}
                                                risultati
                                            </div>
                                            <div className="flex gap-2">
                                                {transfers.links.map((link, index) => (
                                                    <Link
                                                        key={index}
                                                        href={link.url || '#'}
                                                        preserveState
                                                        preserveScroll
                                                        className={clsx(
                                                            'rounded px-3 py-1 text-sm',
                                                            link.active
                                                                ? 'bg-indigo-600 text-white'
                                                                : link.url
                                                                  ? 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                                                  : 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-800'
                                                        )}
                                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
