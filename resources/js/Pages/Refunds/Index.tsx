import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import CardBox from '@/Components/CardBox';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
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


function RefundRow({ refund, onDeleteClick }: { refund: Refund; onDeleteClick: (id: number, description: string) => void }) {
    const originalTx = refund.original_transaction;

    return (
        <div className="flex items-center border-b border-gray-100 py-3 last:border-0 -mx-4 px-3 sm:px-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
            <div className="mr-3 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-lg dark:bg-blue-900/30">
                💸
            </div>
            <Link href={route('refunds.show', refund.id)} className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-1">
                    <span className="text-sm font-medium text-gray-900 dark:text-white">
                        {refund.description || 'Rimborso'}
                    </span>
                    <span className={clsx('inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                        refund.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : refund.status === 'pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                    )}>
                        {refund.status === 'completed' ? '✓' : refund.status === 'pending' ? '⏳' : '✗'}{' '}
                        {refund.status === 'completed' ? 'Completato' : refund.status === 'pending' ? 'In attesa' : 'Annullato'}
                    </span>
                </div>
                {originalTx && (
                    <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                        {originalTx.description || originalTx.category?.name || 'Transazione'}
                        {originalTx.account && ` · ${originalTx.account.name}`}
                    </p>
                )}
            </Link>
            <div className="ml-2 flex shrink-0 items-center gap-1 sm:gap-2">
                <div className="text-right">
                    <p className="text-sm font-semibold text-green-600 dark:text-green-400">
                        +{formatCurrency(refund.amount, refund.currency_code)}
                    </p>
                    {originalTx && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            su {formatCurrency(Math.abs(originalTx.amount), refund.currency_code)}
                        </p>
                    )}
                </div>
                <div className="hidden sm:flex items-center gap-1">
                    <Link href={route('refunds.show', refund.id)} className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400" title="Visualizza">
                        <EyeIcon size={16} />
                    </Link>
                    <Link href={route('refunds.edit', refund.id)} className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400" title="Modifica">
                        <PencilIcon size={16} />
                    </Link>
                    <button onClick={() => onDeleteClick(refund.id, refund.description || 'questo rimborso')} className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400" title="Elimina">
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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Rimborsi"
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
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Rimborsi" icon={<span className="text-sm leading-none">💸</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Traccia i rimborsi ricevuti e il loro impatto sulle spese originarie.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Info Box */}
                    <div className="rounded-xl bg-blue-50 p-4 dark:bg-blue-900/20">
                        <div className="flex">
                            <div className="shrink-0">
                                <span className="text-2xl">💡</span>
                            </div>
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    Cosa sono i rimborsi?
                                </h3>
                                <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                    I rimborsi ti permettono di tracciare quando ricevi indietro soldi per una spesa già effettuata.
                                    Ad esempio: resi di prodotti, rimborsi assicurativi, o restituzione di depositi.
                                </p>
                            </div>
                        </div>
                    </div>

                    <CardBox className="overflow-hidden shadow-sm">
                        {refunds.data.length > 0 ? (
                            <>
                                <div className="p-4">
                                    {refunds.data.map((refund) => (
                                        <RefundRow key={refund.id} refund={refund} onDeleteClick={openDeleteDialog} />
                                    ))}
                                </div>
                                <Pagination data={refunds} />
                            </>
                        ) : (
                            <EmptyState
                                icon="💸"
                                title="Nessun rimborso registrato"
                                description="Registra un rimborso quando ricevi indietro soldi per una spesa."
                                createUrl={route('refunds.create')}
                                createLabel="Nuovo Rimborso"
                            />
                        )}
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
