/**
 * analytics.ts — Sistema di tracking eventi Umami (anonymous, GDPR-compliant)
 *
 * Pattern di nomenclatura: entità.azione (snake_case, lowercase)
 *
 * Regole fondamentali:
 *  - MAI includere importi, descrizioni, nomi utente, email o qualsiasi PII
 *  - Tracciare solo flag booleani, tipi enum, contatori, durate
 *  - Umami non registra ID utente: ogni evento è de-facto anonimo
 *
 * Cosa cattura Umami nativamente (senza codice custom):
 *  - Page views + URL visitati
 *  - Tipo dispositivo (mobile/desktop/tablet) — da User-Agent
 *  - Browser e OS
 *  - Paese (da IP, mai memorizzato il raw IP)
 *  - Durata sessione
 *  - Tasso di rimbalzo
 */

type EventPayload = Record<string, string | number | boolean | null | undefined>;

type QueuedEvent = { name: string; data: EventPayload };

const FIRST_PARTY_QUEUE: QueuedEvent[] = [];
let flushTimer: ReturnType<typeof setTimeout> | null = null;
let csrfTokenCache: string | null = null;

function readCsrfToken(): string | null {
    if (csrfTokenCache) {
        return csrfTokenCache;
    }
    if (typeof document === 'undefined') {
        return null;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    csrfTokenCache = meta?.getAttribute('content') ?? null;
    return csrfTokenCache;
}

function analyticsConsentGranted(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }
    // Shared Inertia prop mirrored on window by AuthenticatedLayout when present.
    const flag = (window as Window & { __fmAnalyticsEnabled?: boolean }).__fmAnalyticsEnabled;
    return flag === true;
}

function enqueueFirstParty(name: string, data: EventPayload): void {
    if (typeof window === 'undefined' || !analyticsConsentGranted()) {
        return;
    }

    FIRST_PARTY_QUEUE.push({ name, data });
    if (FIRST_PARTY_QUEUE.length >= 10) {
        void flushFirstPartyQueue();
        return;
    }

    if (flushTimer) {
        return;
    }

    flushTimer = setTimeout(() => {
        flushTimer = null;
        void flushFirstPartyQueue();
    }, 1500);
}

async function flushFirstPartyQueue(): Promise<void> {
    if (FIRST_PARTY_QUEUE.length === 0 || !analyticsConsentGranted()) {
        FIRST_PARTY_QUEUE.length = 0;
        return;
    }

    const batch = FIRST_PARTY_QUEUE.splice(0, 20);
    const token = readCsrfToken();
    if (!token) {
        return;
    }

    try {
        await fetch('/product-analytics/events', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                events: batch.map((event) => ({
                    name: event.name,
                    data: event.data,
                })),
            }),
        });
    } catch {
        // Best-effort: non bloccare UX se ingest fallisce.
    }
}

if (typeof window !== 'undefined') {
    window.addEventListener('pagehide', () => {
        void flushFirstPartyQueue();
    });
}

/**
 * Primitiva di tracking: invia un evento a Umami se disponibile,
 * e in parallelo agli aggregati first-party (se consenso analytics).
 * In dev logga sulla console senza richiedere il consenso.
 */
export function trackEvent(name: string, data: EventPayload = {}): void {
    if (typeof window === 'undefined') return;

    enqueueFirstParty(name, data);

    const umami = (window as Window & { umami?: { track: (n: string, d?: EventPayload) => void } }).umami;

    if (umami && typeof umami.track === 'function') {
        umami.track(name, data);
        return;
    }

    if (import.meta.env.DEV) {
        // eslint-disable-next-line no-console
        console.log('[Analytics]', name, data);
    }
}

// ─── Transazioni ──────────────────────────────────────────────────────────────

/** Payload anonimo per la creazione/modifica di una transazione */
export interface TransactionEventPayload {
    /** Tipo categoria (expense / income) */
    type: 'expense' | 'income';
    /** Presenza di tag */
    has_tags: boolean;
    /** Presenza di descrizione */
    has_description: boolean;
    /** Uso della sezione valuta estera */
    has_fx: boolean;
    /** Flag transazione privata */
    is_private: boolean;
    /** Flag detrazione fiscale */
    is_tax_deductible: boolean;
    /** Collegamento a debito/credito */
    has_debt_link: boolean;
    /** Secondi trascorsi dal mount del form al submit */
    form_seconds: number;
}

export const tx = {
    created: (p: TransactionEventPayload) =>
        trackEvent('transaction.created', p as unknown as EventPayload),

    edited: (p: Pick<TransactionEventPayload, 'type' | 'has_tags' | 'has_fx' | 'form_seconds'>) =>
        trackEvent('transaction.edited', p as unknown as EventPayload),

    deleted: () => trackEvent('transaction.deleted'),

    importStarted: () => trackEvent('transaction.import.started'),

    importCompleted: (rows: number) =>
        trackEvent('transaction.import.completed', { rows }),

    /** Sezione "valuta diversa dal conto" aperta per la prima volta */
    fxOpened: () => trackEvent('transaction.fx.opened'),

    /** Pannello "Opzioni aggiuntive" aperto */
    optionsOpened: () => trackEvent('transaction.options.opened'),

    /** Sezione detrazione fiscale attivata */
    taxOpened: () => trackEvent('transaction.tax.opened'),

    /**
     * Form abbandonato senza submit.
     * Utile per calcolare il tasso di conversione form.
     */
    formAbandoned: (form: 'create' | 'edit', seconds: number) =>
        trackEvent('form.abandoned', {
            form: `transaction.${form}`,
            form_seconds: seconds,
        }),

    /**
     * Errori di validazione mostrati all'utente.
     * I nomi dei campi sono tecnici (non PII).
     */
    formError: (form: 'create' | 'edit', fields: string[]) =>
        trackEvent('form.error', {
            form: `transaction.${form}`,
            error_fields: fields.join(','),
        }),
} as const;

// ─── Conti ────────────────────────────────────────────────────────────────────

export const accs = {
    created: (accountType: string, currency: string) =>
        trackEvent('account.created', { account_type: accountType, currency }),
} as const;

// ─── Trasferimenti ────────────────────────────────────────────────────────────

export const transfers = {
    created: () => trackEvent('transfer.created'),
} as const;

// ─── Budget ───────────────────────────────────────────────────────────────────

export const budgets = {
    created: (period: string) => trackEvent('budget.created', { period }),
} as const;

// ─── Categorie ────────────────────────────────────────────────────────────────

export const cats = {
    created: (type: 'expense' | 'income') =>
        trackEvent('category.created', { type }),

    /** Tab Uscite/Entrate cambiato nel CategoryPicker */
    tabChanged: (to: 'expense' | 'income') =>
        trackEvent('category.picker.tab', { to }),
} as const;

// ─── Obiettivi finanziari ─────────────────────────────────────────────────────

export const goals = {
    created: (hasDeadline: boolean) =>
        trackEvent('goal.created', { has_deadline: hasDeadline }),
} as const;

// ─── Debiti / Crediti ─────────────────────────────────────────────────────────

export const debts = {
    created: (type: 'debt' | 'credit') =>
        trackEvent('debt_credit.created', { type }),
} as const;

// ─── Ricorrenti ───────────────────────────────────────────────────────────────

export const recurring = {
    created: (frequency: string, type: 'expense' | 'income') =>
        trackEvent('recurring.created', { frequency, type }),
} as const;

// ─── Rimborsi ─────────────────────────────────────────────────────────────────

export const refunds = {
    created: () => trackEvent('refund.created'),
} as const;

// ─── Filtri lista transazioni ─────────────────────────────────────────────────

export type FilterType = 'account' | 'category' | 'type' | 'date_from' | 'date_to' | 'tag' | 'description' | 'amount';

export const filtersAnalytics = {
    applied: (filterType: FilterType) =>
        trackEvent('filter.applied', { filter_type: filterType }),

    cleared: () => trackEvent('filter.cleared'),
} as const;

// ─── Navigazione ──────────────────────────────────────────────────────────────

export const nav = {
    /** Tap su una voce della bottom navigation mobile */
    bottomBar: (destination: string) =>
        trackEvent('nav.bottom_bar', { destination }),

    /** Tap sul FAB floating mobile/tablet (destinazione dipende dalla pagina corrente) */
    mobileFab: (section: string) => trackEvent('nav.mobile_fab', { section }),
} as const;
