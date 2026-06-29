/**
 * Altezze riservate per skeleton widget formula in dashboard.
 * Allineate a FormulaKpiWidget (KPI min-h-[6.5rem]) e ai grafici Recharts
 * (ResponsiveContainer 200px mobile / 256px desktop + riga valore/legenda)
 * per azzerare il layout shift quando il widget deferito viene montato:
 * se lo skeleton riservasse meno spazio del grafico reale, ogni montaggio
 * allungherebbe la pagina facendo "scappare" il fondo durante lo scroll.
 */

/** Altezza esplicita per skeleton e grafici Recharts (ResponsiveContainer richiede h definita). */
export const FORMULA_CHART_RESERVED_H = 'h-[15rem] sm:h-[18.5rem]';

/** @deprecated Usare FORMULA_CHART_RESERVED_H — mantenuto per compatibilità interna. */
export const FORMULA_CHART_RESERVED_MIN_H = FORMULA_CHART_RESERVED_H;

export function formulaWidgetSkeletonBodyClass(displayType: string, variant?: string | null): string {
    if (displayType === 'kpi' && variant === 'balance_summary') {
        return 'min-h-[17.5rem]';
    }

    if (displayType === 'kpi' || displayType === 'progress') {
        return 'min-h-[6.5rem]';
    }

    return FORMULA_CHART_RESERVED_H;
}
