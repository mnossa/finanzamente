import {
    DndContext,
    closestCenter,
    PointerSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    rectSortingStrategy,
} from '@dnd-kit/sortable';
import SortableDashboardWidgetCard from '@/Components/Dashboard/SortableDashboardWidgetCard';
import type { DashboardDragEndEvent } from '@/types/dashboardDrag';
import type { WidgetId, WidgetSize } from '@/types/dashboard';
import type { DashboardWidgetGridItem } from '@/Components/Dashboard/DashboardWidgetGridStatic';

interface DashboardWidgetGridEditableProps {
    items: DashboardWidgetGridItem[];
    sortableIds: WidgetId[];
    onDragEnd: (event: DashboardDragEndEvent) => void;
    onToggleVisibility: (widgetId: WidgetId) => void;
    onChangeSize: (widgetId: WidgetId, size: WidgetSize) => void;
    onManageDelete?: (target: { id: number; name: string }) => void;
}

export default function DashboardWidgetGridEditable({
    items,
    sortableIds,
    onDragEnd,
    onToggleVisibility,
    onChangeSize,
    onManageDelete,
}: DashboardWidgetGridEditableProps) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 3 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const handleDragEnd = (event: DragEndEvent) => {
        onDragEnd({
            active: { id: event.active.id },
            over: event.over ? { id: event.over.id } : null,
        });
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
        >
            <SortableContext items={sortableIds} strategy={rectSortingStrategy}>
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4 xl:grid-cols-6 xl:gap-6">
                    {items.map(({ widget, content, formulaTitle, formulaNumericId, formulaCanDelete, renderable }) => {
                        if (!renderable) {
                            return null;
                        }
                        if (content === null) {
                            return null;
                        }

                        return (
                            <SortableDashboardWidgetCard
                                key={widget.id}
                                widget={widget}
                                isEditing
                                onToggleVisibility={() => onToggleVisibility(widget.id)}
                                onChangeSize={(size) => onChangeSize(widget.id, size)}
                                titleOverride={formulaTitle}
                                manageEditHref={
                                    formulaNumericId
                                        ? route('formula-widgets.edit', formulaNumericId)
                                        : undefined
                                }
                                onManageDelete={
                                    formulaNumericId && formulaCanDelete !== false && onManageDelete
                                        ? () => onManageDelete({
                                            id: Number(formulaNumericId),
                                            name: formulaTitle ?? 'Widget a formula',
                                        })
                                        : undefined
                                }
                            >
                                {content}
                            </SortableDashboardWidgetCard>
                        );
                    })}
                </div>
            </SortableContext>
        </DndContext>
    );
}
