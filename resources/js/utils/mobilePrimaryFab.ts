/**
 * FAB centrale (bottom nav mobile): destinazione primaria in base alla rotta corrente.
 * Tenere allineato con i test PHP che verificano Route::has sui nomi usati.
 */
export type MobilePrimaryFab = {
    href: string;
    ariaLabel: string;
    /** Slug anonimo per analytics (no PII) */
    analyticsSection: string;
};

const defaultFab = (): MobilePrimaryFab => ({
    href: route('transactions.create'),
    ariaLabel: 'Nuova transazione',
    analyticsSection: 'transactions',
});

/**
 * Risolve href/etichetta del pulsante + nel footer mobile.
 * Su form crea/modifica torna al default (nuova transazione) per evitare loop sulla stessa pagina.
 */
export function resolveMobilePrimaryFab(): MobilePrimaryFab | null {
    if (typeof route !== 'function') {
        return defaultFab();
    }

    const current = route().current();
    if (!current || typeof current !== 'string') {
        return defaultFab();
    }

    if (current.endsWith('.create') || current.endsWith('.edit')) {
        return defaultFab();
    }

    /** Pagine strumento / prevalentemente lettura: nessun FAB */
    const routePatternsWithoutFab = [
        'simulations.*',
        'recurrence-detection.*',
        'tax-deductions.*',
        'lifestyle-score.*',
        'asset-allocation.*',
        'investment-analyses.*',
        'telegram.link.*',
        'bank-import-layouts.*',
    ] as const;

    for (const pattern of routePatternsWithoutFab) {
        if (route().current(pattern)) {
            return null;
        }
    }

    const when = (patterns: string[], fab: MobilePrimaryFab): MobilePrimaryFab | null => {
        for (const p of patterns) {
            if (route().current(p)) {
                return fab;
            }
        }
        return null;
    };

    return (
        when(['accounts.index', 'accounts.show'], {
            href: route('accounts.create'),
            ariaLabel: 'Nuovo conto',
            analyticsSection: 'accounts',
        }) ??
        when(['categories.index'], {
            href: route('categories.create'),
            ariaLabel: 'Nuova categoria',
            analyticsSection: 'categories',
        }) ??
        when(['budgets.index', 'budgets.show'], {
            href: route('budgets.create'),
            ariaLabel: 'Nuovo budget',
            analyticsSection: 'budgets',
        }) ??
        when(['financial-goals.index', 'financial-goals.show'], {
            href: route('financial-goals.create'),
            ariaLabel: 'Nuovo obiettivo',
            analyticsSection: 'financial_goals',
        }) ??
        when(['recurring-transactions.index', 'recurring-transactions.show'], {
            href: route('recurring-transactions.create'),
            ariaLabel: 'Nuova ricorrenza',
            analyticsSection: 'recurring_transactions',
        }) ??
        when(['transactions.index', 'transactions.show', 'transactions.import', 'transactions.quick-session'], {
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'transactions',
        }) ??
        when(['tags.index'], {
            href: route('tags.create'),
            ariaLabel: 'Nuova etichetta',
            analyticsSection: 'tags',
        }) ??
        when(['debts-credits.index', 'debts-credits.show'], {
            href: route('debts-credits.create'),
            ariaLabel: 'Nuovo debito/credito',
            analyticsSection: 'debts_credits',
        }) ??
        when(['transfers.index', 'transfers.show'], {
            href: route('transfers.create'),
            ariaLabel: 'Nuovo trasferimento',
            analyticsSection: 'transfers',
        }) ??
        when(['refunds.index', 'refunds.show'], {
            href: route('refunds.create'),
            ariaLabel: 'Nuovo rimborso',
            analyticsSection: 'refunds',
        }) ??
        when(['inter-household-transfers.index', 'inter-household-transfers.show'], {
            href: route('inter-household-transfers.create'),
            ariaLabel: 'Nuovo trasferimento tra nuclei',
            analyticsSection: 'inter_household_transfers',
        }) ??
        when(['investments.index', 'investments.show'], {
            href: route('investments.create'),
            ariaLabel: 'Nuovo investimento',
            analyticsSection: 'investments',
        }) ??
        when(['investment-assets.index'], {
            href: route('investment-assets.create'),
            ariaLabel: 'Nuovo asset',
            analyticsSection: 'investment_assets',
        }) ??
        when(['inbox.index'], {
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'inbox_fallback_transactions',
        }) ??
        when(['dashboard'], {
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'dashboard',
        }) ??
        when(['fixed-expenses.dashboard'], {
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'fixed_expenses',
        }) ??
        defaultFab()
    );
}
