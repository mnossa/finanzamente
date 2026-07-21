import type { SectionHubTab } from '@/Components/SectionHubNav';
import { router } from '@inertiajs/react';

export type HubNavDirection = 'next' | 'prev';

const HUB_NAV_DIRECTION_KEY = 'fm-hub-nav-direction';
const HUB_NAV_SKIP_OVERLAY_KEY = 'fm-hub-nav-skip-overlay';

/** Rotte index degli hub — swipe abilitato solo su queste. */
export const HUB_INDEX_ROUTE_NAMES = new Set([
    'budgets.index',
    'debts-credits.index',
    'financial-goals.index',
    'tax-deductions.index',
    'patrimonio.index',
    'transactions.index',
    'accounts.index',
    'transfers.index',
    'inter-household-transfers.index',
    'investments.index',
    'investment-pacs.index',
    'asset-allocation.index',
    'investment-assets.index',
    'investment-analyses.index',
    'categories.index',
    'tags.index',
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

export function prefersReducedHubMotion(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export function setHubNavDirection(direction: HubNavDirection): void {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.setItem(HUB_NAV_DIRECTION_KEY, direction);
}

export function markHubNavVisit(): void {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.setItem(HUB_NAV_SKIP_OVERLAY_KEY, '1');
}

export function consumeHubNavSkipOverlay(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const shouldSkip = sessionStorage.getItem(HUB_NAV_SKIP_OVERLAY_KEY) === '1';
    sessionStorage.removeItem(HUB_NAV_SKIP_OVERLAY_KEY);

    return shouldSkip;
}

export function consumeHubNavDirection(): HubNavDirection | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const direction = sessionStorage.getItem(HUB_NAV_DIRECTION_KEY);

    sessionStorage.removeItem(HUB_NAV_DIRECTION_KEY);

    if (direction === 'next' || direction === 'prev') {
        return direction;
    }

    return null;
}

export function clearHubNavDirection(): void {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.removeItem(HUB_NAV_DIRECTION_KEY);
}

export function resolveHubNavDirection(
    tabs: SectionHubTab[],
    fromId: string,
    toId: string,
): HubNavDirection {
    const visible = getVisibleHubTabs(tabs);
    const fromIndex = visible.findIndex((tab) => tab.id === fromId);
    const toIndex = visible.findIndex((tab) => tab.id === toId);

    if (fromIndex === -1 || toIndex === -1) {
        return 'next';
    }

    return toIndex > fromIndex ? 'next' : 'prev';
}

export function visitHubTab(href: string, direction: HubNavDirection, onComplete?: () => void): void {
    setHubNavDirection(direction);
    markHubNavVisit();

    router.visit(href, {
        preserveScroll: false,
        onFinish: () => {
            clearHubNavDirection();
            onComplete?.();
        },
    });
}
