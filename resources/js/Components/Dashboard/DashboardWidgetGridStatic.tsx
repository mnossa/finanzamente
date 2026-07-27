import DashboardWidgetCard from '@/Components/DashboardWidgetCard';
import type { WidgetConfig, WidgetId, WidgetSize } from '@/types/dashboard';
import type { ReactNode } from 'react';

export interface DashboardWidgetGridItem {
    widget: WidgetConfig;
    content: ReactNode;
    formulaTitle?: string;
    formulaNumericId: string | null;
    formulaCanDelete?: boolean;
    formulaCanEdit?: boolean;
    renderable: boolean;
}

interface DashboardWidgetGridStaticProps {
    items: DashboardWidgetGridItem[];
    isEditing: boolean;
    onToggleVisibility: (widgetId: WidgetId) => void;
    onChangeSize: (widgetId: WidgetId, size: WidgetSize) => void;
    onManageDelete?: (target: { id: number; name: string }) => void;
}

export default function DashboardWidgetGridStatic({
    items,
    isEditing,
    onToggleVisibility,
    onChangeSize,
    onManageDelete,
}: DashboardWidgetGridStaticProps) {
    return (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-4 xl:grid-cols-6 xl:gap-6">
            {items.map(({ widget, content, formulaTitle, formulaNumericId, formulaCanDelete, formulaCanEdit, renderable }) => {
                if (!renderable) {
                    return null;
                }
                if (!widget.visible && !isEditing) {
                    return null;
                }
                if (content === null && !isEditing) {
                    return null;
                }

                return (
                    <DashboardWidgetCard
                        key={widget.id}
                        widget={widget}
                        isEditing={isEditing}
                        onToggleVisibility={() => onToggleVisibility(widget.id)}
                        onChangeSize={(size) => onChangeSize(widget.id, size)}
                        titleOverride={formulaTitle}
                        manageEditHref={
                            isEditing && formulaNumericId && formulaCanEdit !== false
                                ? route('formula-widgets.edit', formulaNumericId)
                                : undefined
                        }
                        onManageDelete={
                            isEditing && formulaNumericId && formulaCanDelete !== false && onManageDelete
                                ? () => onManageDelete({
                                    id: Number(formulaNumericId),
                                    name: formulaTitle ?? 'Widget a formula',
                                })
                                : undefined
                        }
                    >
                        {content}
                    </DashboardWidgetCard>
                );
            })}
        </div>
    );
}
