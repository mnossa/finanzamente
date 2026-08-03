import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PlanningHubNav from '@/Components/PlanningHubNav';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import IndexListCard from '@/Components/Index/IndexListCard';
import IndexListRow from '@/Components/Index/IndexListRow';
import IndexEmptyList from '@/Components/Index/IndexEmptyList';
import CardBox from '@/Components/CardBox';
import {
    contentPanelHeaderClass,
    indexPageInsetX,
    IndexPageMobileToolbar,
} from '@/Components/IndexPageListToolbars';
import { Head, Link, router } from '@inertiajs/react';
import PageHeader from '@/Components/PageHeader';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import clsx from 'clsx';
import { moneyKpiGrid2, moneyTabular } from '@/utils/moneyGridClasses';
import { formatCurrency, formatDate } from '@/utils/format';

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

function TaxDeductionRow({ transaction }: { transaction: Transaction }) {
    const currencyCode = transaction.account.currency_code;
    const expenseAmount = Math.abs(transaction.amount);
    const deductibleAmount = expenseAmount * transaction.tax_deduction_rate / 100;

    return (
        <IndexListRow
            href={route('transactions.show', transaction.id)}
            avatar={transaction.category?.icon || '💸'}
            title={transaction.description || transaction.category?.name || 'Transazione'}
            subtitle={`${formatDate(transaction.date)} · ${transaction.account.name}`}
            amount={formatCurrency(expenseAmount, currencyCode)}
            amountDetail={
                <p className={clsx('text-xs text-emerald-600 dark:text-emerald-400', moneyTabular)}>
                    {transaction.tax_deduction_rate}% · {formatCurrency(deductibleAmount, currencyCode)}
                </p>
            }
        />
    );
}

export default function Index({ transactions = [], summary, year }: IndexProps) {
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
        return TAX_DEDUCTION_TYPES.find((t) => t.value === typeValue)?.label || typeValue;
    };

    const calculateTypeTotal = (typeTransactions: Transaction[]) => {
        return typeTransactions.reduce((sum, t) => sum + Math.abs(t.amount), 0);
    };

    const calculateTypeDeductible = (typeTransactions: Transaction[]) => {
        return typeTransactions.reduce(
            (sum, t) => sum + (Math.abs(t.amount) * t.tax_deduction_rate / 100),
            0,
        );
    };

    const hasTransactions = transactions.length > 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Spese detraibili"
                    backLink={route('budgets.index')}
                />
            }
        >
            <Head title="Spese detraibili" />

            <PageContent>
                <PlanningHubNav active="tax-deductions" />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <label htmlFor="year" className="shrink-0 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Anno fiscale
                        </label>
                        <select
                            id="year"
                            value={year}
                            onChange={(e) => handleYearChange(Number(e.target.value))}
                            className="min-h-10 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            {safeSummary.years.map((y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {hasTransactions && (
                    <IndexPageMobileToolbar className="mt-0">
                        <button
                            type="button"
                            onClick={handleExportPdf}
                            className="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800"
                        >
                            📄 Esporta PDF
                        </button>
                        <button
                            type="button"
                            onClick={handleExportAttachments}
                            className="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-sm font-medium text-white hover:bg-emerald-700"
                        >
                            📦 Esporta allegati
                        </button>
                    </IndexPageMobileToolbar>
                )}

                {hasTransactions && (
                    <div className="hidden gap-2 sm:flex sm:justify-end">
                        <button
                            type="button"
                            onClick={handleExportPdf}
                            className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800"
                        >
                            📄 Esporta PDF
                        </button>
                        <button
                            type="button"
                            onClick={handleExportAttachments}
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                        >
                            📦 Esporta Allegati (ZIP)
                        </button>
                    </div>
                )}

                {hasTransactions ? (
                    <>
                        <div className="flex flex-col gap-2 sm:gap-3">
                            <div className={clsx(moneyKpiGrid2, 'gap-2 sm:gap-3')}>
                                <IndexKpiCell
                                    label="Transazioni"
                                    value={safeSummary.total_transactions}
                                    className="!p-3 sm:!p-4"
                                />
                                <IndexKpiCell
                                    label="Totale spese"
                                    value={formatCurrency(safeSummary.total_amount)}
                                    className="!p-3 sm:!p-4"
                                />
                            </div>
                            <IndexKpiCell
                                label="Importo detraibile"
                                value={formatCurrency(safeSummary.total_deductible)}
                                detail="Somma delle quote detraibili"
                                valueClassName="text-emerald-600 dark:text-emerald-400"
                                className="!p-3 sm:!p-4"
                            />
                        </div>

                        <div className="space-y-3">
                            {Object.entries(safeSummary.grouped_by_type).map(([type, typeTransactions]) => {
                                const typeLabel = getTypeLabel(type);
                                const typeTotal = calculateTypeTotal(typeTransactions);
                                const typeDeductible = calculateTypeDeductible(typeTransactions);

                                return (
                                    <IndexListCard
                                        key={type}
                                        header={
                                            <div className={contentPanelHeaderClass}>
                                                <div className="flex items-start justify-between gap-2">
                                                    <h3 className="text-sm font-semibold text-gray-900 sm:text-base dark:text-white">
                                                        {typeLabel}
                                                    </h3>
                                                    <div className="shrink-0 text-right">
                                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                                            {typeTransactions.length}{' '}
                                                            {typeTransactions.length === 1 ? 'transazione' : 'transazioni'}
                                                        </p>
                                                        <p
                                                            className={clsx(
                                                                'text-sm font-semibold text-emerald-600 dark:text-emerald-400',
                                                                moneyTabular,
                                                            )}
                                                        >
                                                            {formatCurrency(typeDeductible)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        }
                                        footer={
                                            <div
                                                className={clsx(
                                                    indexPageInsetX,
                                                    'flex justify-between border-t border-gray-100 py-2.5 text-xs sm:text-sm dark:border-gray-700',
                                                )}
                                            >
                                                <span className="font-medium text-gray-700 dark:text-gray-300">
                                                    Totale categoria
                                                </span>
                                                <span
                                                    className={clsx(
                                                        'font-bold text-gray-900 dark:text-white',
                                                        moneyTabular,
                                                    )}
                                                >
                                                    {formatCurrency(typeTotal)}
                                                </span>
                                            </div>
                                        }
                                    >
                                        {typeTransactions.map((transaction) => (
                                            <TaxDeductionRow
                                                key={transaction.id}
                                                transaction={transaction}
                                            />
                                        ))}
                                    </IndexListCard>
                                );
                            })}
                        </div>

                        <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 sm:p-4 dark:border-emerald-700 dark:bg-emerald-900/20">
                            <h4 className="mb-2 flex items-center text-sm font-semibold text-emerald-900 sm:text-base dark:text-emerald-100">
                                <span className="mr-1.5">💡</span> Promemoria
                            </h4>
                            <ul className="space-y-1.5 text-xs text-emerald-800 sm:text-sm dark:text-emerald-200">
                                <li>• Verifica che tutti gli allegati (scontrini, fatture) siano presenti</li>
                                <li>• Esporta il PDF e gli allegati da consegnare al commercialista o al CAF</li>
                                <li>• Le percentuali indicate sono standard, potrebbero variare in base alla normativa vigente</li>
                                <li>• Conserva una copia di backup di tutti i documenti</li>
                            </ul>
                        </div>
                    </>
                ) : (
                    <IndexEmptyList
                        icon="📋"
                        title={`Nessuna transazione detraibile per l'anno ${year}`}
                        description="Inizia a registrare spese detraibili per la dichiarazione dei redditi."
                        createUrl={route('transactions.create')}
                        createLabel="Nuova transazione"
                    />
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
