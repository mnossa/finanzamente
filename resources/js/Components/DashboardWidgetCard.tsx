import React from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import clsx from 'clsx';
import { WidgetConfig, WidgetSize } from '@/types/dashboard';
import { WIDGET_MAP } from '@/constants/widgetRegistry';

interface DashboardWidgetCardProps {
    widget: WidgetConfig;
    isEditing: boolean;
    onToggleVisibility: () => void;
    onChangeSize: (size: WidgetSize) => void;
    children: React.ReactNode;
    className?: string;
}

const SIZE_LABELS: Record<WidgetSize, string> = {
    sm: 'S',
    md: 'M',
    lg: 'L',
    xl: 'XL',
};

const SIZE_COL_CLASSES: Record<WidgetSize, string> = {
    sm: 'col-span-1',
    md: 'col-span-1',
    lg: 'col-span-1 lg:col-span-2',
    xl: 'col-span-1 lg:col-span-2',
};

/**
 * Wrapper draggable per ogni widget della dashboard.
 *
 * In modalità modifica mostra:
 * - maniglia di trascinamento
 * - pulsante di visibilità (mostra/nascondi)
 * - selettore dimensione (tra quelle consentite)
 * - overlay semitrasparente sui widget nascosti
 */
export default function DashboardWidgetCard({
    widget,
    isEditing,
    onToggleVisibility,
    onChangeSize,
    children,
    className,
}: DashboardWidgetCardProps) {
    const definition = WIDGET_MAP[widget.id];
    const allowedSizes = definition?.allowedSizes ?? ['sm', 'md', 'lg', 'xl'];

    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: widget.id, disabled: !isEditing });

    const style: React.CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 50 : undefined,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={clsx(
                SIZE_COL_CLASSES[widget.size],
                isDragging && 'opacity-70 shadow-2xl ring-2 ring-emerald-400',
                !widget.visible && isEditing && 'opacity-50',
                isEditing && 'relative',
                className
            )}
        >
            {/* Barra di controllo in modalità modifica */}
            {isEditing && (
                <div
                    className="mb-1 flex items-center justify-between rounded-lg bg-white px-2 py-1 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                    role="toolbar"
                    aria-label={`Controlli widget ${definition?.title ?? widget.id}`}
                >
                    {/* Maniglia drag */}
                    <button
                        {...attributes}
                        {...listeners}
                        type="button"
                        className="cursor-grab touch-none rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 active:cursor-grabbing dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        aria-label="Trascina per riordinare"
                        title="Trascina per riordinare"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            className="h-4 w-4"
                            aria-hidden="true"
                        >
                            <path d="M7 2a1 1 0 011 1v1h4V3a1 1 0 112 0v1h1a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2h1V3a1 1 0 011-1zm0 5a1 1 0 000 2h6a1 1 0 100-2H7z" />
                        </svg>
                    </button>

                    {/* Nome widget */}
                    <span className="flex-1 truncate px-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        {definition?.title ?? widget.id}
                    </span>

                    {/* Selettore dimensione */}
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
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'
                                )}
                                aria-pressed={widget.size === s}
                                aria-label={`Dimensione ${s.toUpperCase()}`}
                                title={`Dimensione ${s.toUpperCase()}`}
                            >
                                {SIZE_LABELS[s]}
                            </button>
                        ))}
                    </div>

                    {/* Toggle visibilità */}
                    <button
                        type="button"
                        onClick={onToggleVisibility}
                        className={clsx(
                            'rounded p-1 transition-colors',
                            widget.visible
                                ? 'text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'
                                : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
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

            {/* Contenuto del widget */}
            <div className={clsx(!widget.visible && isEditing && 'pointer-events-none select-none')}>
                {children}
            </div>
        </div>
    );
}

/** Restituisce la classe CSS col-span per un widget in base alla sua dimensione. */
export function getWidgetColSpanClass(size: WidgetSize): string {
    return SIZE_COL_CLASSES[size];
}
