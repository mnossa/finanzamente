import ContentPanelShell from '@/Components/ContentPanelShell';
import {
    contentPanelBodyClass,
    contentPanelEmptyClass,
    contentPanelHeaderClass,
    contentPanelListBodyClass,
} from '@/Components/IndexPageListToolbars';
import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import React from 'react';

export const dashboardWidgetHeaderClass = clsx(
    contentPanelHeaderClass,
    'flex items-center justify-between gap-3 sm:py-3',
);
export const dashboardWidgetBodyClass = contentPanelBodyClass;
export const dashboardWidgetListBodyClass = contentPanelListBodyClass;
export const dashboardWidgetEmptyClass = contentPanelEmptyClass;

const detailLinkClass =
    'shrink-0 whitespace-nowrap text-xs font-medium text-emerald-700 hover:text-emerald-800 sm:text-sm dark:text-emerald-300 dark:hover:text-emerald-200';

interface DashboardWidgetShellProps {
    title?: string;
    subtitle?: string;
    titleBadge?: React.ReactNode;
    detailHref?: string;
    detailLabel?: string;
    headerActions?: React.ReactNode;
    /** Header personalizzato (es. widget con toggle mobile/desktop). */
    header?: React.ReactNode;
    bodyClassName?: string;
    className?: string;
    children: React.ReactNode;
}

export default function DashboardWidgetShell({
    title,
    subtitle,
    titleBadge,
    detailHref,
    detailLabel = 'Dettagli',
    headerActions,
    header,
    bodyClassName,
    className,
    children,
}: DashboardWidgetShellProps) {
    const defaultHeader = (
        <div className={dashboardWidgetHeaderClass}>
            <div className="min-w-0">
                <div className="flex min-w-0 items-center gap-2">
                    <h3 className="truncate text-base font-semibold text-gray-900 sm:text-[15px] dark:text-white">
                        {title}
                    </h3>
                    {titleBadge}
                </div>
                {subtitle ? (
                    <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{subtitle}</p>
                ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {headerActions}
                {detailHref ? (
                    <Link href={detailHref} className={detailLinkClass}>
                        {detailLabel} →
                    </Link>
                ) : null}
            </div>
        </div>
    );

    return (
        <ContentPanelShell
            variant="dashboard"
            header={header ?? defaultHeader}
            bodyClassName={bodyClassName ?? dashboardWidgetBodyClass}
            className={className}
        >
            {children}
        </ContentPanelShell>
    );
}

/** Toggle a segmenti per header widget (mobile full-width, desktop inline). */
export function DashboardWidgetSegmentedControl<T extends string>({
    value,
    options,
    onChange,
    ariaLabel,
    className,
}: {
    value: T;
    options: Array<{ value: T; label: string }>;
    onChange: (value: T) => void;
    ariaLabel: string;
    className?: string;
}) {
    return (
        <div
            className={clsx(
                'grid gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-700/60',
                options.length === 2 ? 'grid-cols-2' : `grid-cols-${options.length}`,
                className,
            )}
            role="tablist"
            aria-label={ariaLabel}
        >
            {options.map((option) => {
                const isActive = value === option.value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        onClick={() => onChange(option.value)}
                        className={clsx(
                            'min-h-10 rounded-md text-sm font-medium transition-colors sm:min-h-0 sm:rounded-md sm:px-2.5 sm:py-1 sm:text-xs',
                            isActive
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white sm:bg-emerald-100 sm:text-emerald-800 sm:shadow-none dark:sm:bg-emerald-900/40 dark:sm:text-emerald-300'
                                : 'text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white sm:text-gray-500 sm:hover:bg-gray-100 dark:sm:text-gray-400 dark:sm:hover:bg-gray-700',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
