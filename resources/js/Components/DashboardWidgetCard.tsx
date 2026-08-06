import React from 'react';
import clsx from 'clsx';
import type { DashboardDragHandleAttributes, DashboardDragHandleListeners } from '@/types/dashboardDrag';
import { WidgetConfig, WidgetSize } from '@/types/dashboard';
import { WIDGET_MAP } from '@/constants/widgetRegistry';

interface DashboardWidgetCardProps {
    widget: WidgetConfig;
    isEditing: boolean;
    onToggleVisibility: () => void;
    onChangeSize: (size: WidgetSize) => void;
    children: React.ReactNode;
    className?: string;
    titleOverride?: string;
    manageEditHref?: string;
    onManageDelete?: () => void;
    dragHandleAttributes?: DashboardDragHandleAttributes;
    dragHandleListeners?: DashboardDragHandleListeners;
}

const SIZE_LABELS: Record<WidgetSize, string> = {
    sm: 'S',
    md: 'M',
    lg: 'L',
    xl: 'XL',
};

const SIZE_COL_CLASSES: Record<WidgetSize, string> = {
    sm: 'col-span-full xl:col-span-2',
    md: 'col-span-full xl:col-span-3',
    lg: 'col-span-full xl:col-span-4',
    xl: 'col-span-full xl:col-span-6',
};

export default function DashboardWidgetCard({
    widget,
    isEditing,
    onToggleVisibility,
    onChangeSize,
    children,
    className,
    titleOverride,
    manageEditHref,
    onManageDelete,
    dragHandleAttributes,
    dragHandleListeners,
}: DashboardWidgetCardProps) {
    const definition = WIDGET_MAP[widget.id as keyof typeof WIDGET_MAP];
    const widgetTitle = titleOverride ?? definition?.title ?? widget.id;
    const allowedSizes = definition?.allowedSizes ?? ['sm', 'md', 'lg', 'xl'];
    const isSortableShell = dragHandleAttributes !== undefined;

    const cardBody = (
        <>
            {isEditing && (
                <div
                    className="mb-1 flex items-center justify-between rounded-lg bg-white px-2 py-1 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                    role="toolbar"
                    aria-label={`Controlli widget ${widgetTitle}`}
                >
                    <button
                        {...dragHandleAttributes}
                        {...dragHandleListeners}
                        type="button"
                        className="cursor-grab touch-none rounded p-1.5 text-gray-500 transition-colors hover:bg-emerald-50 hover:text-emerald-600 active:cursor-grabbing dark:text-gray-400 dark:hover:bg-emerald-900/20 dark:hover:text-emerald-400"
                        aria-label="Trascina per riordinare"
                        title="Trascina per riordinare"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            className="h-5 w-5"
                            aria-hidden="true"
                        >
                            <path d="M7 2a1 1 0 110 2 1 1 0 010-2zm6 0a1 1 0 110 2 1 1 0 010-2zM7 7a1 1 0 110 2 1 1 0 010-2zm6 0a1 1 0 110 2 1 1 0 010-2zM7 12a1 1 0 110 2 1 1 0 010-2zm6 0a1 1 0 110 2 1 1 0 010-2z" />
                        </svg>
                    </button>

                    <span className="flex-1 truncate px-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        {widgetTitle}
                    </span>

                    <div
                        className="mr-1 flex items-center gap-0.5"
                        role="group"
                        aria-label="Dimensione widget"
                    >
                        {allowedSizes.map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => onChangeSize(s)}
                                className={clsx(
                                    'rounded px-1.5 py-0.5 text-xs font-medium transition-colors',
                                    widget.size === s
                                        ? 'bg-emerald-500 text-white'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700',
                                )}
                                aria-pressed={widget.size === s}
                                aria-label={`Dimensione ${s.toUpperCase()}`}
                                title={`Dimensione ${s.toUpperCase()}`}
                            >
                                {SIZE_LABELS[s]}
                            </button>
                        ))}
                    </div>

                    {manageEditHref ? (
                        <a
                            href={manageEditHref}
                            className="rounded p-1 text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-primary-400"
                            aria-label={`Modifica ${widgetTitle}`}
                            title="Modifica widget"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4" aria-hidden="true">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                            </svg>
                        </a>
                    ) : null}
                    {onManageDelete ? (
                        <button
                            type="button"
                            onClick={onManageDelete}
                            className="rounded p-1 text-gray-500 transition-colors hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                            aria-label={`Elimina ${widgetTitle}`}
                            title="Elimina widget"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4" aria-hidden="true">
                                <path fillRule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clipRule="evenodd" />
                            </svg>
                        </button>
                    ) : null}
                    <button
                        type="button"
                        onClick={onToggleVisibility}
                        className={clsx(
                            'rounded p-1 transition-colors',
                            widget.visible
                                ? 'text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                                : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700',
                        )}
                        aria-pressed={widget.visible}
                        aria-label={widget.visible ? 'Nascondi widget' : 'Mostra widget'}
                        title={widget.visible ? 'Nascondi widget' : 'Mostra widget'}
                    >
                        {widget.visible ? (
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4" aria-hidden="true">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fillRule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clipRule="evenodd" />
                            </svg>
                        ) : (
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="h-4 w-4" aria-hidden="true">
                                <path fillRule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clipRule="evenodd" />
                                <path d="M10.748 13.93l2.523 2.524a10.045 10.045 0 01-3.27.542c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 012.136-3.386l1.517 1.517a2.5 2.5 0 00-.51.73 2.5 2.5 0 003.42 3.42l.735-.51.001-.001z" />
                            </svg>
                        )}
                    </button>
                </div>
            )}

            <div className={clsx('min-w-0 flex-1 [&>*]:h-full', !widget.visible && isEditing && 'pointer-events-none select-none')}>
                {children}
            </div>
        </>
    );

    if (isSortableShell) {
        return <>{cardBody}</>;
    }

    return (
        <div
            className={clsx(
                SIZE_COL_CLASSES[widget.size],
                'flex min-w-0 flex-col',
                isEditing && 'rounded-xl outline-dashed outline-2 outline-emerald-400/50 dark:outline-emerald-600/50',
                isEditing && !widget.visible && 'opacity-50',
                className,
            )}
        >
            {cardBody}
        </div>
    );
}

export function getWidgetColSpanClass(size: WidgetSize): string {
    return SIZE_COL_CLASSES[size];
}
