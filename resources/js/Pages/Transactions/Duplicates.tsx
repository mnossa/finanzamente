import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import DuplicateTransactionConfirmModal, {
    type DuplicateConfirmAction,
} from '@/Components/DuplicateTransactionConfirmModal';
import TransactionQuickViewModal, {
    type TransactionPreviewSide,
} from '@/Components/TransactionQuickViewModal';
import {
    buildCompactDeleteButtonLabel,
    buildManualDeleteButtonLabel,
    formatTransactionSideSummary,
} from '@/utils/duplicateTransactionLabels';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { useState } from 'react';

const PAIR_RECURRING_VS_MANUAL = 'recurring_vs_manual';

type EntrySource = 'recurring' | 'manual' | 'unknown';

type TransactionSide = TransactionPreviewSide;

interface Item {
    id: number;
    distance_days: number;
    cluster_size: number;
    cluster_spread_days: number;
    pair_type: string;
    recurring_side: 'primary' | 'candidate' | null;
    recurring_template_label: string | null;
    primary: TransactionSide;
    candidate: TransactionSide;
    additional_transactions: TransactionSide[];
}

interface Props {
    items: Item[];
    pendingCount: number;
    recurringDuplicateCount: number;
}

function distanceLabel(days: number): string {
    if (days === 0) {
        return 'stesso giorno o date consecutive';
    }
    if (days === 1) {
        return '1 giorno di distanza';
    }

    return `${days} giorni di distanza`;
}

function columnLabelForSource(source: EntrySource, fallback: string): string {
    if (source === 'recurring') {
        return 'Da ricorrenza';
    }
    if (source === 'manual') {
        return 'Inserimento manuale';
    }

    return fallback;
}

function TransactionColumn({
    label,
    side,
    onPreview,
}: {
    label: string;
    side: TransactionSide;
    onPreview: (side: TransactionSide, columnLabel: string) => void;
}) {
    const isExpense = side.amount < 0;
    const isRecurring = side.entry_source === 'recurring';
    const isManual = side.entry_source === 'manual';

    return (
        <div
            className={clsx(
                'min-w-0 flex-1 rounded-lg border p-3',
                isRecurring
                    ? 'border-emerald-300 bg-emerald-50/70 dark:border-emerald-800 dark:bg-emerald-950/35'
                    : isManual
                      ? 'border-amber-200 bg-amber-50/50 dark:border-amber-900/60 dark:bg-amber-950/25'
                      : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900/40',
            )}
        >
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p>
            {isRecurring && (
                <span className="mt-1 inline-block rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">
                    Generato dalla ricorrenza
                </span>
            )}
            {isManual && (
                <span className="mt-1 inline-block rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-900/50 dark:text-amber-100">
                    Inserito a mano
                </span>
            )}
            <p
                className={clsx(
                    'mt-1 text-lg font-semibold tabular-nums',
                    isExpense ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {formatCurrency(side.amount, side.currency_code)}
            </p>
            <p className="mt-0.5 text-sm text-gray-700 dark:text-gray-200">{side.date ? formatDate(side.date) : '—'}</p>
            {side.account_name && (
                <p className="text-xs text-gray-500 dark:text-gray-400">Conto: {side.account_name}</p>
            )}
            <p className="mt-1 truncate text-sm text-gray-600 dark:text-gray-300" title={side.description ?? undefined}>
                {side.description ?? 'Senza descrizione'}
            </p>
            {side.recurring_show_url && side.recurring_label && (
                <div className="mt-2 space-y-0.5">
                    <Link
                        href={side.recurring_show_url}
                        className="inline-block text-xs font-medium text-emerald-700 underline-offset-2 hover:underline dark:text-emerald-400"
                    >
                        Da ricorrenza: {side.recurring_label}
                    </Link>
                    {side.recurring_is_ended && side.recurring_end_date && (
                        <p className="text-[10px] font-medium uppercase tracking-wide text-violet-700 dark:text-violet-300">
                            Ricorrenza terminata · fine {formatDate(side.recurring_end_date)}
                        </p>
                    )}
                </div>
            )}
            <button
                type="button"
                onClick={() => onPreview(side, label)}
                className="mt-2 inline-block text-xs font-medium text-emerald-700 underline-offset-2 hover:underline dark:text-emerald-400"
            >
                Dettaglio movimento
            </button>
        </div>
    );
}

const actionButtonBase =
    'inline-flex min-h-[44px] shrink-0 items-center justify-center whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition disabled:opacity-50 sm:min-h-0';

function DuplicateCandidateCard({ item }: { item: Item }) {
    const [processing, setProcessing] = useState(false);
    const [confirmAction, setConfirmAction] = useState<DuplicateConfirmAction | null>(null);
    const [preview, setPreview] = useState<{ side: TransactionSide; label: string } | null>(null);

    const isRecurringPair = item.pair_type === PAIR_RECURRING_VS_MANUAL;
    const recurringSide = item.recurring_side === 'primary' ? item.primary : item.candidate;
    const manualSide = item.recurring_side === 'primary' ? item.candidate : item.primary;
    const manualRemoveKey: 'primary' | 'candidate' =
        item.recurring_side === 'primary' ? 'candidate' : 'primary';

    const finish = () => {
        setProcessing(false);
        setConfirmAction(null);
    };

    const runDismiss = () => {
        setProcessing(true);
        router.post(route('transactions.duplicates.dismiss', item.id), {}, {
            preserveScroll: true,
            onFinish: finish,
        });
    };

    const runKeepRecurring = () => {
        setProcessing(true);
        router.post(route('transactions.duplicates.keep-recurring', item.id), {}, {
            preserveScroll: true,
            onFinish: finish,
        });
    };

    const runRemove = (side: 'primary' | 'candidate') => {
        setProcessing(true);
        router.post(
            route('transactions.duplicates.remove', item.id),
            { transaction_to_remove: side },
            { preserveScroll: true, onFinish: finish },
        );
    };

    const handleConfirm = () => {
        if (!confirmAction) {
            return;
        }
        if (confirmAction.kind === 'dismiss') {
            runDismiss();
            return;
        }
        if (confirmAction.kind === 'keepRecurring') {
            runKeepRecurring();
            return;
        }
        if (confirmAction.kind === 'remove') {
            runRemove(confirmAction.removeSide);
        }
    };

    const openKeepRecurringConfirm = () => {
        setConfirmAction({
            kind: 'keepRecurring',
            recurring: recurringSide,
            manual: manualSide,
            recurringDescription: item.recurring_template_label ?? recurringSide.recurring_label,
        });
    };

    const openRemoveManualConfirm = () => {
        setConfirmAction({
            kind: 'remove',
            removeSide: manualRemoveKey,
            toRemove: manualSide,
            toKeep: recurringSide,
            removeRoleLabel: 'Inserimento manuale',
            keepRoleLabel: 'Da ricorrenza',
        });
    };

    return (
        <>
            <article
                className={clsx(
                    'rounded-xl border p-4',
                    isRecurringPair
                        ? 'border-emerald-300 bg-emerald-50/40 dark:border-emerald-800/60 dark:bg-emerald-950/25'
                        : 'border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20',
                )}
            >
                <header className="mb-3 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                            {item.primary.description ?? 'Senza descrizione'}
                        </h3>
                        {isRecurringPair && (
                            <span className="rounded-md bg-emerald-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                Ricorrenza + manuale
                            </span>
                        )}
                    </div>
                    <p
                        className={clsx(
                            'text-xs',
                            isRecurringPair
                                ? 'text-emerald-900 dark:text-emerald-100'
                                : 'text-amber-800 dark:text-amber-200/90',
                        )}
                    >
                        Stesso importo e descrizione simile ·{' '}
                        {item.cluster_size > 2
                            ? `${item.cluster_size} movimenti entro ${distanceLabel(item.cluster_spread_days)}`
                            : distanceLabel(item.distance_days)}
                    </p>
                    {isRecurringPair && (
                        <p className="text-xs text-gray-700 dark:text-gray-300">
                            Un movimento è stato generato dalla ricorrenza
                            {item.recurring_template_label ? (
                                <>
                                    {' '}
                                    <span className="font-medium">«{item.recurring_template_label}»</span>
                                </>
                            ) : null}
                            ; l&apos;altro sembra un inserimento manuale dello stesso pagamento.
                        </p>
                    )}
                </header>

                <div className="flex flex-col gap-3 sm:flex-row">
                    {isRecurringPair ? (
                        <>
                            <TransactionColumn
                                label={columnLabelForSource(recurringSide.entry_source, 'Da ricorrenza')}
                                side={recurringSide}
                                onPreview={(side, label) => setPreview({ side, label })}
                            />
                            <TransactionColumn
                                label={columnLabelForSource(manualSide.entry_source, 'Inserimento manuale')}
                                side={manualSide}
                                onPreview={(side, label) => setPreview({ side, label })}
                            />
                        </>
                    ) : (
                        <>
                            <TransactionColumn
                                label={columnLabelForSource(item.primary.entry_source, 'Movimento A')}
                                side={item.primary}
                                onPreview={(side, label) => setPreview({ side, label })}
                            />
                            <TransactionColumn
                                label={columnLabelForSource(item.candidate.entry_source, 'Movimento B')}
                                side={item.candidate}
                                onPreview={(side, label) => setPreview({ side, label })}
                            />
                        </>
                    )}
                </div>

                {item.additional_transactions.length > 0 && (
                    <div className="mt-3 rounded-lg border border-amber-200/80 bg-amber-50/40 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                        <p className="text-xs font-semibold text-amber-900 dark:text-amber-100">
                            Altri movimenti nello stesso gruppo ({item.additional_transactions.length})
                        </p>
                        <ul className="mt-2 space-y-2">
                            {item.additional_transactions.map((extra) => (
                                <li
                                    key={extra.transaction_id ?? extra.date}
                                    className="flex flex-wrap items-center justify-between gap-2 text-sm"
                                >
                                    <span className="text-gray-700 dark:text-gray-200">
                                        <span className="font-medium tabular-nums">
                                            {formatCurrency(extra.amount, extra.currency_code)}
                                        </span>
                                        {extra.date ? (
                                            <span className="text-gray-500 dark:text-gray-400">
                                                {' '}
                                                · {formatDate(extra.date)}
                                            </span>
                                        ) : null}
                                        {extra.account_name ? (
                                            <span className="text-gray-500 dark:text-gray-400">
                                                {' '}
                                                · {extra.account_name}
                                            </span>
                                        ) : null}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setPreview({ side: extra, label: 'Movimento aggiuntivo' })}
                                        className="text-xs font-medium text-emerald-700 underline-offset-2 hover:underline dark:text-emerald-400"
                                    >
                                        Dettaglio
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div
                    className={clsx(
                        'mt-4 space-y-2 border-t pt-4',
                        isRecurringPair
                            ? 'border-emerald-200/80 dark:border-emerald-900/40'
                            : 'border-amber-200/80 dark:border-amber-900/40',
                    )}
                >
                    <p className="text-xs text-gray-600 dark:text-gray-400">
                        {isRecurringPair
                            ? 'Usa «Mantieni ricorrenza» per eliminare solo l’inserimento manuale e lasciare il movimento generato automaticamente.'
                            : item.cluster_size > 2
                              ? 'Se sono pagamenti distinti (es. rate o acquisti separati), scegli «Non duplicati» una sola volta per tutto il gruppo. Altrimenti elimina i movimenti in eccesso.'
                              : 'Se hai inserito lo stesso pagamento due volte, elimina il movimento in eccesso. Se sono spese distinte, scegli «Non duplicati».'}
                    </p>
                    <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-0.5 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                        {isRecurringPair && (
                            <button
                                type="button"
                                disabled={processing}
                                onClick={openKeepRecurringConfirm}
                                className={clsx(
                                    actionButtonBase,
                                    'bg-emerald-600 text-white hover:bg-emerald-700',
                                )}
                            >
                                Mantieni ricorrenza
                            </button>
                        )}
                        <button
                            type="button"
                            disabled={processing}
                            onClick={() =>
                                setConfirmAction({
                                    kind: 'dismiss',
                                    description: item.primary.description,
                                })
                            }
                            className={clsx(
                                actionButtonBase,
                                'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
                            )}
                        >
                            Non duplicati
                        </button>
                        {isRecurringPair ? (
                            <button
                                type="button"
                                disabled={processing}
                                onClick={openRemoveManualConfirm}
                                aria-label={formatTransactionSideSummary(manualSide, 'Inserimento manuale')}
                                className={clsx(
                                    actionButtonBase,
                                    'border border-red-300 bg-white text-red-700 hover:bg-red-50 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60',
                                )}
                            >
                                {buildManualDeleteButtonLabel()}
                            </button>
                        ) : (
                            <>
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() =>
                                        setConfirmAction({
                                            kind: 'remove',
                                            removeSide: 'primary',
                                            toRemove: item.primary,
                                            toKeep: item.candidate,
                                            removeRoleLabel: 'Movimento A',
                                            keepRoleLabel: 'Movimento B',
                                        })
                                    }
                                    aria-label={formatTransactionSideSummary(item.primary, 'Movimento A')}
                                    className={clsx(
                                        actionButtonBase,
                                        'border border-red-300 bg-white text-red-700 hover:bg-red-50 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-950/60',
                                    )}
                                >
                                    {buildCompactDeleteButtonLabel('A')}
                                </button>
                                <button
                                    type="button"
                                    disabled={processing}
                                    onClick={() =>
                                        setConfirmAction({
                                            kind: 'remove',
                                            removeSide: 'candidate',
                                            toRemove: item.candidate,
                                            toKeep: item.primary,
                                            removeRoleLabel: 'Movimento B',
                                            keepRoleLabel: 'Movimento A',
                                        })
                                    }
                                    aria-label={formatTransactionSideSummary(item.candidate, 'Movimento B')}
                                    className={clsx(
                                        actionButtonBase,
                                        'bg-red-600 text-white hover:bg-red-700',
                                    )}
                                >
                                    {buildCompactDeleteButtonLabel('B')}
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </article>

            <DuplicateTransactionConfirmModal
                show={confirmAction !== null}
                action={confirmAction}
                processing={processing}
                onClose={() => !processing && setConfirmAction(null)}
                onConfirm={handleConfirm}
            />

            <TransactionQuickViewModal
                show={preview !== null}
                side={preview?.side ?? null}
                columnLabel={preview?.label}
                onClose={() => setPreview(null)}
            />
        </>
    );
}

export default function Duplicates({ items, pendingCount, recurringDuplicateCount }: Props) {
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [bulkConfirm, setBulkConfirm] = useState(false);

    const resolveAllRecurring = () => {
        setBulkProcessing(true);
        router.post(route('transactions.duplicates.resolve-all-recurring'), {}, {
            preserveScroll: true,
            onFinish: () => {
                setBulkProcessing(false);
                setBulkConfirm(false);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Possibili duplicati"
                    subtitle={
                        pendingCount > 0
                            ? `${pendingCount} segnalazion${pendingCount === 1 ? 'e' : 'i'} da revisionare`
                            : 'Nessuna segnalazione in sospeso'
                    }
                    backLink={route('transactions.index')}
                />
            }
        >
            <Head title="Possibili duplicati" />
            <PageContent maxWidth="3xl">
                <CardBox className="mb-4 space-y-3 p-4">
                    <p className="text-sm text-gray-700 dark:text-gray-300">
                        Il sistema raggruppa movimenti con <strong>stessa descrizione</strong>, <strong>stesso importo</strong>{' '}
                        e date vicine in un’unica segnalazione (non una card per ogni coppia). Le righe{' '}
                        <strong>«Ricorrenza + manuale»</strong> indicano un movimento automatico e uno inserito a mano. I
                        movimenti collegati a <strong>ricorrenze terminate</strong> sono evidenziati; le occorrenze storiche
                        in mesi diversi non vengono segnalate come duplicati.
                    </p>
                    {recurringDuplicateCount > 0 && (
                        <div className="flex flex-col gap-2 border-t border-gray-200 pt-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-emerald-800 dark:text-emerald-200">
                                {recurringDuplicateCount} segnalazion
                                {recurringDuplicateCount === 1 ? 'e' : 'i'} con ricorrenza + inserimento manuale
                            </p>
                            <button
                                type="button"
                                disabled={bulkProcessing}
                                onClick={() => setBulkConfirm(true)}
                                className={clsx(
                                    actionButtonBase,
                                    'bg-emerald-600 text-white hover:bg-emerald-700',
                                )}
                            >
                                Risolvi tutte le ricorrenze
                            </button>
                        </div>
                    )}
                </CardBox>

                {items.length === 0 ? (
                    <CardBox className="p-6 text-center">
                        <p className="text-sm font-medium text-gray-900 dark:text-white">Nessun duplicato in revisione</p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Quando il controllo automatico troverà nuove coppie sospette, le vedrai qui.
                        </p>
                        <Link
                            href={route('transactions.index')}
                            className="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                        >
                            Torna alle transazioni
                        </Link>
                    </CardBox>
                ) : (
                    <div className="space-y-4">
                        {items.map((item) => (
                            <DuplicateCandidateCard key={item.id} item={item} />
                        ))}
                    </div>
                )}
            </PageContent>

            <DuplicateTransactionConfirmModal
                show={bulkConfirm}
                action={
                    bulkConfirm
                        ? { kind: 'resolveAllRecurring', count: recurringDuplicateCount }
                        : null
                }
                processing={bulkProcessing}
                onClose={() => !bulkProcessing && setBulkConfirm(false)}
                onConfirm={resolveAllRecurring}
            />
        </AuthenticatedLayout>
    );
}
