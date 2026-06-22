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
}

export default function SectionHubNav({ tabs, active, ariaLabel }: SectionHubNavProps) {
    const { plan } = usePage<PageProps>().props;
    const isProPlan = plan?.current === 'pro';

    const visibleTabs = tabs.filter((tab) => !tab.hidden);

    return (
        <nav
            className={clsx(
                'mb-3 flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 scrollbar-hide sm:mb-4',
                'dark:border-gray-700 dark:bg-gray-900',
            )}
            aria-label={ariaLabel}
        >
            {visibleTabs.map((tab) => {
                const isActive = tab.id === active;
                const lockedByPlan = tab.requiresPro && !isProPlan;
                const href = lockedByPlan && tab.moduleId
                    ? `${route('profile.subscription')}?from=${tab.moduleId}`
                    : route(tab.routeName);
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
                            'shrink-0 rounded-lg px-3 py-2.5 text-xs font-medium transition-colors min-h-10 sm:px-3 sm:py-2 sm:text-sm',
                            isActive
                                ? 'bg-emerald-600 text-white'
                                : lockedByPlan
                                  ? 'text-gray-400 hover:bg-gray-50 dark:text-gray-500 dark:hover:bg-gray-800'
                                  : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
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
        </nav>
    );
}
