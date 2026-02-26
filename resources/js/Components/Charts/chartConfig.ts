/**
 * chartConfig.ts
 * Palette colori e stili condivisi per tutti i grafici (Tremor e Recharts).
 */

import { useEffect, useState } from 'react';

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

export function useChartDarkMode(): boolean {
    const [isDark, setIsDark] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') return;

        const updateTheme = () => {
            setIsDark(document.documentElement.classList.contains('dark'));
        };

        updateTheme();

        const observer = new MutationObserver(updateTheme);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    return isDark;
}

export function getChartTooltipStyle(isDark: boolean) {
    return {
        backgroundColor: isDark ? '#0f172a' : '#ffffff',
        border: isDark ? '1px solid #334155' : '1px solid #e2e8f0',
        borderRadius: '8px',
        boxShadow: isDark
            ? '0 10px 20px -8px rgb(0 0 0 / 0.5)'
            : '0 4px 6px -1px rgb(0 0 0 / 0.1)',
        padding: '8px 12px',
        fontSize: '13px',
        color: isDark ? '#e2e8f0' : '#1e293b',
    };
}

export function getChartMutedTextColor(isDark: boolean): string {
    return isDark ? '#94a3b8' : '#64748b';
}

/** Formattazione valuta in euro (it-IT) */
export function formatEuro(value: number): string {
    return new Intl.NumberFormat('it-IT', {
        style:    'currency',
        currency: 'EUR',
    }).format(value);
}
