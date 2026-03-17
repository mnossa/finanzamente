export interface User {
    id: number;
    name: string;
    first_name?: string;
    last_name?: string;
    email: string;
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
    enabled: boolean;
    locked: boolean;
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
    permissions: Permissions;
    modules: ModulesMap;
    flash?: {
        success?: string;
        error?: string;
        info?: string;
    };
    notifications: {
        unread_count: number;
        items: AppNotification[];
    };
    googleDrive?: {
        clientId: string;
        apiKey: string;
    };
};

export interface AppNotification {
    id: number;
    title: string;
    message: string;
    read: boolean;
    created_at: string;
}

