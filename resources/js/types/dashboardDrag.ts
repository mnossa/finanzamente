import type { DOMAttributes, HTMLAttributes } from 'react';

/** Local drag types — keeps @dnd-kit off the dashboard static bundle. */
export type DashboardDragHandleAttributes = HTMLAttributes<HTMLElement>;
export type DashboardDragHandleListeners = DOMAttributes<HTMLElement>;

export interface DashboardDragEndEvent {
    active: { id: string | number };
    over: { id: string | number } | null;
}
