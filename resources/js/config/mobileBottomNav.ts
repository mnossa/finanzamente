import type { HubTabIconId } from '@/utils/hubTabIcons';

export type MobileBottomNavDestinationId =
    | 'dashboard'
    | 'cashflow'
    | 'organization'
    | 'planning'
    | 'investments';

export interface MobileBottomNavDestination {
    id: MobileBottomNavDestinationId;
    label: string;
    ariaLabel: string;
    routeName: string;
    routeMatch: string;
    routeMatchPatterns?: string[];
    icon: HubTabIconId;
    moduleId?: string;
    requiresPro?: boolean;
}

export const MOBILE_BOTTOM_NAV_DEFAULT_SLOTS: MobileBottomNavDestinationId[] = [
    'dashboard',
    'cashflow',
    'organization',
];

export const MOBILE_BOTTOM_NAV_DESTINATIONS: MobileBottomNavDestination[] = [
    {
        id: 'dashboard',
        label: 'Home',
        ariaLabel: 'Dashboard',
        routeName: 'dashboard',
        routeMatch: 'dashboard',
        icon: 'Dashboard',
        moduleId: 'dashboard',
    },
    {
        id: 'cashflow',
        label: 'Movimenti',
        ariaLabel: 'Conti e movimenti',
        routeName: 'transactions.index',
        routeMatch: 'transactions.index',
        routeMatchPatterns: [
            'accounts.*',
            'transactions.*',
            'transfers.*',
            'inter-household-transfers.*',
        ],
        icon: 'ArrowLeftRight',
    },
    {
        id: 'organization',
        label: 'Organizzazione',
        ariaLabel: 'Organizzazione',
        routeName: 'categories.index',
        routeMatch: 'categories.index',
        routeMatchPatterns: [
            'categories.*',
            'inbox.*',
            'refunds.*',
            'recurring-transactions.*',
        ],
        icon: 'Tags',
    },
    {
        id: 'planning',
        label: 'Pianificazione',
        ariaLabel: 'Pianificazione e risparmio',
        routeName: 'budgets.index',
        routeMatch: 'budgets.index',
        routeMatchPatterns: [
            'budgets.*',
            'debts-credits.*',
            'financial-goals.*',
            'tax-deductions.*',
        ],
        icon: 'PiggyBank',
    },
    {
        id: 'investments',
        label: 'Investimenti',
        ariaLabel: 'Investimenti',
        routeName: 'investments.index',
        routeMatch: 'investments.index',
        routeMatchPatterns: [
            'investments.*',
            'investment-pacs.*',
            'asset-allocation.*',
            'investment-assets.*',
            'investment-analyses.*',
        ],
        icon: 'TrendingUp',
        moduleId: 'investments',
        requiresPro: true,
    },
];

export function getMobileBottomNavDestination(
    id: MobileBottomNavDestinationId,
): MobileBottomNavDestination | undefined {
    return MOBILE_BOTTOM_NAV_DESTINATIONS.find((destination) => destination.id === id);
}
