import { useHubSwipeNavigation } from '@/hooks/useHubSwipeNavigation';
import { resolveSectionHubTabHref } from '@/utils/sectionHubNav';
import { Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { PageProps } from '@/types';

export type SectionHubTab = {
    id: string;
    label: string;
    mobileLabel?: string;
    routeName: string;
    /** Nasconde il tab (es. sessione rapida disabilitata) */
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

export default function SectionHubNav({ tabs, active, ariaLabel, enableSwipe = true }: SectionHubNavProps) {
    const { plan } = usePage<PageProps>().props;
    const isProPlan = plan?.current === 'pro';

    const visibleTabs = tabs.filter((tab) => !tab.hidden);

    useHubSwipeNavigation({ tabs, activeId: active, enableSwipe });

    return (
        <nav
            className={clsx(
                'relative mb-3 border-b border-gray-200 dark:border-gray-700 sm:mb-4',
            )}
            aria-label={ariaLabel}
        >
            <div
                className="-mb-px flex gap-1 overflow-x-auto scrollbar-hide"
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
                            href={href}
                            className={clsx(
                                'shrink-0 border-b-2 px-3 py-2.5 text-xs font-medium transition-colors min-h-10 sm:px-4 sm:text-sm',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2',
                                isActive
                                    ? 'border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400'
                                    : lockedByPlan
                                      ? 'border-transparent text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'
                                      : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200',
                            )}
                            aria-current={isActive ? 'page' : undefined}
                        >
                            {label}
                            {lockedByPlan && (
                                <span className="ml-1 text-[10px] font-semibold uppercase text-amber-500">Pro</span>
                            )}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
