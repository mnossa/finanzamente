/**
 * FAB centrale (bottom nav mobile): destinazione primaria in base alla rotta corrente.
 * Su form di creazione/modifica e sessione rapida diventa submit verso il form con id
 * {@link FM_MOBILE_PRIMARY_FORM_ID}.
 * Tenere allineato con i test PHP che verificano Route::has sui nomi usati.
 */
export const FM_MOBILE_PRIMARY_FORM_ID = 'fm-mobile-primary-form';

export const MOBILE_FAB_ACTION_INVESTMENT_ANALYSES_NEW = 'investment-analyses-new';

export const FM_MOBILE_FAB_ACTION_EVENT = 'fm-mobile-fab-action';

export type MobilePrimaryFab =
    | {
          mode: 'link';
          href: string;
          ariaLabel: string;
          /** Slug anonimo per analytics (no PII) */
          analyticsSection: string;
      }
    | {
          mode: 'submit';
          ariaLabel: string;
          analyticsSection: string;
          /** Attributo HTML `form`; default {@link FM_MOBILE_PRIMARY_FORM_ID} */
          formId?: string;
      }
    | {
          mode: 'action';
          ariaLabel: string;
          analyticsSection: string;
          actionId: string;
      };

const defaultFab = (): MobilePrimaryFab => ({
    mode: 'link',
    href: route('transactions.create'),
    ariaLabel: 'Nuova transazione',
    analyticsSection: 'transactions',
});

/** Path sotto /investimenti/pac (index, crea, …). */
const INVESTMENT_PAC_PATH = /\/investimenti\/pac(?:\/|$)/;

/**
 * Ziggy può associare /investimenti/pac a `investments.show` (parametro "pac") prima di
 * `investment-pacs.index`: il FAB non deve mai cadere sul default transazioni.
 */
function isInvestmentPacSection(): boolean {
    if (typeof route === 'function' && route().current('investment-pacs.*')) {
        return true;
    }

    if (typeof window !== 'undefined') {
        return INVESTMENT_PAC_PATH.test(window.location.pathname);
    }

    return false;
}

function isInvestmentPacCreatePage(): boolean {
    if (typeof route === 'function' && route().current('investment-pacs.create')) {
        return true;
    }

    if (typeof window !== 'undefined') {
        return /\/investimenti\/pac\/crea\/?$/.test(window.location.pathname);
    }

    return false;
}

function investmentPacFab(): MobilePrimaryFab {
    if (isInvestmentPacCreatePage()) {
        return {
            mode: 'submit',
            ariaLabel: 'Crea PAC',
            analyticsSection: 'investment_pacs_create_save',
        };
    }

    return {
        mode: 'link',
        href: route('investment-pacs.create'),
        ariaLabel: 'Nuovo PAC',
        analyticsSection: 'investment_pacs',
    };
}

/**
 * Pagine con più form principali: nessun FAB submit (resta il link di default dopo i `when`).
 */
function isExcludedFromMobileSubmitFab(current: string): boolean {
    return current === 'profile.edit';
}

function tryResolveMobileSubmitFab(current: string): MobilePrimaryFab | null {
    if (isExcludedFromMobileSubmitFab(current)) {
        return null;
    }

    if (current === 'transactions.quick-session') {
        return {
            mode: 'submit',
            ariaLabel: 'Salva transazione',
            analyticsSection: 'transactions_quick_session_save',
        };
    }

    if (isInvestmentPacCreatePage()) {
        return null;
    }

    if (current.endsWith('.create') || current.endsWith('.edit')) {
        return {
            mode: 'submit',
            ariaLabel: 'Salva',
            analyticsSection: `${current.replace(/[.-]/g, '_')}_save`,
        };
    }

    return null;
}

/**
 * Risolve href/etichetta del pulsante centrale nel footer mobile.
 */
export function resolveMobilePrimaryFab(): MobilePrimaryFab | null {
    if (typeof route !== 'function') {
        return defaultFab();
    }

    if (isInvestmentPacSection()) {
        return investmentPacFab();
    }

    const current = route().current();
    if (!current || typeof current !== 'string') {
        return defaultFab();
    }

    const submitFab = tryResolveMobileSubmitFab(current);
    if (submitFab) {
        return submitFab;
    }

    /** Pagine strumento / prevalentemente lettura: nessun FAB */
    const routePatternsWithoutFab = [
        'simulations.*',
        'recurrence-detection.*',
        'tax-deductions.*',
        'lifestyle-score.*',
        'asset-allocation.*',
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
            mode: 'link',
            href: route('accounts.create'),
            ariaLabel: 'Nuovo conto',
            analyticsSection: 'accounts',
        }) ??
        when(['categories.index'], {
            mode: 'link',
            href: route('categories.create'),
            ariaLabel: 'Nuova categoria',
            analyticsSection: 'categories',
        }) ??
        when(['budgets.index', 'budgets.show'], {
            mode: 'link',
            href: route('budgets.create'),
            ariaLabel: 'Nuovo budget',
            analyticsSection: 'budgets',
        }) ??
        when(['financial-goals.index', 'financial-goals.show'], {
            mode: 'link',
            href: route('financial-goals.create'),
            ariaLabel: 'Nuovo obiettivo',
            analyticsSection: 'financial_goals',
        }) ??
        when(['recurring-transactions.index', 'recurring-transactions.show'], {
            mode: 'link',
            href: route('recurring-transactions.create'),
            ariaLabel: 'Nuova ricorrenza',
            analyticsSection: 'recurring_transactions',
        }) ??
        when(['transactions.index', 'transactions.show', 'transactions.import'], {
            mode: 'link',
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'transactions',
        }) ??
        when(['tags.index'], {
            mode: 'link',
            href: route('tags.create'),
            ariaLabel: 'Nuova etichetta',
            analyticsSection: 'tags',
        }) ??
        when(['debts-credits.index', 'debts-credits.show'], {
            mode: 'link',
            href: route('debts-credits.create'),
            ariaLabel: 'Nuovo debito/credito',
            analyticsSection: 'debts_credits',
        }) ??
        when(['transfers.index', 'transfers.show'], {
            mode: 'link',
            href: route('transfers.create'),
            ariaLabel: 'Nuovo trasferimento',
            analyticsSection: 'transfers',
        }) ??
        when(['refunds.index', 'refunds.show'], {
            mode: 'link',
            href: route('refunds.create'),
            ariaLabel: 'Nuovo rimborso',
            analyticsSection: 'refunds',
        }) ??
        when(['inter-household-transfers.index', 'inter-household-transfers.show'], {
            mode: 'link',
            href: route('inter-household-transfers.create'),
            ariaLabel: 'Nuovo trasferimento tra nuclei',
            analyticsSection: 'inter_household_transfers',
        }) ??
        when(['investments.index', 'investments.show'], {
            mode: 'link',
            href: route('investments.create'),
            ariaLabel: 'Nuovo investimento',
            analyticsSection: 'investments',
        }) ??
        when(['investment-assets.index'], {
            mode: 'link',
            href: route('investment-assets.create'),
            ariaLabel: 'Nuovo asset',
            analyticsSection: 'investment_assets',
        }) ??
        when(['investment-analyses.index'], {
            mode: 'action',
            ariaLabel: 'Nuova analisi',
            analyticsSection: 'investment_analyses',
            actionId: MOBILE_FAB_ACTION_INVESTMENT_ANALYSES_NEW,
        }) ??
        when(['inbox.index'], {
            mode: 'link',
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'inbox_fallback_transactions',
        }) ??
        when(['formula-widgets.index'], {
            mode: 'link',
            href: route('formula-widgets.create'),
            ariaLabel: 'Nuovo widget',
            analyticsSection: 'formula_widgets',
        }) ??
        when(['formula-marketplace.index'], {
            mode: 'link',
            href: route('formula-widgets.create'),
            ariaLabel: 'Nuovo widget',
            analyticsSection: 'formula_marketplace',
        }) ??
        when(['formula-variables.index'], {
            mode: 'link',
            href: route('formula-widgets.create'),
            ariaLabel: 'Nuovo widget',
            analyticsSection: 'formula_variables',
        }) ??
        when(['dashboard'], {
            mode: 'link',
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'dashboard',
        }) ??
        when(['fixed-expenses.dashboard'], {
            mode: 'link',
            href: route('transactions.create'),
            ariaLabel: 'Nuova transazione',
            analyticsSection: 'fixed_expenses',
        }) ??
        defaultFab()
    );
}

export function dispatchMobileFabAction(actionId: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(FM_MOBILE_FAB_ACTION_EVENT, {
        detail: { actionId },
    }));
}

/**
 * True se il FAB mobile corrente esegue l'azione inline indicata (toolbar create ridondante su mobile).
 */
export function isMobileFabActionCovered(actionId: string): boolean {
    const fab = resolveMobilePrimaryFab();

    return fab?.mode === 'action' && fab.actionId === actionId;
}

/** Normalizza pathname per confronto href (ignora query/hash, trailing slash). */
export function normalizeMobileFabPath(href: string): string {
    if (typeof window === 'undefined') {
        return href.replace(/\/$/, '') || '/';
    }

    try {
        const url = href.startsWith('http') ? new URL(href) : new URL(href, window.location.origin);
        const path = url.pathname.replace(/\/$/, '') || '/';
        return path;
    } catch {
        return href.replace(/\/$/, '') || '/';
    }
}

/**
 * True se l'href coincide con l'azione link del FAB mobile corrente (create ridondante in toolbar).
 */
export function isHrefCoveredByMobileFab(href: string): boolean {
    const fab = resolveMobilePrimaryFab();
    if (!fab || fab.mode !== 'link') {
        return false;
    }

    return normalizeMobileFabPath(href) === normalizeMobileFabPath(fab.href);
}
