import { lazy, Suspense } from 'react';
import clsx from 'clsx';
import FormulaKpiWidget from '@/Components/FormulaWidgets/FormulaKpiWidget';
import FormulaTableWidget from '@/Components/FormulaWidgets/FormulaTableWidget';
import FormulaWidgetParameterControls from '@/Components/FormulaWidgets/FormulaWidgetParameterControls';
import { FORMULA_CHART_RESERVED_H } from '@/utils/formulaWidgetSkeletonClass';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';

const FormulaChartWidget = lazy(() => import('@/Components/FormulaWidgets/FormulaChartWidget'));

interface CustomFormulaWidgetProps {
    payload: FormulaWidgetPayload;
    embedded?: boolean;
    className?: string;
    onParameterChange?: (key: string, value: string) => void;
    parameterControlsDisabled?: boolean;
    refreshing?: boolean;
}

function ChartSkeleton() {
    return (
        <div
            className={clsx('animate-pulse rounded-lg bg-gray-100 dark:bg-gray-800', FORMULA_CHART_RESERVED_H)}
            aria-hidden="true"
        />
    );
}

function RefreshingOverlay() {
    return (
        <div
            className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/55 backdrop-blur-[1px] dark:bg-gray-900/55"
            role="status"
            aria-live="polite"
        >
            <span className="flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm dark:bg-gray-800/90 dark:text-gray-300">
                <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-gray-300 border-t-primary-500" />
                Aggiornamento…
            </span>
        </div>
    );
}

export default function CustomFormulaWidget({
    payload,
    embedded = true,
    className,
    onParameterChange,
    parameterControlsDisabled = false,
    refreshing = false,
}: CustomFormulaWidgetProps) {
    const parameterControls = payload.parameters && payload.parameters.length > 0 && onParameterChange ? (
        <FormulaWidgetParameterControls
            parameters={payload.parameters}
            disabled={parameterControlsDisabled || refreshing}
            onChange={onParameterChange}
            className="mb-3"
        />
    ) : null;

    if (payload.type === 'kpi' || payload.type === 'progress') {
        return (
            <div className={clsx('h-full w-full', className)}>
                {parameterControls}
                <div className="relative">
                    {refreshing && <RefreshingOverlay />}
                    <FormulaKpiWidget payload={payload} embedded={embedded} />
                </div>
            </div>
        );
    }

    if (payload.type === 'table') {
        return (
            <div className={clsx('h-full w-full', className)}>
                {parameterControls}
                <div className="relative min-h-[12rem]">
                    {refreshing && <RefreshingOverlay />}
                    <FormulaTableWidget payload={payload} embedded={embedded} />
                </div>
            </div>
        );
    }

    return (
        <div className={clsx('h-full w-full', className)}>
            {parameterControls}
            <div className={clsx('relative w-full shrink-0', FORMULA_CHART_RESERVED_H)}>
                {refreshing && <RefreshingOverlay />}
                <Suspense fallback={<ChartSkeleton />}>
                    <FormulaChartWidget payload={payload} embedded={embedded} />
                </Suspense>
            </div>
        </div>
    );
}
