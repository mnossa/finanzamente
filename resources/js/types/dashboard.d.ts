/**
 * Tipi TypeScript per la personalizzazione del layout della dashboard.
 */

/** Identificativi stabili dei widget disponibili nella dashboard. */
export type WidgetId =
    | 'total_balance'
    | 'monthly_stats'
    | 'annual_revenue'
    | 'tax_thermometer'
    | 'lifestyle_widget'
    | 'accounts'
    | 'recent_transactions'
    | 'active_budgets'
    | 'debts_credits'
    | 'quick_actions'
    | 'asset_allocation'
    | 'net_worth'
    | 'cash_flow'
    | 'expense_treemap'
    | 'financial_goals';

/** Dimensioni supportate per ogni widget nella griglia. */
export type WidgetSize = 'sm' | 'md' | 'lg' | 'xl';

/** Configurazione di un singolo widget salvata nel DB. */
export interface WidgetConfig {
    id: WidgetId;
    visible: boolean;
    position: number;
    size: WidgetSize;
}

/** Configurazione completa del layout dashboard. */
export interface DashboardLayoutConfig {
    widgets: WidgetConfig[];
}

/** Definizione statica di un widget nel registry (solo frontend). */
export interface WidgetDefinition {
    id: WidgetId;
    title: string;
    description: string;
    defaultSize: WidgetSize;
    defaultVisible: boolean;
    /** Se true, il widget viene mostrato solo a utenti con Partita IVA. */
    requiresVat?: boolean;
    /** Se impostato, il widget richiede che il modulo sia abilitato. */
    requiresModule?: string;
    /** Dimensioni consentite per questo widget. */
    allowedSizes: WidgetSize[];
}
