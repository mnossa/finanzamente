/**
 * Tipi TypeScript per la personalizzazione del layout della dashboard.
 */

/** Identificativi stabili dei widget built-in nella dashboard. */
export type KnownWidgetId =
    | 'lifestyle_widget'
    | 'accounts'
    | 'recent_transactions'
    | 'active_budgets'
    | 'debts_credits'
    | 'asset_allocation'
    | 'expense_treemap'
    | 'financial_goals'
    | 'expense_distribution'
    | 'pac_projection';

/** Widget dinamici creati dall'utente (formula platform). */
export type FormulaWidgetLayoutId = `formula_widget_${number}`;

/** Identificativo di un widget nel layout (built-in o formula). */
export type WidgetId = KnownWidgetId | FormulaWidgetLayoutId;

/** Dimensioni supportate per ogni widget nella griglia. */
export type WidgetSize = 'sm' | 'md' | 'lg' | 'xl';

/** Configurazione di un singolo widget salvata nel DB. */
export interface WidgetConfig {
    id: WidgetId;
    visible: boolean;
    position: number;
    size: WidgetSize;
    /** Parametri runtime selezionati dall'utente in dashboard (es. conto). */
    runtime_params?: Record<string, string>;
}

/** Configurazione completa del layout dashboard. */
export interface DashboardLayoutConfig {
    widgets: WidgetConfig[];
}

/** Definizione statica di un widget nel registry (solo frontend). */
export interface WidgetDefinition {
    id: KnownWidgetId;
    title: string;
    description: string;
    defaultSize: WidgetSize;
    defaultVisible: boolean;
    /** Se impostato, il widget richiede che il modulo sia abilitato. */
    requiresModule?: string;
    /** Dimensioni consentite per questo widget. */
    allowedSizes: WidgetSize[];
}
