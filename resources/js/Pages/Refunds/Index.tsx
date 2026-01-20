import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { Pagination } from '@/Components/Pagination';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';

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


function RefundRow({ refund }: { refund: Refund }) {
    const originalTx = refund.original_transaction;

    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-4 last:border-0 dark:border-gray-700">
            <div className="flex items-center space-x-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-2xl dark:bg-blue-900/30">
                    💸
                </div>
                <div>
                    <div className="flex items-center space-x-2">
                        <span className="font-medium text-gray-900 dark:text-white">
                            {refund.description || 'Rimborso'}
                        </span>
                        <span
                            className={clsx(
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                refund.status === 'completed'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                    : refund.status === 'pending'
                                    ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                            )}
                        >
                            {refund.status === 'completed' ? '✓ Completato' : refund.status === 'pending' ? '⏳ In attesa' : '✗ Annullato'}
                        </span>
                    </div>
                    {originalTx && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Rimborso di: {originalTx.description || originalTx.category?.name || 'Transazione'}
                            {originalTx.account && ` • ${originalTx.account.name}`}
                            {originalTx.date && ` • ${formatDate(originalTx.date)}`}
                        </p>
                    )}
                    <p className="text-xs text-gray-400 dark:text-gray-500">
                        {refund.created_at}
                        {refund.user && ` • ${refund.user.name}`}
                    </p>
                </div>
            </div>
            <div className="flex items-center space-x-4">
                <div className="text-right">
                    <p className="font-semibold text-green-600 dark:text-green-400">
                        +{formatCurrency(refund.amount, refund.currency_code)}
                    </p>
                    {originalTx && (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            su {formatCurrency(Math.abs(originalTx.amount), refund.currency_code)}
                        </p>
                    )}
                </div>
                <div className="flex space-x-2">
                    <Link
                        href={route('refunds.show', refund.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                        title="Visualizza"
                    >
                        <EyeIcon size={18} />
                    </Link>
                    <Link
                        href={route('refunds.edit', refund.id)}
                        className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                    >
                        <PencilIcon size={18} />
                    </Link>
                    <button
                        onClick={() => {
                            if (confirm('Sei sicuro di voler eliminare questo rimborso?')) {
                                router.delete(route('refunds.destroy', refund.id));
                            }
                        }}
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


export default function Index({ refunds }: IndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold leading-tight text-slate-800">
                        Rimborsi
                    </h1>
                    <LinkButton
                        href={route('refunds.create')}
                        icon={<PlusIcon />}
                    >
                        Nuovo Rimborso
                    </LinkButton>
                </div>
            }
        >
            <Head title="Rimborsi" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Info Box */}
                    <div className="rounded-xl bg-blue-50 p-4 dark:bg-blue-900/20">
                        <div className="flex">
                            <div className="flex-shrink-0">
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

                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        {refunds.data.length > 0 ? (
                            <>
                                <div className="p-4">
                                    {refunds.data.map((refund) => (
                                        <RefundRow key={refund.id} refund={refund} />
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
