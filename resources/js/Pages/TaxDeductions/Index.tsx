import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import clsx from 'clsx';
import React from 'react';

interface Category {
    id: number;
    name: string;
    icon: string | null;
}

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
    tax_deduction_rate: number;
    tax_deduction_type: string;
    tax_year: number;
    category: Category | null;
    account: Account;
}

interface GroupedTransactions {
    [key: string]: Transaction[];
}

interface TransactionsSummary {
    total_transactions: number;
    total_amount: number;
    total_deductible: number;
    years: number[];
    grouped_by_type: GroupedTransactions;
}

interface IndexProps {
    transactions: Transaction[];
    summary: TransactionsSummary;
    year: number;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export default function Index({ transactions = [], summary, year }: IndexProps) {
    // Provide safe defaults for summary
    const safeSummary = {
        total_transactions: summary?.total_transactions ?? 0,
        total_amount: summary?.total_amount ?? 0,
        total_deductible: summary?.total_deductible ?? 0,
        years: summary?.years ?? [new Date().getFullYear()],
        grouped_by_type: summary?.grouped_by_type ?? {},
    };

    const handleYearChange = (newYear: number) => {
        router.get(route('tax-deductions.index'), { year: newYear }, { preserveState: true });
    };

    const handleExportPdf = () => {
        window.location.href = route('tax-deductions.export-pdf', { year });
    };

    const handleExportAttachments = () => {
        window.location.href = route('tax-deductions.export-attachments', { year });
    };

    const getTypeLabel = (typeValue: string) => {
        return TAX_DEDUCTION_TYPES.find(t => t.value === typeValue)?.label || typeValue;
    };

    const calculateTypeTotal = (typeTransactions: Transaction[]) => {
        return typeTransactions.reduce((sum, t) => sum + Math.abs(t.amount), 0);
    };

    const calculateTypeDeductible = (typeTransactions: Transaction[]) => {
        return typeTransactions.reduce((sum, t) => 
            sum + (Math.abs(t.amount) * t.tax_deduction_rate / 100), 0
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Detrazioni Fiscali (730)"
                    backLink={route('dashboard')}
                />
            }
        >
            <Head title="Detrazioni Fiscali" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Header con filtri ed export */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        {/* Filtro anno */}
                        <div className="flex items-center gap-3">
                            <label htmlFor="year" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Anno fiscale:
                            </label>
                            <select
                                id="year"
                                value={year}
                                onChange={(e) => handleYearChange(Number(e.target.value))}
                                className="rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {safeSummary.years.map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Bottoni export */}
                        {transactions.length > 0 && (
                            <div className="flex gap-2">
                                <button
                                    onClick={handleExportPdf}
                                    className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-800"
                                >
                                    📄 Esporta PDF
                                </button>
                                <button
                                    onClick={handleExportAttachments}
                                    className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                >
                                    📦 Esporta Allegati (ZIP)
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Statistiche */}
                    {transactions.length > 0 ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-3">
                                {/* Totale transazioni */}
                                <div className="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                Transazioni
                                            </p>
                                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                                {safeSummary.total_transactions}
                                            </p>
                                        </div>
                                        <span className="text-4xl">📋</span>
                                    </div>
                                </div>

                                {/* Totale spese */}
                                <div className="rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                Totale Spese
                                            </p>
                                            <p className="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                                {formatCurrency(safeSummary.total_amount)}
                                            </p>
                                        </div>
                                        <span className="text-4xl">💸</span>
                                    </div>
                                </div>

                                {/* Totale detraibile */}
                                <div className="rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 p-6 shadow-sm">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-medium text-emerald-50">
                                                Importo Detraibile
                                            </p>
                                            <p className="mt-2 text-3xl font-bold text-white">
                                                {formatCurrency(safeSummary.total_deductible)}
                                            </p>
                                        </div>
                                        <span className="text-4xl">💰</span>
                                    </div>
                                </div>
                            </div>

                            {/* Transazioni raggruppate per tipo */}
                            <div className="space-y-4">
                                {Object.entries(safeSummary.grouped_by_type).map(([type, typeTransactions]) => {
                                    const typeLabel = getTypeLabel(type);
                                    const typeTotal = calculateTypeTotal(typeTransactions);
                                    const typeDeductible = calculateTypeDeductible(typeTransactions);

                                    return (
                                        <div
                                            key={type}
                                            className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800"
                                        >
                                            {/* Header tipo */}
                                            <div className="border-b border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                                <div className="flex items-center justify-between">
                                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                                        {typeLabel}
                                                    </h3>
                                                    <div className="text-right">
                                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                                            {typeTransactions.length} {typeTransactions.length === 1 ? 'transazione' : 'transazioni'}
                                                        </p>
                                                        <p className="text-lg font-semibold text-emerald-600 dark:text-emerald-400">
                                                            Detraibile: {formatCurrency(typeDeductible)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Lista transazioni */}
                                            <div className="divide-y divide-gray-200 dark:divide-gray-700">
                                                {typeTransactions.map((transaction) => (
                                                    <Link
                                                        key={transaction.id}
                                                        href={route('transactions.show', transaction.id)}
                                                        className="block p-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <div className="flex items-center gap-3">
                                                                <span className="text-2xl">
                                                                    {transaction.category?.icon || '💸'}
                                                                </span>
                                                                <div>
                                                                    <p className="font-medium text-gray-900 dark:text-white">
                                                                        {transaction.description || transaction.category?.name || 'Transazione'}
                                                                    </p>
                                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                                        {formatDate(transaction.date)} · {transaction.account.name}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-semibold text-gray-900 dark:text-white">
                                                                    {formatCurrency(Math.abs(transaction.amount), transaction.account.currency_code)}
                                                                </p>
                                                                <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                                                    {transaction.tax_deduction_rate}% · {formatCurrency(
                                                                        Math.abs(transaction.amount) * transaction.tax_deduction_rate / 100,
                                                                        transaction.account.currency_code
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </Link>
                                                ))}
                                            </div>

                                            {/* Footer tipo con totale */}
                                            <div className="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                                <div className="flex justify-between text-sm">
                                                    <span className="font-medium text-gray-700 dark:text-gray-300">
                                                        Totale categoria
                                                    </span>
                                                    <span className="font-bold text-gray-900 dark:text-white">
                                                        {formatCurrency(typeTotal)}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            {/* Info finale */}
                            <div className="rounded-xl border-2 border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-700 dark:bg-emerald-900/20">
                                <h4 className="mb-3 flex items-center text-lg font-semibold text-emerald-900 dark:text-emerald-100">
                                    <span className="mr-2">💡</span> Promemoria
                                </h4>
                                <ul className="space-y-2 text-sm text-emerald-800 dark:text-emerald-200">
                                    <li>• Verifica che tutti gli allegati (scontrini, fatture) siano presenti</li>
                                    <li>• Esporta il PDF e gli allegati da consegnare al commercialista o al CAF</li>
                                    <li>• Le percentuali indicate sono standard, potrebbero variare in base alla normativa vigente</li>
                                    <li>• Conserva una copia di backup di tutti i documenti</li>
                                </ul>
                            </div>
                        </>
                    ) : (
                        /* Empty state */
                        <div className="rounded-xl bg-white p-12 text-center shadow-sm dark:bg-gray-800">
                            <span className="text-6xl">📋</span>
                            <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                                Nessuna transazione detraibile per l'anno {year}
                            </h3>
                            <p className="mt-2 text-gray-500 dark:text-gray-400">
                                Inizia a registrare spese detraibili per la dichiarazione dei redditi.
                            </p>
                            <Link
                                href={route('transactions.create')}
                                className="mt-6 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-3 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                            >
                                ➕ Nuova Transazione
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
