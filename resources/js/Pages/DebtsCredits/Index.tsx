import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PlanningHubNav from '@/Components/PlanningHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import IndexCardGrid from '@/Components/Index/IndexCardGrid';
import IndexEntityCard, {
    IndexEntityCardFooterButton,
    IndexEntityCardFooterLink,
} from '@/Components/Index/IndexEntityCard';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';

interface Currency {
    code: string;
    symbol: string;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    initial_amount: number;
    paid_amount: number;
    remaining_amount: number;
    currency: Currency;
    type: string;
    type_label: string;
    due_date: string | null;
    status: string;
    status_label: string;
    description: string | null;
    created_by: string;
    created_at: string;
}

interface Summary {
    total_debts: number;
    total_credits: number;
    overdue_count: number;
}

interface Types {
    [key: string]: string;
}

interface Statuses {
    [key: string]: string;
}

interface IndexProps {
    debtsCredits: DebtCredit[];
    summary: Summary;
    types: Types;
    statuses: Statuses;
}

import { formatCurrency, formatDate } from '@/utils/format';
import { StatusBadge } from '@/Components/StatusBadge';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import { ProgressBar } from '@/Components/ProgressBar';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

function DebtCreditCard({ item, canModify }: { item: DebtCredit; canModify: boolean }) {
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

    const handleClose = () => {
        if (confirm('Vuoi segnare questo elemento come chiuso/saldato?')) {
            router.post(route('debts-credits.close', item.id));
        }
    };

    const handleReopen = () => {
        router.post(route('debts-credits.reopen', item.id));
    };

    const handleDelete = () => {
        router.delete(route('debts-credits.destroy', item.id));
        setShowDeleteDialog(false);
    };

    const isDebt = item.type === 'debt';
    const initialAmount = item.initial_amount || item.amount;
    const paidPercent = initialAmount > 0 ? Math.min(100, (item.paid_amount / initialAmount) * 100) : 0;

    return (
        <>
        <IndexEntityCard
            href={route('debts-credits.show', item.id)}
            dimmed={item.status === 'closed'}
            icon={<span className="text-lg">{isDebt ? '📤' : '📥'}</span>}
            iconClassName={clsx(
                'flex h-10 w-10 items-center justify-center rounded-full',
                isDebt ? 'bg-red-100 dark:bg-red-900/30' : 'bg-emerald-100 dark:bg-emerald-900/30',
            )}
            title={item.counterparty}
            subtitle={
                <span className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <span>
                        {item.type_label}
                        {item.due_date ? ` · Scad. ${formatDate(item.due_date)}` : ''}
                    </span>
                    <StatusBadge
                        status={item.status}
                        statusLabel={item.status_label}
                        size="sm"
                    />
                </span>
            }
            amount={
                <>
                    {isDebt ? '−' : '+'}
                    {formatCurrency(item.remaining_amount, item.currency.code)}
                </>
            }
            amountClassName={isDebt ? 'text-red-500' : 'text-emerald-500'}
            extra={
                <div className="space-y-1.5">
                    {item.status !== 'closed' && initialAmount > 0 && (
                        <div className="flex items-center gap-2">
                            <ProgressBar
                                percentage={paidPercent}
                                color={paidPercent >= 100 ? 'bg-emerald-500' : isDebt ? 'bg-red-400' : 'bg-emerald-400'}
                                height="0.375rem"
                                className="flex-1"
                            />
                            <span className="text-[10px] font-medium text-gray-500 dark:text-gray-400">{paidPercent.toFixed(0)}%</span>
                        </div>
                    )}
                    {item.description && (
                        <p className="line-clamp-1 text-xs text-gray-500 dark:text-gray-400">
                            {item.description}
                        </p>
                    )}
                </div>
            }
            footer={
                canModify ? (
                    <>
                        {item.status !== 'closed' ? (
                            <button
                                type="button"
                                onClick={handleClose}
                                className="mr-auto rounded px-2 py-1 text-xs text-emerald-600 hover:bg-emerald-50 sm:text-sm dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                            >
                                ✓ Chiudi
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={handleReopen}
                                className="mr-auto rounded px-2 py-1 text-xs text-blue-600 hover:bg-blue-50 sm:text-sm dark:text-blue-400 dark:hover:bg-blue-900/20"
                            >
                                ↩ Riapri
                            </button>
                        )}
                        <IndexEntityCardFooterLink href={route('debts-credits.edit', item.id)} title="Modifica">
                            <PencilIcon size={16} />
                        </IndexEntityCardFooterLink>
                        <IndexEntityCardFooterButton
                            onClick={() => setShowDeleteDialog(true)}
                            title="Elimina"
                            className="hover:text-red-600 dark:hover:text-red-400"
                        >
                            <TrashIcon size={16} />
                        </IndexEntityCardFooterButton>
                    </>
                ) : undefined
            }
            footerClassName="flex items-center justify-end gap-0.5"
        />
        <ConfirmDeleteDialog
            open={showDeleteDialog}
            onConfirm={handleDelete}
            onCancel={() => setShowDeleteDialog(false)}
        />
        </>
    );
}

export default function Index({ debtsCredits, summary, types, statuses }: IndexProps) {
    const { permissions } = usePage<PageProps>().props;
    const canModify = permissions.canModify ?? false;

    const openItems = debtsCredits.filter((item) => item.status !== 'closed');
    const closedItems = debtsCredits.filter((item) => item.status === 'closed');

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Debiti e Crediti"
                    backLink={route('budgets.index')}
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('debts-credits.create')} icon={<PlusIcon />}>
                                Nuovo
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Debiti e Crediti" />

            <PageContent maxWidth="7xl">
                <PlanningHubNav active="debts" />
                    {debtsCredits.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="💸"
                                title="Nessun debito o credito trovato"
                                description="Tieni traccia dei soldi che devi o che ti devono."
                                createUrl={route('debts-credits.create')}
                                createLabel="Aggiungi il primo"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Riepilogo */}
                            <IndexKpiStrip columns={3}>
                                <IndexKpiCell
                                    label="Debiti aperti"
                                    value={formatCurrency(summary.total_debts)}
                                    detail="Soldi che devi"
                                    valueClassName="text-red-600 dark:text-red-400"
                                />
                                <IndexKpiCell
                                    label="Crediti aperti"
                                    value={formatCurrency(summary.total_credits)}
                                    detail="Soldi che ti devono"
                                    valueClassName="text-emerald-600 dark:text-emerald-400"
                                />
                                <IndexKpiCell
                                    label="Bilancio netto"
                                    value={formatCurrency(summary.total_credits - summary.total_debts)}
                                    detail={
                                        summary.overdue_count > 0
                                            ? `⚠️ ${summary.overdue_count} scaduti`
                                            : 'Crediti − debiti'
                                    }
                                    valueClassName={clsx(
                                        summary.total_credits - summary.total_debts >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-amber-600 dark:text-amber-400',
                                    )}
                                />
                            </IndexKpiStrip>

                            {/* Elementi Aperti */}
                            {openItems.length > 0 && (
                                <div>
                                    <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                        📋 Aperti ({openItems.length})
                                    </h3>
                                    <IndexCardGrid className="gap-2 lg:grid-cols-2 xl:grid-cols-3 sm:gap-3">
                                        {openItems.map((item) => (
                                            <DebtCreditCard key={item.id} item={item} canModify={canModify} />
                                        ))}
                                    </IndexCardGrid>
                                </div>
                            )}

                            {/* Elementi Chiusi */}
                            {closedItems.length > 0 && (
                                <div>
                                    <h3 className="mb-4 font-medium text-gray-500 dark:text-gray-400">
                                        ✓ Chiusi ({closedItems.length})
                                    </h3>
                                    <IndexCardGrid className="gap-2 lg:grid-cols-2 xl:grid-cols-3 sm:gap-3">
                                        {closedItems.map((item) => (
                                            <DebtCreditCard key={item.id} item={item} canModify={canModify} />
                                        ))}
                                    </IndexCardGrid>
                                </div>
                            )}
                        </>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
