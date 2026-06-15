import type { FormulaWidgetSummary } from '@/types/formulaWidget';

export interface User {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    email: string;
    default_currency_code?: string | null;
    income_band?: string | null;
    macro_region?: string | null;
    email_verified_at?: string;
    active_household_id?: number;
    birth_date?: string;
    status?: 'active' | 'suspended' | 'deleted';
    preferences?: Record<string, unknown>;
    profile_completed?: boolean;
    user_type?: 'persona' | 'partita_iva';
    profile_settings?: {
        has_vat: boolean;
        family_status: 'single' | 'couple' | 'family';
        tracks_investments: boolean;
        completed_at?: string;
        updated_at?: string;
    };
}

export interface Household {
    id: number;
    name: string;
    owner_user_id: number;
    owner?: User;
    is_owner?: boolean;
    role?: 'owner' | 'member' | 'guest';
    members_count?: number;
    financial_management_type?: 'debt_balancing' | 'shared_wallet';
    financial_management_type_label?: string;
    balance_percentages?: Record<string, number>;
    enable_turn_suggestions?: boolean;
    turn_suggestion_settings?: Record<string, unknown>;
    last_turn_assignments?: Record<string, number>;
    exclude_inter_transfers_from_stats?: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface ActiveHousehold {
    id: number;
    name: string;
    is_owner: boolean;
}

export interface HouseholdMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'member' | 'guest';
    is_owner: boolean;
}

export interface Permissions {
    canModify: boolean;
    role: 'owner' | 'member' | 'guest' | null;
}

export interface Module {
    id: string;
    name: string;
    category: 'base' | 'special' | 'planning' | 'fiscal' | 'investments';
    routes: string[];
    requires: string[];
    requires_plan: 'base' | 'pro';
    enabled: boolean;
    locked: boolean;
    locked_by_plan: boolean;
    missing_requirements: string[];
    unlock_hint: string | null;
}

export type ModulesMap = Record<string, Module>;

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    activeHousehold?: ActiveHousehold | null;
    formulaWidgetDataVersion?: string | null;
    permissions: Permissions;
    modules: ModulesMap;
    flash?: {
        success?: string;
        error?: string;
        info?: string;
        duplicateWidget?: FormulaWidgetSummary | null;
        duplicateMarketplaceWidget?: FormulaWidgetSummary | null;
    };
    notifications: {
        unread_count: number;
        items: AppNotification[];
    };
    googleDrive?: {
        clientId: string;
        apiKey: string;
    };
    umami?: {
        websiteId: string;
    };
    plan?: {
        current: 'base' | 'pro';
        pro_enabled: boolean;
        waitlist_enabled: boolean;
        expires_at: string | null;
        days_until_expiry: number | null;
        excess_accounts: number;
        excess_households: number;
    } | null;
    isEarlyBird?: boolean;
    isAdmin?: boolean;
    privacy?: {
        analytics_enabled: boolean;
    };
    marketing?: {
        can_register: boolean;
    };
};

export interface AppNotification {
    id: number;
    title: string;
    message: string;
    read: boolean;
    action_url?: string | null;
    created_at: string;
}

