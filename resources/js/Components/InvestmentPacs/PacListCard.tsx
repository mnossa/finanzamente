import Dropdown from '@/Components/Dropdown';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { formatCurrency, formatDateShort } from '@/utils/format';
import { Link } from '@inertiajs/react';
import { indexPageInsetX } from '@/Components/IndexPageListToolbars';
import clsx from 'clsx';

export interface PacListCardPac {
    id: number;
    amount: number;
    adjust_for_inflation: boolean;
    inflation_rate_annual: number | null;
    currency_code: string;
    start_date: string;
    end_date: string | null;
    last_executed_at: string | null;
    next_execution_date?: string | null;
    status: string;
    investments_count: number;
    asset: { id: number; name: string; symbol: string; isin: string | null };
    account: { id: number; name: string } | null;
}

interface PacListCardProps {
    pac: PacListCardPac;
    onRunNow: (pacId: number) => void;
    onToggleStatus: (pacId: number) => void;
    onDelete: (id: number, description: string) => void;
}

function StatusBadge({ status }: { status: string }) {
    const isActive = status === 'active';

    return (
        <span
            className={clsx(
                'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium sm:text-xs',
                isActive
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            )}
        >
            {isActive ? 'Attivo' : 'In pausa'}
        </span>
    );
}

export default function PacListCard({ pac, onRunNow, onToggleStatus, onDelete }: PacListCardProps) {
    const isActive = pac.status === 'active';
    const assetLabel = pac.asset.symbol || pac.asset.isin || 'Asset';
    const showHref = route('investment-pacs.show', pac.id);
    const editHref = route('investment-pacs.edit', pac.id);

    return (
        <article className="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800/50">
            <Link
                href={showHref}
                className={clsx('block transition-colors active:bg-gray-50 dark:active:bg-gray-700/40', indexPageInsetX, 'py-3 sm:py-4')}
            >
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                        <h3 className="truncate text-sm font-semibold text-gray-900 sm:text-base dark:text-white">
                            {pac.asset.name}
                        </h3>
                        <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                            {assetLabel}
                        </p>
                    </div>
                    <StatusBadge status={pac.status} />
                </div>

                <div className="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <p className="text-lg font-bold tabular-nums text-gray-900 sm:text-xl dark:text-white">
                        {formatCurrency(pac.amount, pac.currency_code)}
                        <span className="ml-1 text-sm font-medium text-gray-500 dark:text-gray-400">/ mese</span>
                    </p>
                    {pac.adjust_for_inflation && pac.inflation_rate_annual !== null && (
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/25 dark:text-emerald-400">
                            +{pac.inflation_rate_annual.toFixed(1)}% annuo
                        </span>
                    )}
                </div>

                <dl className="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    {pac.account ? (
                        <div className="col-span-2 min-w-0">
                            <dt className="sr-only">Conto</dt>
                            <dd className="truncate">{pac.account.name}</dd>
                        </div>
                    ) : null}
                    <div>
                        <dt className="sr-only">Movimenti</dt>
                        <dd>{pac.investments_count} movimenti</dd>
                    </div>
                    <div className="text-right sm:text-left">
                        <dt className="sr-only">Inizio</dt>
                        <dd>Inizio {formatDateShort(pac.start_date)}</dd>
                    </div>
                    {pac.last_executed_at ? (
                        <div className="col-span-2 min-w-0">
                            <dt className="sr-only">Ultimo versamento</dt>
                            <dd className="truncate">Ultimo {formatDateShort(pac.last_executed_at)}</dd>
                        </div>
                    ) : null}
                    {pac.next_execution_date ? (
                        <div className="col-span-2 min-w-0">
                            <dt className="sr-only">Prossimo versamento</dt>
                            <dd className="truncate">Prossimo {formatDateShort(pac.next_execution_date)}</dd>
                        </div>
                    ) : null}
                    {pac.end_date ? (
                        <div className="col-span-2">
                            <dt className="sr-only">Fine</dt>
                            <dd>Fine {formatDateShort(pac.end_date)}</dd>
                        </div>
                    ) : null}
                </dl>
            </Link>

            <div className={clsx('flex items-center gap-2 border-t border-gray-100 py-2.5 dark:border-gray-700', indexPageInsetX)}>
                <button
                    type="button"
                    onClick={() => onRunNow(pac.id)}
                    disabled={!isActive}
                    className="min-h-10 flex-1 rounded-lg bg-emerald-600 px-3 text-sm font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none sm:px-4"
                >
                    Esegui ora
                </button>
                <button
                    type="button"
                    onClick={() => onToggleStatus(pac.id)}
                    className="min-h-10 shrink-0 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <span className="sm:hidden">{isActive ? 'Pausa' : 'Riprendi'}</span>
                    <span className="hidden sm:inline">{isActive ? 'Metti in pausa' : 'Riattiva'}</span>
                </button>

                <div className="hidden items-center gap-1 sm:flex">
                    <Link
                        href={editHref}
                        className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                        aria-label="Modifica PAC"
                    >
                        <PencilIcon size={16} />
                    </Link>
                    <button
                        type="button"
                        onClick={() => onDelete(pac.id, pac.asset.name)}
                        className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                        aria-label="Elimina PAC"
                    >
                        <TrashIcon size={16} />
                    </button>
                </div>

                <div className="sm:hidden">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 text-lg leading-none text-gray-600 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                aria-label="Altre azioni PAC"
                            >
                                ⋮
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content
                            align="right"
                            placement="top"
                            contentClasses="py-1 bg-white dark:bg-gray-800"
                        >
                            <Dropdown.Link
                                href={editHref}
                                className="dark:text-gray-200 dark:hover:bg-gray-700/60"
                            >
                                Modifica
                            </Dropdown.Link>
                            <button
                                type="button"
                                onClick={() => onDelete(pac.id, pac.asset.name)}
                                className="block w-full px-4 py-2.5 text-start text-sm leading-5 text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                Elimina
                            </button>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </div>
        </article>
    );
}
