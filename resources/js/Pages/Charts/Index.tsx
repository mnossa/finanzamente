import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import CashFlowChart, { CashFlowDataPoint } from '@/Components/Charts/CashFlowChart';
import ExpenseTreemap, { ExpenseCategory } from '@/Components/Charts/ExpenseTreemap';
import NetWorthChart, { NetWorthDataPoint } from '@/Components/Charts/NetWorthChart';
import BudgetProgress, { BudgetItem } from '@/Components/Charts/BudgetProgress';
import PortfolioChart, { PortfolioItem } from '@/Components/Charts/PortfolioChart';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';

interface ChartsProps {
    cashFlowData: CashFlowDataPoint[];
    expenseCategories: ExpenseCategory[];
    netWorthData: NetWorthDataPoint[];
    activeBudgets: BudgetItem[];
    portfolioData: PortfolioItem[];
    period: string;
}

const PERIOD_OPTIONS: { value: string; label: string }[] = [
    { value: '7d',  label: '7 giorni' },
    { value: '30d', label: '30 giorni' },
    { value: '1y',  label: '1 anno' },
    { value: 'max', label: 'Tutto' },
];

export default function ChartsIndex({
    cashFlowData,
    expenseCategories,
    netWorthData,
    activeBudgets,
    portfolioData,
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

                {/* Griglia grafici principali */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <CashFlowChart data={cashFlowData} />
                    <NetWorthChart data={netWorthData} />
                </div>

                {/* Riga budget + categorie */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <BudgetProgress budgets={activeBudgets} />
                    <ExpenseTreemap data={expenseCategories} />
                </div>

                {/* Portafoglio */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <PortfolioChart data={portfolioData} />

                    {/* Riepilogo statistico */}
                    <CardBox>
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                            📋 Riepilogo Periodo
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Statistiche chiave del periodo selezionato
                        </p>
                        <div className="mt-4 grid grid-cols-2 gap-4">
                            <SummaryCard
                                label="Totale Entrate"
                                value={cashFlowData.reduce((s, d) => s + d.Entrate, 0)}
                                color="text-emerald-600 dark:text-emerald-400"
                            />
                            <SummaryCard
                                label="Totale Uscite"
                                value={cashFlowData.reduce((s, d) => s + d.Uscite, 0)}
                                color="text-orange-500"
                            />
                            <SummaryCard
                                label="Risparmio Netto"
                                value={cashFlowData.reduce((s, d) => s + d.Risparmio, 0)}
                                color="text-blue-500"
                            />
                            <SummaryCard
                                label="Budget Attivi"
                                value={activeBudgets.length}
                                isCount
                                color="text-violet-500"
                            />
                        </div>
                    </CardBox>
                    </div>
                </div>
            {/* </div> */}
        </AuthenticatedLayout>
    );
}

function SummaryCard({
    label,
    value,
    color,
    isCount = false,
}: {
    label: string;
    value: number;
    color: string;
    isCount?: boolean;
}) {
    const formatted = isCount
        ? value.toString()
        : new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(value);

    return (
        <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
            <p className="text-xs text-gray-500 dark:text-gray-400">{label}</p>
            <p className={clsx('mt-1 text-xl font-bold', color)}>{formatted}</p>
        </div>
    );
}
