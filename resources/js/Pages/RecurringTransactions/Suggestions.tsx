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
    will_auto_close: boolean;
    auto_close_end_date: string | null;
    has_gaps: boolean;
    missing_occurrences: number;
    largest_gap_days: number;
    has_internal_gaps: boolean;
    internal_missing_occurrences: number;
    has_trailing_gap: boolean;
    trailing_missing_occurrences: number;
    first_transaction_date: string | null;
    last_transaction_date: string | null;
    amount_change_guidance: {
        pair_with_suggestion_id: number;
        pair_amount: number;
        recommended_mode: 'active' | 'closed';
        variant: 'amount_change_previous' | 'amount_change_next';
        message: string;
    } | null;
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
    daily: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    weekly: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    monthly: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    yearly: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};

const CONFIDENCE_COLORS: Record<string, string> = {
    alto: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
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
    onAccept: (mode?: 'auto' | 'active' | 'closed' | 'closed_fill_gaps' | 'active_fill_gaps') => void;
    onIgnore: () => void;
    processing: boolean;
}) {
    const [expanded, setExpanded] = useState(false);
    const [showGuidanceDetails, setShowGuidanceDetails] = useState(false);
    const [showActionLegend, setShowActionLegend] = useState(false);
    const isExpense = suggestion.amount < 0;
    const hasGuidance = Boolean(
        suggestion.will_auto_close || suggestion.has_gaps || suggestion.amount_change_guidance
    );
    const hasManualActive = suggestion.has_gaps || suggestion.amount_change_guidance?.recommended_mode === 'active';
    const hasManualClosed = suggestion.has_gaps || suggestion.amount_change_guidance?.recommended_mode === 'closed';
    const hasFillGapsMode = suggestion.has_gaps;
    const hasAutoMode = true;

    return (
        <CardBox
            className={clsx(
                'p-0 overflow-hidden',
                suggestion.amount_change_guidance && 'ring-1 ring-sky-200 dark:ring-sky-800/60'
            )}
        >
            <div className="p-4 sm:p-5">
                {/* Header — colonna su mobile per evitare compressione con importo */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
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
                            <p className="font-semibold text-gray-900 dark:text-gray-100 break-words leading-snug">
                                {suggestion.description ?? suggestion.category?.name ?? 'Transazione senza descrizione'}
                            </p>
                            <p className="text-sm text-gray-500 dark:text-gray-400 break-words">
                                {suggestion.account.name}
                            </p>
                        </div>
                    </div>

                    <div className="flex shrink-0 flex-row items-baseline justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-700 sm:flex-col sm:border-t-0 sm:pt-0 sm:text-right">
                        <p className={clsx(
                            'text-lg font-bold tabular-nums sm:text-xl',
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
                    {suggestion.amount_change_guidance && (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                            🔗 Cambio importo ({suggestion.amount_change_guidance.variant === 'amount_change_previous' ? 'fase precedente' : 'fase recente'})
                        </span>
                    )}
                    {suggestion.has_gaps && (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            ⛳ Buchi rilevati
                        </span>
                    )}
                    {suggestion.will_auto_close && (
                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            ⏹ Auto-dismessa
                        </span>
                    )}
                </div>

                {hasGuidance && (
                    <div className="mt-3">
                        <button
                            type="button"
                            onClick={() => setShowGuidanceDetails((prev) => !prev)}
                            className="text-xs font-medium text-indigo-600 dark:text-indigo-300 hover:underline"
                        >
                            {showGuidanceDetails ? 'Nascondi guida rapida' : 'Mostra guida rapida'}
                        </button>

                        {showGuidanceDetails && (
                            <div className="mt-2 space-y-2">
                                {suggestion.amount_change_guidance && (
                                    <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:border-sky-800/60 dark:bg-sky-900/20 dark:text-sky-300">
                                        <p className="font-semibold">Variazione importo collegata</p>
                                        <p className="mt-1">
                                            Questa card e collegata alla ricorrenza con importo {formatCurrency(suggestion.amount_change_guidance.pair_amount, suggestion.currency_code)}.
                                        </p>
                                        <p className="mt-1">
                                            Azione consigliata: crea questa come{' '}
                                            <span className="font-semibold">
                                                {suggestion.amount_change_guidance.recommended_mode === 'closed' ? 'dismessa' : 'attiva'}
                                            </span>.
                                        </p>
                                    </div>
                                )}
                                {suggestion.has_gaps && (
                                    <div className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:border-indigo-800/60 dark:bg-indigo-900/20 dark:text-indigo-300">
                                        Ho trovato buchi nel pattern ({suggestion.missing_occurrences} occorrenze mancanti, gap max {suggestion.largest_gap_days} giorni).
                                        {suggestion.has_trailing_gap && suggestion.has_internal_gaps ? (
                                            <span className="block mt-1">
                                                Ci sono sia buchi interni che un buco finale: in genere conviene <span className="font-semibold">Forza Dismessa</span> (serie probabilmente terminata), poi verifica manualmente eventuali mesi mancanti.
                                            </span>
                                        ) : suggestion.has_trailing_gap ? (
                                            <span className="block mt-1">
                                                Il buco principale e dopo l'ultima transazione ({suggestion.trailing_missing_occurrences} mancanti in coda): in genere conviene <span className="font-semibold">Forza Dismessa</span>, salvo casi di mancata registrazione recente.
                                            </span>
                                        ) : (
                                            <span className="block mt-1">
                                                I buchi risultano interni alla serie ({suggestion.internal_missing_occurrences}): in genere conviene <span className="font-semibold">Forza Attiva</span>, verificando eventuali dimenticanze.
                                            </span>
                                        )}
                                    </div>
                                )}
                                {suggestion.will_auto_close && suggestion.auto_close_end_date && (
                                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/20 dark:text-amber-300">
                                        In modalita auto verra creata come dismessa (fine: {formatDate(suggestion.auto_close_end_date)}), perche l'ultima occorrenza risulta datata.
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* Anteprima transazioni (collassabile) */}
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    className="mt-3 text-xs text-indigo-600 dark:text-indigo-400 hover:underline focus:outline-none"
                >
                    {expanded ? 'Nascondi transazioni' : `Mostra ${suggestion.transaction_count} transazioni`}
                </button>

                {expanded && (
                    <div className="mt-2 overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-700 [-webkit-overflow-scrolling:touch]">
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

            {/* Azioni — stack verticale su mobile, riga su sm+ */}
            <div className="border-t border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/50 sm:px-5">
                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end sm:gap-2">
                    <button
                        type="button"
                        disabled={processing}
                        onClick={onIgnore}
                        className="order-first w-full min-h-[44px] rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 sm:order-0 sm:w-auto sm:min-h-0"
                    >
                        Ignora
                    </button>
                    {suggestion.has_gaps ? (
                        <>
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => onAccept('active')}
                                className="w-full min-h-[44px] rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                            >
                                Forza Attiva
                            </button>
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => onAccept('active_fill_gaps')}
                                className="w-full min-h-[44px] rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                            >
                                Attiva + Inserisci Buchi
                            </button>
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => onAccept('closed')}
                                className="w-full min-h-[44px] rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                            >
                                Forza Dismessa
                            </button>
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => onAccept('closed_fill_gaps')}
                                className="w-full min-h-[44px] rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-orange-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                            >
                                Dismessa + Inserisci Buchi
                            </button>
                        </>
                    ) : (
                        <>
                            {suggestion.amount_change_guidance?.recommended_mode === 'closed' && (
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() => onAccept('closed')}
                                    className="w-full min-h-[44px] rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                                >
                                    Forza Dismessa
                                </button>
                            )}
                            {suggestion.amount_change_guidance?.recommended_mode === 'active' && (
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() => onAccept('active')}
                                    className="w-full min-h-[44px] rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                                >
                                    Forza Attiva
                                </button>
                            )}
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => onAccept('auto')}
                                className="w-full min-h-[44px] rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50 sm:w-auto sm:min-h-0"
                            >
                                Applica Auto
                            </button>
                        </>
                    )}
                </div>
            </div>
            <div className="px-4 sm:px-5 pb-2">
                <button
                    type="button"
                    onClick={() => setShowActionLegend((prev) => !prev)}
                    className="text-[11px] font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    {showActionLegend ? 'Nascondi legenda azioni' : 'Mostra legenda azioni'}
                </button>
                {showActionLegend && (
                    <ul className="mt-1 space-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                        {hasAutoMode && (
                            <li>• Applica Auto: usa le regole automatiche.</li>
                        )}
                        {hasManualActive && (
                            <li>• Forza Attiva: imposta la ricorrenza come aperta.</li>
                        )}
                        {hasManualClosed && (
                            <li>• Forza Dismessa: imposta la ricorrenza come terminata.</li>
                        )}
                        {hasFillGapsMode && (
                            <li>• Dismessa + Inserisci Buchi: chiude e prova a colmare i mesi mancanti.</li>
                        )}
                                        {hasFillGapsMode && (
                                            <li>• Attiva + Inserisci Buchi: lascia attiva e colma i buchi mancanti fino a oggi.</li>
                                        )}
                    </ul>
                )}
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

    const handleAccept = (
        suggestion: Suggestion,
        mode: 'auto' | 'active' | 'closed' | 'closed_fill_gaps' | 'active_fill_gaps' = 'auto'
    ) => {
        setProcessingId(suggestion.id);
        router.post(route('recurrence-detection.accept', suggestion.id), { mode }, {
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
                    actions={<button
                        type="button"
                        disabled={detecting}
                        onClick={handleDetect}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50 lg:w-auto"
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
                    </button>}
                />

                <div className="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <div className="flex flex-wrap gap-2">
                        <span className="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 font-medium text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                            🔗 Cambio importo
                        </span>
                        <span className="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            ⛳ Buchi rilevati
                        </span>
                        <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                            ⏹ Auto-dismessa
                        </span>
                    </div>
                    <p className="mt-2">
                        Usa i pulsanti guidati: <span className="font-semibold">Forza Attiva</span> (continua),
                        <span className="font-semibold"> Forza Dismessa</span> (terminata) oppure
                        <span className="font-semibold"> Applica Auto</span> (decisione automatica).
                        Per coprire buchi storici puoi usare <span className="font-semibold">Attiva + Inserisci Buchi</span> o <span className="font-semibold">Dismessa + Inserisci Buchi</span>.
                    </p>
                </div>

                {suggestions.length === 0 ? (
                    <EmptyState
                        icon="🔍"
                        title="Nessun suggerimento in attesa"
                        description="Clicca su 'Avvia Rilevamento' per analizzare le tue transazioni e trovare pattern ricorrenti."
                    />
                ) : (
                    <div className="grid gap-4 grid-cols-1">
                        {suggestions.map((suggestion) => (
                            <SuggestionCard
                                key={suggestion.id}
                                suggestion={suggestion}
                                processing={processingId === suggestion.id}
                                onAccept={(mode) => handleAccept(suggestion, mode ?? 'auto')}
                                onIgnore={() => handleIgnore(suggestion)}
                            />
                        ))}
                    </div>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
