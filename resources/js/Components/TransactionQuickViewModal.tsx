import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import LinkButton from '@/Components/LinkButton';
import { formatCurrency } from '@/utils/format';
import clsx from 'clsx';

const RECURRING_FREQUENCY_LABELS: Record<string, string> = {
    daily: 'Giornaliera',
    weekly: 'Settimanale',
    monthly: 'Mensile',
    yearly: 'Annuale',
};

export interface TransactionPreviewCategory {
    name: string;
    color: string | null;
    icon: string | null;
}

export interface TransactionPreviewTag {
    name: string;
    color: string | null;
}

export interface TransactionPreviewSide {
    transaction_id?: number | null;
    date: string | null;
    amount: number;
    description: string | null;
    account_name: string | null;
    currency_code: string;
    edit_url: string | null;
    entry_source: 'recurring' | 'manual' | 'unknown';
    recurring_label: string | null;
    recurring_show_url: string | null;
    recurring_edit_url: string | null;
    recurring_frequency: string | null;
    recurring_is_ended?: boolean;
    recurring_end_date?: string | null;
    category: TransactionPreviewCategory | null;
    tags: TransactionPreviewTag[];
    user_name: string | null;
    is_private: boolean;
    is_tax_deductible: boolean;
    is_transfer: boolean;
    is_refund: boolean;
    created_at: string | null;
}

interface Props {
    show: boolean;
    side: TransactionPreviewSide | null;
    columnLabel?: string;
    onClose: () => void;
}

function formatLongDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('it-IT', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

export default function TransactionQuickViewModal({ show, side, columnLabel, onClose }: Props) {
    if (!side) {
        return null;
    }

    const isIncome = side.amount > 0;
    const isRecurring = side.entry_source === 'recurring';
    const isManual = side.entry_source === 'manual';

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Dettaglio movimento</h2>
                        {columnLabel && (
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{columnLabel}</p>
                        )}
                    </div>
                    {isRecurring && (
                        <span className="rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">
                            Da ricorrenza
                        </span>
                    )}
                    {isManual && (
                        <span className="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-900/50 dark:text-amber-100">
                            Inserimento manuale
                        </span>
                    )}
                </div>

                <div className="mt-5 text-center">
                    <div
                        className="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-2xl"
                        style={{
                            backgroundColor: side.category?.color
                                ? `${side.category.color}20`
                                : isIncome
                                  ? '#22c55e20'
                                  : '#ef444420',
                        }}
                    >
                        {side.category?.icon || (isIncome ? '💰' : '💸')}
                    </div>
                    <p className="mt-3 text-base font-semibold text-gray-900 dark:text-white">
                        {side.description || side.category?.name || 'Senza descrizione'}
                    </p>
                    <p
                        className={clsx(
                            'mt-1 text-3xl font-bold tabular-nums',
                            isIncome ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
                        )}
                    >
                        {formatCurrency(side.amount, side.currency_code)}
                    </p>
                    {side.date && (
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{formatLongDate(side.date)}</p>
                    )}
                </div>

                {side.tags.length > 0 && (
                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        {side.tags.map((tag) => (
                            <span
                                key={tag.name}
                                className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                style={{
                                    backgroundColor: tag.color ? `${tag.color}20` : '#e5e7eb',
                                    color: tag.color || '#374151',
                                }}
                            >
                                {tag.name}
                            </span>
                        ))}
                    </div>
                )}

                <dl className="mt-6 space-y-3 text-sm">
                    <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                        <dt className="text-gray-500 dark:text-gray-400">Conto</dt>
                        <dd className="text-right font-medium text-gray-900 dark:text-white">
                            {side.account_name ?? '—'}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                        <dt className="text-gray-500 dark:text-gray-400">Categoria</dt>
                        <dd className="text-right font-medium text-gray-900 dark:text-white">
                            {side.category ? (
                                <>
                                    {side.category.icon} {side.category.name}
                                </>
                            ) : (
                                'Non categorizzata'
                            )}
                        </dd>
                    </div>
                    {side.recurring_label && (
                        <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                            <dt className="text-gray-500 dark:text-gray-400">Ricorrenza</dt>
                            <dd className="text-right font-medium text-violet-700 dark:text-violet-300">
                                {side.recurring_label}
                                {side.recurring_frequency && (
                                    <span className="text-gray-500 dark:text-gray-400">
                                        {' '}
                                        ({RECURRING_FREQUENCY_LABELS[side.recurring_frequency] ?? side.recurring_frequency})
                                    </span>
                                )}
                                {side.recurring_is_ended && side.recurring_end_date && (
                                    <span className="mt-0.5 block text-xs font-normal text-gray-500 dark:text-gray-400">
                                        Terminata il {side.recurring_end_date}
                                    </span>
                                )}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                        <dt className="text-gray-500 dark:text-gray-400">Tipo</dt>
                        <dd className="text-right font-medium text-gray-900 dark:text-white">
                            {side.is_transfer
                                ? 'Trasferimento'
                                : side.is_refund
                                  ? 'Rimborso'
                                  : isIncome
                                    ? 'Entrata'
                                    : 'Uscita'}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                        <dt className="text-gray-500 dark:text-gray-400">Privacy</dt>
                        <dd className="text-right font-medium text-gray-900 dark:text-white">
                            {side.is_private ? 'Privata' : 'Condivisa'}
                        </dd>
                    </div>
                    {side.is_tax_deductible && (
                        <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                            <dt className="text-gray-500 dark:text-gray-400">Fiscale</dt>
                            <dd className="text-right font-medium text-gray-900 dark:text-white">Detraibile</dd>
                        </div>
                    )}
                    {side.user_name && (
                        <div className="flex justify-between gap-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                            <dt className="text-gray-500 dark:text-gray-400">Creata da</dt>
                            <dd className="text-right font-medium text-gray-900 dark:text-white">{side.user_name}</dd>
                        </div>
                    )}
                    {side.created_at && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500 dark:text-gray-400">Registrata il</dt>
                            <dd className="text-right font-medium text-gray-900 dark:text-white">{side.created_at}</dd>
                        </div>
                    )}
                </dl>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" onClick={onClose} className="justify-center">
                        Chiudi
                    </SecondaryButton>
                    {side.edit_url && (
                        <LinkButton href={side.edit_url} className="justify-center">
                            Modifica movimento
                        </LinkButton>
                    )}
                    {(side.recurring_show_url ?? side.recurring_edit_url) && (
                        <LinkButton
                            href={side.recurring_show_url ?? side.recurring_edit_url!}
                            variant="secondary"
                            className="justify-center"
                        >
                            Apri ricorrenza
                        </LinkButton>
                    )}
                </div>
            </div>
        </Modal>
    );
}
