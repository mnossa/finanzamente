/**
 * Form di creazione guidati: controllato da FEATURE_GUIDED_CREATE_FORMS (.env).
 */

/** Route Ziggy `.create` che usano GuidedFormWizard quando il flag è on. */
export const GUIDED_CREATE_ROUTE_NAMES = [
    'transactions.create',
    'accounts.create',
    'categories.create',
    'tags.create',
    'budgets.create',
    'financial-goals.create',
    'recurring-transactions.create',
    'debts-credits.create',
    'transfers.create',
    'refunds.create',
    'inter-household-transfers.create',
    'investments.create',
    'investment-assets.create',
    'households.create',
] as const;

export type GuidedCreateRouteName = (typeof GUIDED_CREATE_ROUTE_NAMES)[number];

export function isGuidedCreateEnabled(features?: Record<string, boolean>): boolean {
    return features?.guided_create_forms !== false;
}

export function isGuidedCreateRoute(current?: string | false | null): current is GuidedCreateRouteName {
    if (!current || typeof current !== 'string') {
        return false;
    }

    return (GUIDED_CREATE_ROUTE_NAMES as readonly string[]).includes(current);
}

/**
 * Nasconde FAB submit + bottom nav mobile sulle create guidate (CTA solo in-card).
 */
export function shouldHideMobileChromeForGuidedCreate(
    features?: Record<string, boolean>,
    current?: string | false | null,
): boolean {
    return isGuidedCreateEnabled(features) && isGuidedCreateRoute(current);
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
