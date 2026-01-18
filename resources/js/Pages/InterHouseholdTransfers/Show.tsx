import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

interface Household {
    id: number;
    name: string;
}

interface Account {
    id: number;
    name: string;
    type: string;
}

interface User {
    id: number;
    first_name: string;
    last_name: string;
}

interface Transaction {
    id: number;
    amount: number;
    description: string;
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
    notes: string | null;
    transfer_date: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled' | 'completed';
    source_transaction: Transaction | null;
    destination_transaction: Transaction | null;
    approved_at: string | null;
    approved_by: User | null;
    rejected_at: string | null;
    rejected_by: User | null;
    rejection_reason: string | null;
    created_at: string;
}

interface ShowProps {
    transfer: InterHouseholdTransfer;
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

function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
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

export default function Show({ transfer }: ShowProps) {
    const handleDelete = () => {
        router.delete(route('inter-household-transfers.destroy', transfer.id), {
            onBefore: () => confirm('Sei sicuro di voler eliminare questo trasferimento?'),
        });
    };

    // const getUserName = (user: User | null) => {
    //     if (!user) return 'Utente sconosciuto';
    //     return `${user.first_name} ${user.last_name}`;
    // };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={route('inter-household-transfers.index')}
                            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            ← Indietro
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Dettagli Trasferimento
                        </h2>
                    </div>
                    <span
                        className={clsx(
                            'inline-flex rounded-full px-3 py-1 text-sm font-medium',
                            STATUS_COLORS[transfer.status]
                        )}
                    >
                        {STATUS_LABELS[transfer.status]}
                    </span>
                </div>
            }
        >
            <Head title="Dettagli Trasferimento" />

            <div className="py-6">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    <div className="space-y-6">
                        {/* Informazioni principali */}
                        <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                            <h3 className="mb-4 text-lg font-medium text-gray-900 dark:text-white">
                                Informazioni Trasferimento
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Da
                                    </label>
                                    <p className="mt-1 text-base text-gray-900 dark:text-white">
                                        {transfer.source_household.name}
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        {transfer.source_account.name}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        A
                                    </label>
                                    <p className="mt-1 text-base text-gray-900 dark:text-white">
                                        {transfer.destination_household.name}
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        {transfer.destination_account.name}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Importo Inviato
                                    </label>
                                    <p className="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">
                                        {formatCurrency(transfer.source_amount, transfer.source_currency)}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Importo Ricevuto
                                    </label>
                                    <p className="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">
                                        {formatCurrency(transfer.dest_amount, transfer.dest_currency)}
                                    </p>
                                </div>
                                {transfer.exchange_rate && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Tasso di Cambio
                                        </label>
                                        <p className="mt-1 text-base text-gray-900 dark:text-white">
                                            1 {transfer.source_currency} = {transfer.exchange_rate}{' '}
                                            {transfer.dest_currency}
                                        </p>
                                    </div>
                                )}
                                {transfer.fee && transfer.fee > 0 && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Commissione
                                        </label>
                                        <p className="mt-1 text-base text-gray-900 dark:text-white">
                                            {formatCurrency(transfer.fee, transfer.source_currency)}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Data Trasferimento
                                    </label>
                                    <p className="mt-1 text-base text-gray-900 dark:text-white">
                                        {formatDate(transfer.transfer_date)}
                                    </p>
                                </div>
                                {/* <div>
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Creato da
                                    </label>
                                    <p className="mt-1 text-base text-gray-900 dark:text-white">
                                        {getUserName(transfer.source_user)}
                                    </p>
                                </div> */}
                            </div>

                            {transfer.description && (
                                <div className="mt-4">
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Descrizione
                                    </label>
                                    <p className="mt-1 text-base text-gray-900 dark:text-white">
                                        {transfer.description}
                                    </p>
                                </div>
                            )}

                            {transfer.notes && (
                                <div className="mt-4">
                                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        Note
                                    </label>
                                    <p className="mt-1 text-base text-gray-900 dark:text-white">
                                        {transfer.notes}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Stato e azioni */}
                        {transfer.status === 'approved' && (
                            <div className="overflow-hidden rounded-xl bg-green-50 p-6 shadow-sm dark:bg-green-900/20">
                                <h3 className="mb-2 text-lg font-medium text-green-900 dark:text-green-300">
                                    Trasferimento Completato
                                </h3>
                                <p className="text-sm text-green-700 dark:text-green-400">
                                    Completato il {formatDateTime(transfer.approved_at!)}
                                </p>
                                {transfer.source_transaction && transfer.destination_transaction && (
                                    <div className="mt-4 space-y-2">
                                        <Link
                                            href={route('transactions.show', transfer.source_transaction.id)}
                                            className="block text-sm text-green-700 underline hover:text-green-900 dark:text-green-400 dark:hover:text-green-200"
                                        >
                                            Visualizza transazione di uscita →
                                        </Link>
                                        <Link
                                            href={route('transactions.show', transfer.destination_transaction.id)}
                                            className="block text-sm text-green-700 underline hover:text-green-900 dark:text-green-400 dark:hover:text-green-200"
                                        >
                                            Visualizza transazione di entrata →
                                        </Link>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Pulsanti azioni */}
                        <div className="flex flex-wrap gap-3">
                            <Link
                                href={route('inter-household-transfers.index')}
                                className="rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                            >
                                Torna alla Lista
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
