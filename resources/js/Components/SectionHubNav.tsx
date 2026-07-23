import { useHubSwipeNavigation } from '@/hooks/useHubSwipeNavigation';
import {
    resolveHubNavDirection,
    resolveSectionHubTabHref,
    visitHubTab,
} from '@/utils/sectionHubNav';
import { renderHubTabIcon, type HubTabIconId } from '@/utils/hubTabIcons';
import { Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { PageProps } from '@/types';
import { useCallback, useLayoutEffect, useRef, type MouseEvent } from 'react';

export type SectionHubTab = {
    id: string;
    label: string;
    mobileLabel?: string;
    icon?: HubTabIconId;
    routeName: string;
    /** Nasconde il tab */
    hidden?: boolean;
    requiresPro?: boolean;
    moduleId?: string;
};

interface SectionHubNavProps {
    tabs: SectionHubTab[];
    active: string;
    ariaLabel: string;
    /** Abilita swipe tra tab su mobile (solo pagine index). Default true. */
    enableSwipe?: boolean;
}

function resolveActiveTabElement(
    container: HTMLElement,
    tabRefs: Record<string, HTMLAnchorElement | null>,
    activeId: string,
): HTMLElement | null {
    const fromRef = tabRefs[activeId];

    if (fromRef) {
        return fromRef;
    }

    const fromAria = container.querySelector<HTMLElement>('[aria-current="page"]');

    return fromAria;
}

function positionIndicator(
    container: HTMLElement,
    activeTab: HTMLElement,
    indicator: HTMLElement,
    animate: boolean,
): void {
    const left = activeTab.offsetLeft - container.scrollLeft;
    const width = activeTab.offsetWidth;

    if (!animate) {
        indicator.style.transition = 'none';
    }

    indicator.style.width = `${width}px`;
    indicator.style.transform = `translateX(${left}px)`;

    if (!animate) {
        void indicator.offsetWidth;
        indicator.style.transition = '';
    }
}

function scrollActiveTabIntoView(container: HTMLElement, activeTab: HTMLElement): void {
    const tabLeft = activeTab.offsetLeft;
    const tabWidth = activeTab.offsetWidth;
    const maxScroll = Math.max(0, container.scrollWidth - container.clientWidth);
    const targetScroll = tabLeft - (container.clientWidth - tabWidth) / 2;

    container.scrollTo({
        left: Math.min(maxScroll, Math.max(0, targetScroll)),
        behavior: 'auto',
    });
}

export default function SectionHubNav({ tabs, active, ariaLabel, enableSwipe = true }: SectionHubNavProps) {
    const { plan } = usePage<PageProps>().props;
    const isProPlan = plan?.current === 'pro';

    const visibleTabs = tabs.filter((tab) => !tab.hidden);
    const scrollRef = useRef<HTMLDivElement>(null);
    const tabRefs = useRef<Record<string, HTMLAnchorElement | null>>({});
    const indicatorRef = useRef<HTMLSpanElement>(null);

    useHubSwipeNavigation({ tabs, activeId: active, enableSwipe });

    const syncIndicator = useCallback((animate = true) => {
        const container = scrollRef.current;
        const indicator = indicatorRef.current;

        if (!container || !indicator) {
            return;
        }

        const activeTab = resolveActiveTabElement(container, tabRefs.current, active);

        if (!activeTab) {
            return;
        }

        positionIndicator(container, activeTab, indicator, animate);
    }, [active]);

    const syncLayout = useCallback((animate = false) => {
        const container = scrollRef.current;

        if (!container) {
            return;
        }

        const activeTab = resolveActiveTabElement(container, tabRefs.current, active);

        if (!activeTab) {
            return;
        }

        scrollActiveTabIntoView(container, activeTab);
        syncIndicator(animate);
    }, [active, syncIndicator]);

    useLayoutEffect(() => {
        const container = scrollRef.current;

        syncLayout(false);

        const raf1 = window.requestAnimationFrame(() => {
            syncLayout(false);
        });
        const raf2 = window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                syncLayout(false);
            });
        });

        if (!container) {
            return () => {
                window.cancelAnimationFrame(raf1);
                window.cancelAnimationFrame(raf2);
            };
        }

        const onScroll = () => syncIndicator(true);
        const resizeObserver = new ResizeObserver(() => syncLayout(false));

        resizeObserver.observe(container);
        container.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);

        return () => {
            window.cancelAnimationFrame(raf1);
            window.cancelAnimationFrame(raf2);
            resizeObserver.disconnect();
            container.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, [active, syncIndicator, syncLayout, visibleTabs.length]);

    const handleTabClick = (
        event: MouseEvent<Element>,
        tab: SectionHubTab,
        href: string,
    ) => {
        if (tab.id === active) {
            return;
        }

        event.preventDefault();
        visitHubTab(href, resolveHubNavDirection(visibleTabs, active, tab.id));
    };

    return (
        <nav
            className="relative mb-2 border-b border-gray-200 dark:border-gray-700 sm:mb-4"
            aria-label={ariaLabel}
        >
            <div
                ref={scrollRef}
                className="-mb-px flex gap-0.5 overflow-x-auto scrollbar-hide sm:gap-1"
                data-horizontal-scroll
            >
                {visibleTabs.map((tab) => {
                    const isActive = tab.id === active;
                    const lockedByPlan = tab.requiresPro && !isProPlan;
                    const href = resolveSectionHubTabHref(tab, isProPlan);
                    const label = tab.mobileLabel ? (
                        <>
                            <span className="sm:hidden">{tab.mobileLabel}</span>
                            <span className="hidden sm:inline">{tab.label}</span>
                        </>
                    ) : (
                        tab.label
                    );

                    return (
                        <Link
                            key={tab.id}
                            ref={(element) => {
                                tabRefs.current[tab.id] = element as HTMLAnchorElement | null;
                            }}
                            href={href}
                            onClick={(event) => handleTabClick(event, tab, href)}
                            className={clsx(
                                'flex shrink-0 items-center gap-1 border-b-2 border-transparent px-2.5 py-2 text-[11px] font-medium min-h-9 sm:gap-1.5 sm:px-4 sm:py-2.5 sm:text-sm sm:min-h-10',
                                'transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2',
                                isActive
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : lockedByPlan
                                      ? 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'
                                      : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200',
                            )}
                            aria-current={isActive ? 'page' : undefined}
                        >
                            <span className="[&>svg]:h-3.5 [&>svg]:w-3.5 sm:[&>svg]:h-4 sm:[&>svg]:w-4">
                                {renderHubTabIcon(tab.icon)}
                            </span>
                            <span>{label}</span>
                            {lockedByPlan && (
                                <span className="text-[10px] font-semibold uppercase text-amber-500">Pro</span>
                            )}
                        </Link>
                    );
                })}
            </div>

            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 overflow-hidden"
            >
                <span
                    ref={indicatorRef}
                    data-testid="hub-tab-indicator"
                    className="absolute bottom-0 left-0 block h-full rounded-full bg-emerald-600 transition-[transform,width] duration-200 ease-out will-change-transform dark:bg-emerald-400"
                />
            </div>
        </nav>
    );
}
