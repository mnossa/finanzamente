import type { SectionHubTab } from '@/Components/SectionHubNav';

/** Rotte index degli hub — swipe abilitato solo su queste. */
export const HUB_INDEX_ROUTE_NAMES = new Set([
    'budgets.index',
    'debts-credits.index',
    'financial-goals.index',
    'tax-deductions.index',
    'transactions.index',
    'accounts.index',
    'transfers.index',
    'transactions.quick-session',
    'inter-household-transfers.index',
    'investments.index',
    'investment-pacs.index',
    'asset-allocation.index',
    'investment-assets.index',
    'investment-analyses.index',
    'categories.index',
    'inbox.index',
    'refunds.index',
    'recurring-transactions.index',
]);

export function isHubIndexRoute(routeName: string | null | undefined): boolean {
    if (!routeName) {
        return false;
    }

    return HUB_INDEX_ROUTE_NAMES.has(routeName);
}

export function resolveSectionHubTabHref(tab: SectionHubTab, isProPlan: boolean): string {
    const lockedByPlan = tab.requiresPro && !isProPlan;

    if (lockedByPlan && tab.moduleId) {
        return `${route('profile.subscription')}?from=${tab.moduleId}`;
    }

    return route(tab.routeName);
}

export function getVisibleHubTabs(tabs: SectionHubTab[]): SectionHubTab[] {
    return tabs.filter((tab) => !tab.hidden);
}

export function getAdjacentHubTabHref(
    tabs: SectionHubTab[],
    activeId: string,
    direction: 'next' | 'prev',
    isProPlan: boolean,
): string | null {
    const visible = getVisibleHubTabs(tabs);
    const currentIndex = visible.findIndex((tab) => tab.id === activeId);

    if (currentIndex === -1) {
        return null;
    }

    const offset = direction === 'next' ? 1 : -1;
    const target = visible[currentIndex + offset];

    if (!target) {
        return null;
    }

    return resolveSectionHubTabHref(target, isProPlan);
}
