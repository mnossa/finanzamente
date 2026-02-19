import React from 'react';
import clsx from 'clsx';
import { router } from '@inertiajs/react';
import { ProgressBar } from '@/Components/ProgressBar';
import { formatCurrency } from '@/utils/format';

interface RevenueProgressCardProps {
    currentRevenue: number;
    threshold: number;
    percentage: number;
    year: number;
    className?: string;
}

function getColorClass(percentage: number): string {
    if (percentage > 100) return 'bg-red-700';
    if (percentage >= 90) return 'bg-red-500';
    if (percentage >= 70) return 'bg-amber-500';
    return 'bg-emerald-500';
}

function getDaysLeftInYear(): number {
    const now = new Date();
    const endOfYear = new Date(now.getFullYear(), 11, 31);
    const diffMs = endOfYear.getTime() - now.getTime();
    return Math.max(0, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));
}

export default function RevenueProgressCard({
    currentRevenue,
    threshold,
    percentage,
    year,
    className,
}: RevenueProgressCardProps) {
    const daysLeft = getDaysLeftInYear();
    const colorClass = getColorClass(percentage);

    function handleHide() {
        router.post(route('profile.revenue-tracking.toggle'), {}, { preserveScroll: true });
    }

    return (
        <div
            className={clsx(
                'overflow-hidden rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800',
                className
            )}
        >
            {/* Header */}
            <div className="mb-4 flex items-center justify-between">
                <h3 className="font-semibold text-gray-900 dark:text-white">
                    📈 Fatturato Annuo {year}
                </h3>
                <button
                    onClick={handleHide}
                    className="rounded-md px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    title="Nascondi il monitoraggio fatturato"
                    type="button"
                >
                    Nascondi
                </button>
            </div>

            {/* Importi */}
            <div className="mb-3 flex items-baseline gap-2">
                <span className="text-2xl font-bold text-gray-900 dark:text-white">
                    {formatCurrency(currentRevenue)}
                </span>
                <span className="text-sm text-gray-500 dark:text-gray-400">
                    / {formatCurrency(threshold)}
                </span>
                <span
                    className={clsx(
                        'ml-auto text-sm font-semibold',
                        percentage > 100
                            ? 'text-red-700 dark:text-red-400'
                            : percentage >= 90
                            ? 'text-red-500'
                            : percentage >= 70
                            ? 'text-amber-500'
                            : 'text-emerald-500'
                    )}
                >
                    {percentage.toFixed(1)}%
                </span>
            </div>

            {/* Barra di avanzamento */}
            <ProgressBar
                percentage={Math.min(percentage, 100)}
                isExceeded={percentage > 100}
                color={colorClass}
                height="0.625rem"
            />

            {/* Footer */}
            <div className="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{daysLeft} giorni rimanenti nell'anno</span>
                {percentage > 100 && (
                    <span className="font-medium text-red-600 dark:text-red-400">
                        🚨 Soglia superata
                    </span>
                )}
                {percentage <= 100 && percentage > 80 && (
                    <span className="font-medium text-amber-600 dark:text-amber-400">
                        ⚠️ Soglia in avvicinamento
                    </span>
                )}
            </div>
        </div>
    );
}
