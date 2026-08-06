import { useEffect, useState, type ComponentType } from 'react';
import type { DashboardDragEndEvent } from '@/types/dashboardDrag';
import type { WidgetId, WidgetSize } from '@/types/dashboard';
import type { DashboardWidgetGridItem } from '@/Components/Dashboard/DashboardWidgetGridStatic';

interface DashboardEditGridLoaderProps {
    items: DashboardWidgetGridItem[];
    sortableIds: WidgetId[];
    onDragEnd: (event: DashboardDragEndEvent) => void;
    onToggleVisibility: (widgetId: WidgetId) => void;
    onChangeSize: (widgetId: WidgetId, size: WidgetSize) => void;
    onManageDelete?: (target: { id: number; name: string }) => void;
    fallback: React.ReactNode;
}

type EditableGridComponent = ComponentType<Omit<DashboardEditGridLoaderProps, 'fallback'>>;

/**
 * Loads @dnd-kit only after mount (edit mode). Avoids React.lazy so vendor-dnd
 * is not pulled into the dashboard critical path at parse time.
 */
export default function DashboardEditGridLoader({
    fallback,
    ...props
}: DashboardEditGridLoaderProps) {
    const [EditGrid, setEditGrid] = useState<EditableGridComponent | null>(null);

    useEffect(() => {
        let cancelled = false;

        import('@/Components/Dashboard/DashboardWidgetGridEditable').then((module) => {
            if (!cancelled) {
                setEditGrid(() => module.default);
            }
        });

        return () => {
            cancelled = true;
        };
    }, []);

    if (!EditGrid) {
        return <>{fallback}</>;
    }

    return <EditGrid {...props} />;
}
