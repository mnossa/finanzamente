import type { SectionHubTab } from '@/Components/SectionHubNav';
import {
    getAdjacentHubTabHref,
    isHubIndexRoute,
    prefersReducedHubMotion,
    visitHubTab,
    type HubNavDirection,
} from '@/utils/sectionHubNav';
import { usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useEffect, useRef } from 'react';

const MIN_SWIPE_DISTANCE_PX = 60;
const MAX_VERTICAL_DRIFT_RATIO = 0.75;
const AXIS_LOCK_THRESHOLD_PX = 10;

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
    const gestureAxis = useRef<'horizontal' | 'vertical' | null>(null);
    const navigating = useRef(false);

    useEffect(() => {
        if (!enableSwipe || prefersReducedHubMotion() || !isMobileViewport()) {
            return;
        }

        const currentRoute = typeof route === 'function' ? route().current() : null;
        if (!isHubIndexRoute(typeof currentRoute === 'string' ? currentRoute : null)) {
            return;
        }

        const onTouchStart = (event: TouchEvent) => {
            gestureAxis.current = null;

            if (event.touches.length !== 1 || shouldIgnoreSwipeTarget(event.target)) {
                touchStart.current = null;
                return;
            }

            const touch = event.touches[0];
            touchStart.current = { x: touch.clientX, y: touch.clientY };
        };

        const onTouchMove = (event: TouchEvent) => {
            if (!touchStart.current || event.touches.length !== 1) {
                return;
            }

            const touch = event.touches[0];
            const deltaX = touch.clientX - touchStart.current.x;
            const deltaY = touch.clientY - touchStart.current.y;

            if (gestureAxis.current === null) {
                if (Math.abs(deltaX) < AXIS_LOCK_THRESHOLD_PX && Math.abs(deltaY) < AXIS_LOCK_THRESHOLD_PX) {
                    return;
                }

                gestureAxis.current = Math.abs(deltaY) > Math.abs(deltaX) ? 'vertical' : 'horizontal';
            }

            if (gestureAxis.current === 'vertical') {
                return;
            }
        };

        const onTouchEnd = (event: TouchEvent) => {
            if (gestureAxis.current === 'vertical') {
                touchStart.current = null;
                gestureAxis.current = null;
                return;
            }

            if (!touchStart.current || navigating.current || event.changedTouches.length !== 1) {
                touchStart.current = null;
                gestureAxis.current = null;
                return;
            }

            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - touchStart.current.x;
            const deltaY = touch.clientY - touchStart.current.y;
            touchStart.current = null;
            gestureAxis.current = null;

            if (Math.abs(deltaX) < MIN_SWIPE_DISTANCE_PX) {
                return;
            }

            if (Math.abs(deltaY) / Math.abs(deltaX) > MAX_VERTICAL_DRIFT_RATIO) {
                return;
            }

            const direction: HubNavDirection = deltaX < 0 ? 'next' : 'prev';
            const href = getAdjacentHubTabHref(tabs, activeId, direction, isProPlan);

            if (!href) {
                return;
            }

            navigating.current = true;
            visitHubTab(href, direction, () => {
                navigating.current = false;
            });
        };

        document.addEventListener('touchstart', onTouchStart, { passive: true });
        document.addEventListener('touchmove', onTouchMove, { passive: true });
        document.addEventListener('touchend', onTouchEnd, { passive: true });

        return () => {
            document.removeEventListener('touchstart', onTouchStart);
            document.removeEventListener('touchmove', onTouchMove);
            document.removeEventListener('touchend', onTouchEnd);
        };
    }, [tabs, activeId, enableSwipe, isProPlan]);
}
