import { WidgetDefinition } from '@/types/dashboard';

/**
 * Registry centrale dei widget disponibili nella dashboard.
 *
 * Ogni widget ha un ID stabile, un titolo in italiano, una dimensione di
 * default e le dimensioni consentite. I vincoli di visibilità (es. P.IVA,
 * moduli) sono indicati tramite le proprietà `requiresVat` e `requiresModule`.
 */
export const WIDGET_REGISTRY: WidgetDefinition[] = [
    {
        id: 'total_balance',
        title: 'Saldo Totale',
        description: 'Visualizza il saldo complessivo di tutti i conti.',
        defaultSize: 'lg',
        defaultVisible: true,
        allowedSizes: ['lg', 'xl'],
    },
    {
        id: 'monthly_stats',
        title: 'Statistiche Mensili',
        description: 'Entrate, uscite, saldo netto e transazioni del mese corrente.',
        defaultSize: 'lg',
        defaultVisible: true,
        allowedSizes: ['lg', 'xl'],
    },
    {
        id: 'annual_revenue',
        title: 'Fatturato Annuo',
        description: 'Monitora il fatturato annuo rispetto alla soglia del regime forfettario.',
        defaultSize: 'lg',
        defaultVisible: true,
        requiresVat: true,
        allowedSizes: ['lg', 'xl'],
    },
    {
        id: 'tax_thermometer',
        title: 'Termometro Tasse',
        description: 'Stima le tasse e i contributi INPS in base al reddito lordo.',
        defaultSize: 'lg',
        defaultVisible: true,
        requiresVat: true,
        allowedSizes: ['lg', 'xl'],
    },
    {
        id: 'lifestyle_widget',
        title: 'Lifestyle Inflation Score',
        description: 'Analizza l\'indice di inflazione del tuo stile di vita.',
        defaultSize: 'lg',
        defaultVisible: true,
        allowedSizes: ['md', 'lg', 'xl'],
    },
    {
        id: 'accounts',
        title: 'I tuoi Conti',
        description: 'Panoramica di tutti i conti attivi con saldo aggiornato.',
        defaultSize: 'md',
        defaultVisible: true,
        allowedSizes: ['sm', 'md', 'lg'],
    },
    {
        id: 'recent_transactions',
        title: 'Ultime Transazioni',
        description: 'Le 10 transazioni più recenti della household.',
        defaultSize: 'md',
        defaultVisible: true,
        allowedSizes: ['sm', 'md', 'lg'],
    },
    {
        id: 'active_budgets',
        title: 'Budget Attivi',
        description: 'Stato dei budget attivi con barra di avanzamento.',
        defaultSize: 'md',
        defaultVisible: true,
        requiresModule: 'budgets',
        allowedSizes: ['sm', 'md', 'lg'],
    },
    {
        id: 'debts_credits',
        title: 'Debiti e Crediti',
        description: 'Riepilogo dei debiti e crediti aperti.',
        defaultSize: 'md',
        defaultVisible: true,
        requiresModule: 'debts_credits',
        allowedSizes: ['sm', 'md', 'lg'],
    },
    {
        id: 'quick_actions',
        title: 'Azioni Rapide',
        description: 'Accesso rapido alle operazioni più frequenti.',
        defaultSize: 'lg',
        defaultVisible: true,
        allowedSizes: ['lg', 'xl'],
    },
];

/** Mappa per accesso rapido al widget per ID. */
export const WIDGET_MAP = Object.fromEntries(
    WIDGET_REGISTRY.map((w) => [w.id, w])
) as Record<string, WidgetDefinition>;
