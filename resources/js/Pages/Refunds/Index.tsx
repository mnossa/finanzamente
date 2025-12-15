import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import PlusIcon from '@/Components/Icons/PlusIcon';

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

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
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
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    >
                        👁️
                    </Link>
                    <Link
                        href={route('refunds.edit', refund.id)}
                        className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    >
                        ✏️
                    </Link>
                    <button
                        onClick={() => {
                            if (confirm('Sei sicuro di voler eliminare questo rimborso?')) {
                                router.delete(route('refunds.destroy', refund.id));
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

function Pagination({ data }: { data: PaginatedData<Refund> }) {
    if (data.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            <div className="text-sm text-gray-500 dark:text-gray-400">
                {data.from}-{data.to} di {data.total} rimborsi
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
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="mb-4 text-6xl">💸</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessun rimborso registrato
                                </h3>
                                <p className="mb-6 text-slate-500">
                                    Registra un rimborso quando ricevi indietro soldi per una spesa.
                                </p>
                                <LinkButton
                                    href={route('refunds.create')}
                                    icon={<PlusIcon />}
                                >
                                    Nuovo Rimborso
                                </LinkButton>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
