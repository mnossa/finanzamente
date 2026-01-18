import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import PlusIcon from '@/Components/Icons/PlusIcon';

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

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function TransferRow({ transfer }: { transfer: Transfer }) {
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
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    >
                        👁️
                    </Link>
                    <button
                        onClick={() => {
                            if (confirm('Sei sicuro di voler annullare questo trasferimento?')) {
                                router.delete(route('transfers.destroy', transfer.id));
                            }
                        }}
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-500 dark:hover:bg-gray-700"
                    >
                        🗑️
                    </button>
                </div>
            </div>
        </div>
    );
}

function Pagination({ data }: { data: PaginatedData<Transfer> }) {
    if (data.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            <div className="text-sm text-gray-500 dark:text-gray-400">
                {data.from}-{data.to} di {data.total} trasferimenti
            </div>
            <div className="flex space-x-1">
                {data.links.map((link, index) => (
                    <button
                        key={index}
                        onClick={() => link.url && router.get(link.url)}
                        disabled={!link.url}
                        className={clsx(
                            'rounded px-3 py-1 text-sm',
                            link.active
                                ? 'bg-emerald-500 text-white shadow-accent'
                                : link.url
                                ? 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                                : 'cursor-not-allowed text-gray-300 dark:text-gray-600'
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Index({ transfers }: IndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold leading-tight text-slate-800">
                        Trasferimenti
                    </h1>
                    <LinkButton
                        href={route('transfers.create')}
                        icon={<PlusIcon />}
                    >
                        Nuovo Trasferimento
                    </LinkButton>

                    
                </div>
            }
        >
            <Head title="Trasferimenti" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        {transfers.data.length > 0 ? (
                            <>
                                <div className="p-4">
                                    {transfers.data.map((transfer) => (
                                        <TransferRow key={transfer.id} transfer={transfer} />
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
