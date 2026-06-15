const DISPLAY_LABELS: Record<string, string> = {
    kpi: 'KPI',
    line: 'Linea',
    area: 'Area',
    bar: 'Barre verticali',
    horizontal_bar: 'Barre orizzontali',
    stacked_bar: 'Barre impilate',
    pie: 'Torta',
    treemap: 'Treemap',
    progress: 'Avanzamento',
};

const DISPLAY_BADGE_LABELS: Record<string, string> = {
    kpi: 'KPI',
    line: 'Linea',
    area: 'Area',
    bar: 'Barre',
    horizontal_bar: 'Oriz.',
    stacked_bar: 'Impilate',
    pie: 'Torta',
    treemap: 'Tree',
    progress: 'Obiettivo',
};

export function formulaWidgetDisplayLabel(displayType: string): string {
    return DISPLAY_LABELS[displayType] ?? displayType;
}

export function formulaWidgetBadgeLabel(displayType: string): string {
    return DISPLAY_BADGE_LABELS[displayType] ?? formulaWidgetDisplayLabel(displayType);
}
