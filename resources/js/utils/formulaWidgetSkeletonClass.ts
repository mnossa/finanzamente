/**
 * Altezze riservate per skeleton widget formula in dashboard.
 * Allineate a CustomFormulaWidget (KPI min-h-[5.5rem], chart 200/256px) per limitare CLS.
 */
export function formulaWidgetSkeletonBodyClass(displayType: string, variant?: string | null): string {
    if (displayType === 'kpi' && variant === 'balance_summary') {
        return 'min-h-[17.5rem]';
    }

    if (displayType === 'kpi' || displayType === 'progress') {
        return 'min-h-[5.5rem]';
    }

    return 'min-h-[12.5rem] sm:min-h-[16rem]';
}
