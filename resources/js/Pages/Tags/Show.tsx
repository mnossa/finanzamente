import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, Link, router, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { moneyKpiGrid4, moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency, formatDate } from '@/utils/format';
import type { PageProps } from '@/types';
import React from 'react';

interface MonthOption {
    value: string;
    label: string;
}

interface Tag {
    id: number;
    name: string;
    color: string;
}

interface CategoryBreakdown {
    category_id: number | null;
    name: string;
    color: string | null;
    icon: string | null;
    count: number;
    income: number;
    expenses: number;
    net: number;
}

interface RecentTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    category: { id: number; name: string; color: string | null; icon: string | null } | null;
    account: string | null;
    user: { id: number; name: string } | null;
}

interface ShowProps {
    tag: Tag;
    selectedMonth: string;
    selectedMonthLabel: string;
    monthOptions: MonthOption[];
    stats: {
        transaction_count: number;
        income: number;
        expenses: number;
        net: number;
    };
    byCategory: CategoryBreakdown[];
    recentTransactions: RecentTransaction[];
    periodFrom: string;
    periodTo: string;
}

export default function Show({
    tag,
    selectedMonth,
    selectedMonthLabel,
    monthOptions,
    stats,
    byCategory,
    recentTransactions,
    periodFrom,
    periodTo,
}: ShowProps) {
    const { permissions } = usePage<PageProps>().props;
    const canModify = permissions.canModify ?? false;
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);

    const onMonthChange = (month: string) => {
        router.get(route('tags.show', tag.id), { month }, { preserveState: true, preserveScroll: true });
    };

    const handleConfirmDelete = () => {
        router.delete(route('tags.destroy', tag.id));
        setDeleteDialogOpen(false);
    };

    const allTransactionsHref = route('transactions.index', {
        tag_id: tag.id,
        from: periodFrom,
        to: periodTo,
    });

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Tag: ${tag.name}`}
                    backLink={route('tags.index')}
                    actions={
                        canModify ? (
                            <div className="flex items-center gap-2">
                                <LinkButton href={route('tags.edit', tag.id)} icon={<PencilIcon />}>
                                    Modifica
                                </LinkButton>
                                <button
                                    type="button"
                                    onClick={() => setDeleteDialogOpen(true)}
                                    className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/40"
                                >
                                    <TrashIcon size={16} />
                                    Elimina
                                </button>
                            </div>
                        ) : undefined
                    }
                />
            }
        >
            <Head title={`Tag - ${tag.name}`} />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={`Sei sicuro di voler eliminare il tag "${tag.name}"?`}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={() => setDeleteDialogOpen(false)}
            />

            <PageContent maxWidth="4xl">
                {canModify ? (
                    <IndexPageMobileToolbar>
                        <LinkButton href={route('tags.edit', tag.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    </IndexPageMobileToolbar>
                ) : null}

                <CardBox className="p-4 shadow-sm">
                    <div className="flex flex-wrap items-center gap-3">
                        <span
                            className="inline-flex h-10 w-10 items-center justify-center rounded-full text-lg text-white"
                            style={{ backgroundColor: tag.color }}
                            aria-hidden
                        >
                            🏷️
                        </span>
                        <div className="min-w-0 flex-1">
                            <h2 className="text-xl font-bold text-gray-900 dark:text-white">{tag.name}</h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Periodo: <strong>{selectedMonthLabel}</strong>
                            </p>
                        </div>
                        <div>
                            <label htmlFor="tag-month-filter" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Mese
                            </label>
                            <select
                                id="tag-month-filter"
                                value={selectedMonth}
                                onChange={(e) => onMonthChange(e.target.value)}
                                className="mt-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >
                                {monthOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </CardBox>

                <div className={moneyKpiGrid4}>
                    <CardBox className="p-4 shadow-sm">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Transazioni</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                            {stats.transaction_count}
                        </p>
                    </CardBox>
                    <CardBox className="p-4 shadow-sm">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Entrate</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400', moneyTabular)}>
                            {formatCurrency(stats.income)}
                        </p>
                    </CardBox>
                    <CardBox className="p-4 shadow-sm">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Uscite</p>
                        <p className={clsx('mt-1 text-2xl font-bold text-red-600 dark:text-red-400', moneyTabular)}>
                            {formatCurrency(stats.expenses)}
                        </p>
                    </CardBox>
                    <CardBox className="p-4 shadow-sm">
                        <p className="text-sm text-gray-500 dark:text-gray-400">Netto</p>
                        <p
                            className={clsx(
                                'mt-1 text-2xl font-bold',
                                moneyTabular,
                                stats.net >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-red-600 dark:text-red-400',
                            )}
                        >
                            {formatCurrency(stats.net)}
                        </p>
                    </CardBox>
                </div>

                <CardBox className="overflow-hidden shadow-sm">
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h3 className="font-semibold text-gray-900 dark:text-white">Per categoria</h3>
                    </div>
                    {byCategory.length === 0 ? (
                        <p className="p-4 text-sm text-gray-500 dark:text-gray-400">
                            Nessuna transazione con questo tag nel periodo.
                        </p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {byCategory.map((row) => (
                                <li
                                    key={row.category_id ?? 'null'}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-3"
                                >
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span className="text-lg" aria-hidden>
                                            {row.icon || '📁'}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-gray-900 dark:text-white">
                                                {row.name}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {row.count} {row.count === 1 ? 'transazione' : 'transazioni'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className={clsx('text-right text-sm', moneyTabular)}>
                                        <p className="text-emerald-600 dark:text-emerald-400">
                                            +{formatCurrency(row.income)}
                                        </p>
                                        <p className="text-red-600 dark:text-red-400">
                                            −{formatCurrency(row.expenses)}
                                        </p>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(row.net)}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBox>

                <CardBox className="overflow-hidden shadow-sm">
                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                        <h3 className="font-semibold text-gray-900 dark:text-white">Ultime transazioni</h3>
                        <Link
                            href={allTransactionsHref}
                            className="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                            Vedi tutte le transazioni
                        </Link>
                    </div>
                    {recentTransactions.length === 0 ? (
                        <p className="p-4 text-sm text-gray-500 dark:text-gray-400">
                            Nessuna transazione recente nel periodo.
                        </p>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                            {recentTransactions.map((tx) => (
                                <li key={tx.id}>
                                    <Link
                                        href={route('transactions.show', tx.id)}
                                        className="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-gray-900 dark:text-white">
                                                {tx.description || tx.category?.name || 'Transazione'}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {formatDate(tx.date)}
                                                {tx.account ? ` · ${tx.account}` : ''}
                                            </p>
                                        </div>
                                        <p
                                            className={clsx(
                                                'shrink-0 font-semibold',
                                                moneyTabular,
                                                tx.amount >= 0
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-red-600 dark:text-red-400',
                                            )}
                                        >
                                            {formatCurrency(tx.amount)}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
