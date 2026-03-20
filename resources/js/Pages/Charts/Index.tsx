import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import ExpenseTreemap, { ExpenseCategory } from '@/Components/Charts/ExpenseTreemap';
import BudgetProgress, { BudgetItem } from '@/Components/Charts/BudgetProgress';
import clsx from 'clsx';

interface ChartsProps {
    expenseCategories: ExpenseCategory[];
    activeBudgets: BudgetItem[];
    period: string;
}

const PERIOD_OPTIONS: { value: string; label: string }[] = [
    { value: '7d',  label: '7 giorni' },
    { value: '30d', label: '30 giorni' },
    { value: '1y',  label: '1 anno' },
    { value: 'max', label: 'Tutto' },
];

export default function ChartsIndex({
    expenseCategories,
    activeBudgets,
    period,
}: ChartsProps) {
    const [loading, setLoading] = useState(false);

    const handlePeriodChange = (newPeriod: string) => {
        setLoading(true);
        router.get(
            route('charts.index'),
            { period: newPeriod },
            {
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="Grafici & Analisi" />}>
            <Head title="Grafici & Analisi" />

            <div className="space-y-6">
                {/* Filtro periodo */}
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Analizza la tua salute finanziaria nel tempo
                    </p>
                    <div
                        className="flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800"
                        role="group"
                        aria-label="Filtro periodo"
                    >
                        {PERIOD_OPTIONS.map((opt) => (
                            <button
                                key={opt.value}
                                onClick={() => handlePeriodChange(opt.value)}
                                disabled={loading}
                                aria-pressed={period === opt.value}
                                className={clsx(
                                    'rounded-lg px-3 py-1.5 text-sm font-medium transition-all',
                                    period === opt.value
                                        ? 'bg-white text-gray-900 shadow dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                                    loading && 'opacity-50 cursor-not-allowed',
                                )}
                            >
                                {opt.label}
                            </button>
                        ))}
                    </div>
                </div>

                {loading && (
                    <div role="status" aria-live="polite" className="flex items-center justify-center py-4">
                        <div className="h-6 w-6 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent" aria-hidden="true" />
                        <span className="ml-2 text-sm text-gray-500">Caricamento...</span>
                    </div>
                )}

                {/* Riga budget + categorie */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <BudgetProgress budgets={activeBudgets} className="min-w-0" />
                    <ExpenseTreemap data={expenseCategories} className="min-w-0" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
