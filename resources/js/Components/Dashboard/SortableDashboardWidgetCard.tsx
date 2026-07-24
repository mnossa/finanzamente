import React from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import type { DashboardDragHandleAttributes, DashboardDragHandleListeners } from '@/types/dashboardDrag';
import clsx from 'clsx';
import DashboardWidgetCard from '@/Components/DashboardWidgetCard';
import { WidgetConfig, WidgetSize } from '@/types/dashboard';
import { getWidgetColSpanClass } from '@/Components/DashboardWidgetCard';

interface SortableDashboardWidgetCardProps {
    widget: WidgetConfig;
    isEditing: boolean;
    onToggleVisibility: () => void;
    onChangeSize: (size: WidgetSize) => void;
    children: React.ReactNode;
    className?: string;
    titleOverride?: string;
    manageEditHref?: string;
    onManageDelete?: () => void;
}

export default function SortableDashboardWidgetCard({
    widget,
    isEditing,
    onToggleVisibility,
    onChangeSize,
    children,
    className,
    titleOverride,
    manageEditHref,
    onManageDelete,
}: SortableDashboardWidgetCardProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: widget.id, disabled: !isEditing });

    const style: React.CSSProperties = {
        transform: isDragging
            ? `${CSS.Transform.toString(transform)} scale(1.02)`
            : CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 50 : undefined,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={clsx(
                getWidgetColSpanClass(widget.size),
                'flex min-w-0 flex-col',
                isDragging && 'opacity-90 shadow-2xl ring-2 ring-emerald-400 rounded-xl',
                isEditing && !isDragging && 'rounded-xl outline-dashed outline-2 outline-emerald-400/50 dark:outline-emerald-600/50',
                isEditing && !widget.visible && 'opacity-50',
                className,
            )}
        >
            <DashboardWidgetCard
                widget={widget}
                isEditing={isEditing}
                onToggleVisibility={onToggleVisibility}
                onChangeSize={onChangeSize}
                titleOverride={titleOverride}
                manageEditHref={manageEditHref}
                onManageDelete={onManageDelete}
                dragHandleAttributes={attributes as DashboardDragHandleAttributes}
                dragHandleListeners={listeners as DashboardDragHandleListeners}
            >
                {children}
            </DashboardWidgetCard>
        </div>
    );
}
