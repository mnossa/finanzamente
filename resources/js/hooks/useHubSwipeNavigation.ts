import type { SectionHubTab } from '@/Components/SectionHubNav';
import { getAdjacentHubTabHref, isHubIndexRoute } from '@/utils/sectionHubNav';
import { router, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useEffect, useRef } from 'react';

const MIN_SWIPE_DISTANCE_PX = 60;
const MAX_VERTICAL_DRIFT_RATIO = 0.75;

function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function isMobileViewport(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches;
}

function shouldIgnoreSwipeTarget(target: EventTarget | null): boolean {
    if (!(target instanceof Element)) {
        return false;
    }

    const interactive = target.closest(
        'input, textarea, select, button, a, [contenteditable="true"], [data-no-hub-swipe]',
    );

    if (interactive) {
        return true;
    }

    const horizontalScroll = target.closest('[data-horizontal-scroll]');

    return horizontalScroll instanceof HTMLElement && horizontalScroll.scrollWidth > horizontalScroll.clientWidth;
}

interface UseHubSwipeNavigationOptions {
    tabs: SectionHubTab[];
    activeId: string;
    enableSwipe?: boolean;
}

/**
 * Swipe orizzontale tra tab hub (solo pagine index, solo mobile).
 */
export function useHubSwipeNavigation({
    tabs,
    activeId,
    enableSwipe = true,
}: UseHubSwipeNavigationOptions): void {
    const { plan } = usePage<PageProps>().props;
    const isProPlan = plan?.current === 'pro';
    const touchStart = useRef<{ x: number; y: number } | null>(null);
    const navigating = useRef(false);

    useEffect(() => {
        if (!enableSwipe || prefersReducedMotion() || !isMobileViewport()) {
            return;
        }

        const currentRoute = typeof route === 'function' ? route().current() : null;
        if (!isHubIndexRoute(typeof currentRoute === 'string' ? currentRoute : null)) {
            return;
        }

        const onTouchStart = (event: TouchEvent) => {
            if (event.touches.length !== 1 || shouldIgnoreSwipeTarget(event.target)) {
                touchStart.current = null;
                return;
            }

            const touch = event.touches[0];
            touchStart.current = { x: touch.clientX, y: touch.clientY };
        };

        const onTouchEnd = (event: TouchEvent) => {
            if (!touchStart.current || navigating.current || event.changedTouches.length !== 1) {
                touchStart.current = null;
                return;
            }

            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - touchStart.current.x;
            const deltaY = touch.clientY - touchStart.current.y;
            touchStart.current = null;

            if (Math.abs(deltaX) < MIN_SWIPE_DISTANCE_PX) {
                return;
            }

            if (Math.abs(deltaY) / Math.abs(deltaX) > MAX_VERTICAL_DRIFT_RATIO) {
                return;
            }

            const direction = deltaX < 0 ? 'next' : 'prev';
            const href = getAdjacentHubTabHref(tabs, activeId, direction, isProPlan);

            if (!href) {
                return;
            }

            navigating.current = true;
            router.visit(href, {
                preserveScroll: false,
                onFinish: () => {
                    navigating.current = false;
                },
            });
        };

        document.addEventListener('touchstart', onTouchStart, { passive: true });
        document.addEventListener('touchend', onTouchEnd, { passive: true });

        return () => {
            document.removeEventListener('touchstart', onTouchStart);
            document.removeEventListener('touchend', onTouchEnd);
        };
    }, [tabs, activeId, enableSwipe, isProPlan]);
}
