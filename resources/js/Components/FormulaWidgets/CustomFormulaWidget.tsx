import { lazy, Suspense } from 'react';
import clsx from 'clsx';
import FormulaKpiWidget from '@/Components/FormulaWidgets/FormulaKpiWidget';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';

const FormulaChartWidget = lazy(() => import('@/Components/FormulaWidgets/FormulaChartWidget'));

interface CustomFormulaWidgetProps {
    payload: FormulaWidgetPayload;
    embedded?: boolean;
    className?: string;
}

function ChartSkeleton() {
    return (
        <div
            className="min-h-[12.5rem] animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800 sm:min-h-[16rem]"
            aria-hidden="true"
        />
    );
}

export default function CustomFormulaWidget({ payload, embedded = true, className }: CustomFormulaWidgetProps) {
    if (payload.type === 'kpi' || payload.type === 'progress') {
        return <FormulaKpiWidget payload={payload} embedded={embedded} className={className} />;
    }

    return (
        <div className={clsx('h-full w-full', className)}>
            <Suspense fallback={<ChartSkeleton />}>
                <FormulaChartWidget payload={payload} embedded={embedded} />
            </Suspense>
        </div>
    );
}
