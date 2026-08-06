import React from 'react';
import { ProgressBar as TremorProgressBar, Card } from '@tremor/react';
import clsx from 'clsx';
import { Link } from '@inertiajs/react';
import { formatEuro } from './chartConfig';

export interface BudgetItem {
    id: number;
    category_name: string;
    category_icon: string | null;
    category_color: string | null;
    amount: number;
    spent: number;
    percentage: number;
    is_exceeded: boolean;
    currency_symbol: string;
}

interface BudgetProgressProps {
    budgets: BudgetItem[];
    className?: string;
}

function BudgetRow({ budget }: { budget: BudgetItem }) {
    const pct = Math.min(budget.percentage, 100);
    const color = budget.is_exceeded ? 'rose' : budget.percentage >= 80 ? 'amber' : 'emerald';

    return (
        <Link
            href={route('budgets.show', budget.id)}
            className="block rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            aria-label={`Budget ${budget.category_name}`}
        >
            <div className="mb-2 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span aria-hidden="true">{budget.category_icon ?? '📁'}</span>
                    <span className="text-sm font-medium text-gray-900 dark:text-white">
                        {budget.category_name}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    {budget.is_exceeded && (
                        <span className="text-xs text-red-500" aria-label="Budget superato">⚠️ Superato</span>
                    )}
                    <span
                        className={clsx(
                            'text-sm font-bold',
                            budget.is_exceeded
                                ? 'text-red-500'
                                : budget.percentage >= 80
                                ? 'text-amber-500'
                                : 'text-emerald-600 dark:text-emerald-400',
                        )}
                    >
                        {budget.percentage.toFixed(0)}%
                    </span>
                </div>
            </div>
            <TremorProgressBar value={pct} color={color} className="mt-1" />
            <div className="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Speso: {formatEuro(budget.spent)}</span>
                <span>Budget: {formatEuro(budget.amount)}</span>
            </div>
        </Link>
    );
}

export default function BudgetProgress({ budgets, className }: BudgetProgressProps) {
    return (
        <Card className={className}>
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                        🎯 Monitoraggio Budget
                    </h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Target vs spesa reale
                    </p>
                </div>
                <Link
                    href={route('budgets.index')}
                    className="text-sm text-emerald-500 hover:text-emerald-600"
                >
                    Gestisci →
                </Link>
            </div>

            {budgets.length > 0 ? (
                <div className="mt-4 space-y-1 divide-y divide-gray-100 dark:divide-gray-700">
                    {budgets.map((b) => (
                        <BudgetRow key={b.id} budget={b} />
                    ))}
                </div>
            ) : (
                <div className="mt-6 flex flex-col items-center justify-center py-8 text-center">
                    <p className="text-gray-500 dark:text-gray-400">
                        Nessun budget attivo questo mese
                    </p>
                    <Link
                        href={route('budgets.create')}
                        className="mt-3 text-sm text-emerald-500 hover:text-emerald-600"
                    >
                        Crea il tuo primo budget →
                    </Link>
                </div>
            )}
        </Card>
    );
}
