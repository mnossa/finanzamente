import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import { Head, Link } from '@inertiajs/react';

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Transaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    category: {
        id: number;
        name: string;
        icon: string | null;
    } | null;
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
    transactions: Transaction[];
}

interface ShowProps {
    transfer: Transfer;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

export default function Show({ transfer }: ShowProps) {
    const isSameCurrency = transfer.source_currency === transfer.dest_currency;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={
                        <>
                            <Link
                                href={route('transfers.index')}
                                className="mr-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                ← Indietro
                            </Link>
                            Dettagli Trasferimento
                        </>
                    }
                />
            }
        >
            <Head title="Dettagli Trasferimento" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Card Principale */}
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div className="mb-6 flex items-center justify-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl dark:bg-emerald-900/30">
                                🔄
                            </div>
                        </div>

                        {/* Flusso del trasferimento */}
                        <div className="mb-6 flex items-center justify-center space-x-4">
                            <div className="text-center">
                                <p className="text-sm text-gray-500 dark:text-gray-400">Da</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {transfer.source_account?.name || 'Conto eliminato'}
                                </p>
                                <p className="text-lg font-bold text-red-500">
                                    -{formatCurrency(transfer.source_amount, transfer.source_currency)}
                                </p>
                            </div>
                            <div className="text-3xl text-gray-400">→</div>
                            <div className="text-center">
                                <p className="text-sm text-gray-500 dark:text-gray-400">A</p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {transfer.destination_account?.name || 'Conto eliminato'}
                                </p>
                                <p className="text-lg font-bold text-green-500">
                                    +{formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                                </p>
                            </div>
                        </div>

                        {/* Dettagli */}
                        <div className="space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Data</span>
                                <span className="text-gray-900 dark:text-white">{transfer.created_at}</span>
                            </div>

                            {!isSameCurrency && transfer.exchange_rate && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Tasso di cambio</span>
                                    <span className="text-gray-900 dark:text-white">
                                        1 {transfer.source_currency} = {transfer.exchange_rate.toFixed(6)} {transfer.dest_currency}
                                    </span>
                                </div>
                            )}

                            {transfer.fee && transfer.fee > 0 && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Commissione</span>
                                    <span className="text-orange-500">
                                        {formatCurrency(transfer.fee, transfer.source_currency)}
                                    </span>
                                </div>
                            )}

                            {transfer.user && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Effettuato da</span>
                                    <span className="text-gray-900 dark:text-white">{transfer.user.name}</span>
                                </div>
                            )}

                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">Stato</span>
                                <span className="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    {transfer.status === 'completed' ? 'Completato' : transfer.status}
                                </span>
                            </div>

                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500 dark:text-gray-400">ID</span>
                                <span className="font-mono text-xs text-gray-400">{transfer.uuid}</span>
                            </div>
                        </div>
                    </div>

                    {/* Transazioni collegate */}
                    {transfer.transactions.length > 0 && (
                        <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                            <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                Transazioni collegate
                            </h3>
                            <div className="space-y-3">
                                {transfer.transactions.map((tx) => (
                                    <div
                                        key={tx.id}
                                        className="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-700/50"
                                    >
                                        <div className="flex items-center space-x-3">
                                            <span className="text-xl">
                                                {tx.category?.icon || (tx.amount > 0 ? '↙️' : '↗️')}
                                            </span>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                    {tx.category?.name || 'Trasferimento'}
                                                </p>
                                                <p className="text-xs text-gray-500">{tx.date}</p>
                                            </div>
                                        </div>
                                        <p
                                            className={
                                                tx.amount > 0
                                                    ? 'font-medium text-green-500'
                                                    : 'font-medium text-red-500'
                                            }
                                        >
                                            {tx.amount > 0 ? '+' : ''}
                                            {formatCurrency(Math.abs(tx.amount))}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
