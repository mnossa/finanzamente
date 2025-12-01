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
}

export interface Household {
    id: number;
    name: string;
    owner_user_id: number;
    owner?: User;
    is_owner?: boolean;
    role?: 'owner' | 'member' | 'guest';
    members_count?: number;
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

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    activeHousehold?: ActiveHousehold | null;
    flash?: {
        success?: string;
        error?: string;
        info?: string;
    };
};

