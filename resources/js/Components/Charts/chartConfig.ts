/**
 * chartConfig.ts
 * Palette colori e stili condivisi per tutti i grafici (Tremor e Recharts).
 */

export const chartColors = {
    primary:   '#3b82f6', // blue-500
    secondary: '#10b981', // emerald-500
    income:    '#10b981', // emerald-500 – entrate
    expenses:  '#f97316', // orange-500 – uscite (arancione meno aggressivo)
    net:       '#3b82f6', // blue-500   – risparmio netto
    grid:      '#e2e8f0', // slate-200
    text:      '#64748b', // slate-500
    success:   '#10b981', // emerald-500
    danger:    '#ef4444', // red-500
    warning:   '#f59e0b', // amber-500
};

/** Colori per le categorie di spesa (usati come fallback o nei treemap) */
export const categoryPalette = [
    '#3b82f6', '#10b981', '#f97316', '#8b5cf6',
    '#ec4899', '#f59e0b', '#06b6d4', '#84cc16',
    '#6366f1', '#14b8a6',
];

/** Stile Tremor per il BarChart / LineChart / AreaChart */
export const tremorColors = {
    income:   'emerald' as const,
    expenses: 'orange'  as const,
    net:      'blue'    as const,
};

/**
 * CustomTooltip riutilizzabile per Recharts.
 * Replicato come parametri in modo da poter essere usato nei singoli grafici.
 */
export const tooltipStyle = {
    backgroundColor: '#ffffff',
    border:          '1px solid #e2e8f0',
    borderRadius:    '8px',
    boxShadow:       '0 4px 6px -1px rgb(0 0 0 / 0.1)',
    padding:         '8px 12px',
    fontSize:        '13px',
    color:           '#1e293b',
};

/** Formattazione valuta in euro (it-IT) */
export function formatEuro(value: number): string {
    return new Intl.NumberFormat('it-IT', {
        style:    'currency',
        currency: 'EUR',
    }).format(value);
}
