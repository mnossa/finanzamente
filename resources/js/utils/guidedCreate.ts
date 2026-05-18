/**
 * Form di creazione guidati: controllato da FEATURE_GUIDED_CREATE_FORMS (.env).
 */
export function isGuidedCreateEnabled(features?: Record<string, boolean>): boolean {
    return features?.guided_create_forms !== false;
}

export function wizardSteps(count: number): { label?: string }[] {
    return Array.from({ length: count }, () => ({}));
}

export function formatItalianDate(dateStr: string): string {
    if (!dateStr) {
        return '-';
    }
    const normalized = dateStr.includes('T') ? dateStr : `${dateStr}T12:00:00`;
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(normalized));
}
