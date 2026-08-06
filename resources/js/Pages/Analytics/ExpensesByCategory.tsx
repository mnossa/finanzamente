import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import ExpenseTreemap, { ExpenseCategory } from '@/Components/Charts/ExpenseTreemap';
import { Head, router } from '@inertiajs/react';

interface MonthOption {
    value: string;
    label: string;
}

interface Props {
    expenseCategories: ExpenseCategory[];
    selectedMonth: string;
    selectedMonthLabel: string;
    monthOptions: MonthOption[];
}

export default function ExpensesByCategory({
    expenseCategories,
    selectedMonth,
    selectedMonthLabel,
    monthOptions,
}: Props) {
    const onMonthChange = (month: string) => {
        router.get(route('analytics.expenses-by-category'), { month }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Spese per categoria"
                    subtitle="Dettaglio mensile per categoria"
                    backLink={route('dashboard')}
                />
            }
        >
            <Head title="Spese per categoria" />
            <PageContent maxWidth="4xl">
                <div className="mb-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label htmlFor="month-filter" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Mese
                        </label>
                        <select
                            id="month-filter"
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
                    <p className="text-sm text-gray-500 dark:text-gray-400 pb-2">
                        Periodo: <strong>{selectedMonthLabel}</strong>
                    </p>
                </div>
                <div className="overflow-hidden rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800 sm:p-6">
                    <ExpenseTreemap data={expenseCategories} month={selectedMonth} />
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
