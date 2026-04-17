import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import CardBox from '@/Components/CardBox';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import React, { useState } from 'react';

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

interface TransactionPreview {
    id: number;
    date: string;
    description: string | null;
    amount: number;
}

interface Suggestion {
    id: number;
    amount: number;
    currency_code: string;
    description: string | null;
    detected_frequency: 'daily' | 'weekly' | 'monthly' | 'yearly';
    confidence: number;
    confidence_label: 'alto' | 'medio' | 'basso';
    transaction_count: number;
    transactions: TransactionPreview[];
    account: Account;
    category: Category | null;
}

interface SuggestionsProps {
    suggestions: Suggestion[];
}

const FREQUENCY_LABELS: Record<string, string> = {
    daily: 'Giornaliera',
    weekly: 'Settimanale',
    monthly: 'Mensile',
    yearly: 'Annuale',
};

const FREQUENCY_COLORS: Record<string, string> = {
    daily:   'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    weekly:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    monthly: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    yearly:  'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};

const CONFIDENCE_COLORS: Record<string, string> = {
    alto:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    medio: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    basso: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(amount);
}

function formatDate(dateString: string): string {
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(dateString));
}

function SuggestionCard({ suggestion, onAccept, onIgnore, processing }: {
    suggestion: Suggestion;
    onAccept: () => void;
    onIgnore: () => void;
    processing: boolean;
}) {
    const [expanded, setExpanded] = useState(false);
    const isExpense = suggestion.amount < 0;

    return (
        <CardBox className="p-0 overflow-hidden">
            <div className="p-4 sm:p-5">
                {/* Header */}
                <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3 min-w-0">
                        {/* Icona categoria */}
                        {suggestion.category ? (
                            <span
                                className="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-base"
                                style={{ backgroundColor: suggestion.category.color ?? '#e5e7eb' }}
                                aria-hidden="true"
                            >
                                {suggestion.category.icon ?? '💳'}
                            </span>
                        ) : (
                            <span className="flex-shrink-0 w-9 h-9 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-base" aria-hidden="true">
                                💳
                            </span>
                        )}

                        <div className="min-w-0">
                            <p className="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {suggestion.description ?? suggestion.category?.name ?? 'Transazione senza descrizione'}
                            </p>
                            <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                                {suggestion.account.name}
                            </p>
                        </div>
                    </div>

                    <div className="flex-shrink-0 text-right">
                        <p className={clsx(
                            'text-lg font-bold tabular-nums',
                            isExpense ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'
                        )}>
                            {formatCurrency(suggestion.amount, suggestion.currency_code)}
                        </p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            {suggestion.transaction_count} occorrenze
                        </p>
                    </div>
                </div>

                {/* Badge */}
                <div className="mt-3 flex flex-wrap gap-2">
                    <span className={clsx('inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', FREQUENCY_COLORS[suggestion.detected_frequency])}>
                        {FREQUENCY_LABELS[suggestion.detected_frequency]}
                    </span>
                    <span className={clsx('inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', CONFIDENCE_COLORS[suggestion.confidence_label])}>
                        Confidenza {suggestion.confidence_label}
                    </span>
                    {suggestion.category && (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {suggestion.category.name}
                        </span>
                    )}
                </div>

                {/* Anteprima transazioni (collassabile) */}
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    className="mt-3 text-xs text-indigo-600 dark:text-indigo-400 hover:underline focus:outline-none"
                >
                    {expanded ? 'Nascondi transazioni' : `Mostra ${suggestion.transaction_count} transazioni`}
                </button>

                {expanded && (
                    <div className="mt-2 rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Data</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Descrizione</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Importo</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                {suggestion.transactions.map((t) => (
                                    <tr key={t.id}>
                                        <td className="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {formatDate(t.date)}
                                        </td>
                                        <td className="px-3 py-2 text-gray-500 dark:text-gray-400 truncate max-w-[180px]">
                                            {t.description ?? '—'}
                                        </td>
                                        <td className={clsx(
                                            'px-3 py-2 text-right tabular-nums whitespace-nowrap font-medium',
                                            t.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'
                                        )}>
                                            {formatCurrency(t.amount, suggestion.currency_code)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Azioni */}
            <div className="px-4 sm:px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700 flex gap-3 justify-end">
                <button
                    type="button"
                    disabled={processing}
                    onClick={onIgnore}
                    className="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 transition-colors"
                >
                    Ignora
                </button>
                <button
                    type="button"
                    disabled={processing}
                    onClick={onAccept}
                    className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50 transition-colors"
                >
                    Crea Ricorrenza
                </button>
            </div>
        </CardBox>
    );
}

export default function Suggestions({ suggestions }: SuggestionsProps) {
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [detecting, setDetecting] = useState(false);

    const handleDetect = () => {
        setDetecting(true);
        router.post(route('recurrence-detection.detect'), {}, {
            onFinish: () => setDetecting(false),
        });
    };

    const handleAccept = (suggestion: Suggestion) => {
        setProcessingId(suggestion.id);
        router.post(route('recurrence-detection.accept', suggestion.id), {}, {
            onFinish: () => setProcessingId(null),
        });
    };

    const handleIgnore = (suggestion: Suggestion) => {
        setProcessingId(suggestion.id);
        router.post(route('recurrence-detection.ignore', suggestion.id), {}, {
            onFinish: () => setProcessingId(null),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Ricorrenze Rilevate" />

            <PageContent>
                <PageHeader
                    title="Ricorrenze Rilevate"
                    subtitle="Transazioni con pattern regolare che potrebbero essere ricorrenti."
                >
                    <button
                        type="button"
                        disabled={detecting}
                        onClick={handleDetect}
                        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50 transition-colors"
                    >
                        {detecting ? (
                            <>
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                Rilevamento in corso…
                            </>
                        ) : (
                            <>
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z" />
                                </svg>
                                Avvia Rilevamento
                            </>
                        )}
                    </button>
                </PageHeader>

                {suggestions.length === 0 ? (
                    <EmptyState
                        title="Nessun suggerimento in attesa"
                        description="Clicca su 'Avvia Rilevamento' per analizzare le tue transazioni e trovare pattern ricorrenti."
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {suggestions.map((suggestion) => (
                            <SuggestionCard
                                key={suggestion.id}
                                suggestion={suggestion}
                                processing={processingId === suggestion.id}
                                onAccept={() => handleAccept(suggestion)}
                                onIgnore={() => handleIgnore(suggestion)}
                            />
                        ))}
                    </div>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
