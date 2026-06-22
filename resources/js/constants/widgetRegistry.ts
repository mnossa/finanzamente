import { WidgetDefinition } from '@/types/dashboard';

/**
 * Registry centrale dei widget disponibili nella dashboard.
 */
const ALL_SIZES: WidgetDefinition['allowedSizes'] = ['sm', 'md', 'lg', 'xl'];

export const WIDGET_REGISTRY: WidgetDefinition[] = [
    {
        id: 'lifestyle_widget',
        title: 'Lifestyle Inflation Score',
        description: 'Analizza l\'indice di inflazione del tuo stile di vita.',
        defaultSize: 'xl',
        defaultVisible: true,
        requiresModule: 'lifestyle_score',
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'accounts',
        title: 'I tuoi Conti',
        description: 'Panoramica di tutti i conti attivi con saldo aggiornato.',
        defaultSize: 'md',
        defaultVisible: true,
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'recent_transactions',
        title: 'Ultime Transazioni',
        description: 'Le 10 transazioni più recenti della household.',
        defaultSize: 'md',
        defaultVisible: true,
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'active_budgets',
        title: 'Budget Attivi',
        description: 'Stato dei budget attivi con barra di avanzamento.',
        defaultSize: 'md',
        defaultVisible: true,
        requiresModule: 'budgets',
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'debts_credits',
        title: 'Debiti e Crediti',
        description: 'Riepilogo dei debiti e crediti aperti.',
        defaultSize: 'md',
        defaultVisible: true,
        requiresModule: 'debts_credits',
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'quick_actions',
        title: 'Azioni Rapide',
        description: 'Accesso rapido alle operazioni più frequenti (solo desktop: su mobile usa la barra in basso).',
        defaultSize: 'xl',
        defaultVisible: true,
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'asset_allocation',
        title: 'Asset Allocation',
        description: 'Panoramica del patrimonio suddiviso per asset class e indice di rischio.',
        defaultSize: 'md',
        defaultVisible: true,
        requiresModule: 'investments',
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'expense_treemap',
        title: 'Spese per Categoria',
        description: 'Distribuzione delle uscite per categoria nel mese corrente.',
        defaultSize: 'md',
        defaultVisible: true,
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'financial_goals',
        title: 'Obiettivi Finanziari',
        description: 'Riepilogo degli obiettivi finanziari attivi con avanzamento.',
        defaultSize: 'md',
        defaultVisible: true,
        requiresModule: 'financial_goals',
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'expense_distribution',
        title: 'Distribuzione Spese',
        description: 'Analisi della distribuzione delle spese per Necessità, Extra e Investimenti (regola 50/30/20).',
        defaultSize: 'md',
        defaultVisible: true,
        allowedSizes: ALL_SIZES,
    },
    {
        id: 'pac_projection',
        title: 'Proiezione PAC',
        description: 'Versamenti mensili previsti dai piani di accumulo attivi (solo contributi, senza rendimento di mercato).',
        defaultSize: 'md',
        defaultVisible: false,
        requiresModule: 'investments',
        allowedSizes: ALL_SIZES,
    },
];

/** Mappa per accesso rapido al widget per ID. */
export const WIDGET_MAP = Object.fromEntries(
    WIDGET_REGISTRY.map((w) => [w.id, w])
) as Record<string, WidgetDefinition>;
