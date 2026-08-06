import type { HubTabIconId } from '@/utils/hubTabIcons';

export type MobileBottomNavDestinationId =
    | 'dashboard'
    | 'cashflow'
    | 'organization'
    | 'planning'
    | 'patrimonio'
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
}

export const MOBILE_BOTTOM_NAV_SLOT_COUNT = 4;

export const MOBILE_BOTTOM_NAV_DEFAULT_SLOTS: MobileBottomNavDestinationId[] = [
    'dashboard',
    'cashflow',
    'planning',
    'patrimonio',
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
        ariaLabel: 'Movimenti',
        routeName: 'transactions.index',
        routeMatch: 'transactions.index',
        routeMatchPatterns: [
            'transactions.*',
            'transfers.*',
            'refunds.*',
            'recurring-transactions.*',
            'inbox.*',
            'inter-household-transfers.*',
        ],
        icon: 'ArrowLeftRight',
    },
    {
        id: 'patrimonio',
        label: 'Patrimonio',
        ariaLabel: 'Patrimonio',
        routeName: 'patrimonio.index',
        routeMatch: 'patrimonio.index',
        routeMatchPatterns: [
            'patrimonio.*',
            'investments.*',
            'investment-pacs.*',
            'asset-allocation.*',
            'investment-assets.*',
            'investment-analyses.*',
        ],
        icon: 'Wallet',
    },
    {
        id: 'organization',
        label: 'Organizzazione',
        ariaLabel: 'Organizzazione',
        routeName: 'accounts.index',
        routeMatch: 'accounts.index',
        routeMatchPatterns: [
            'categories.*',
            'accounts.*',
            'tags.*',
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
    },
];

export function getMobileBottomNavDestination(
    id: MobileBottomNavDestinationId,
): MobileBottomNavDestination | undefined {
    return MOBILE_BOTTOM_NAV_DESTINATIONS.find((destination) => destination.id === id);
}
