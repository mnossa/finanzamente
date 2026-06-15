import { formulaWidgetBadgeLabel } from '@/utils/formulaWidgetDisplayLabels';
import clsx from 'clsx';

interface FormulaWidgetTypeBadgeProps {
    displayType: string;
    className?: string;
}

export default function FormulaWidgetTypeBadge({ displayType, className }: FormulaWidgetTypeBadgeProps) {
    return (
        <span
            className={clsx(
                'inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                className,
            )}
            title={`Widget a formula · ${formulaWidgetBadgeLabel(displayType)}`}
        >
            {formulaWidgetBadgeLabel(displayType)}
        </span>
    );
}
