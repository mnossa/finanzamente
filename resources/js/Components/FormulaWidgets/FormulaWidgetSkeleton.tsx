import DashboardWidgetShell, { dashboardWidgetListBodyClass } from '@/Components/Dashboard/DashboardWidgetShell';
import FormulaWidgetTypeBadge from '@/Components/FormulaWidgets/FormulaWidgetTypeBadge';
import { formulaWidgetSkeletonBodyClass } from '@/utils/formulaWidgetSkeletonClass';
import clsx from 'clsx';

interface FormulaWidgetSkeletonProps {
    title: string;
    displayType?: string;
    variant?: string | null;
    showBadge?: boolean;
}

export default function FormulaWidgetSkeleton({
    title,
    displayType = 'kpi',
    variant,
    showBadge = true,
}: FormulaWidgetSkeletonProps) {
    const bodyMinClass = formulaWidgetSkeletonBodyClass(displayType, variant);

    return (
        <DashboardWidgetShell
            title={title}
            titleBadge={showBadge && displayType ? <FormulaWidgetTypeBadge displayType={displayType} /> : undefined}
            bodyClassName={dashboardWidgetListBodyClass}
        >
            <div
                className={clsx('flex flex-col justify-center animate-pulse', bodyMinClass)}
                aria-hidden="true"
            >
                <div className="h-8 w-2/5 max-w-[8rem] rounded-lg bg-gray-200 dark:bg-gray-700" />
                <div className="mt-3 flex-1 rounded-lg bg-gray-100 dark:bg-gray-800" />
            </div>
            <span className="sr-only">Caricamento widget…</span>
        </DashboardWidgetShell>
    );
}
