import {
    MOBILE_BOTTOM_NAV_DEFAULT_SLOTS,
    MOBILE_BOTTOM_NAV_DESTINATIONS,
    MOBILE_BOTTOM_NAV_SLOT_COUNT,
    type MobileBottomNavDestination,
    type MobileBottomNavDestinationId,
} from '@/config/mobileBottomNav';
import { useModules } from '@/hooks/useModules';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useSyncExternalStore } from 'react';

function isDestinationAvailable(
    destination: MobileBottomNavDestination,
    isModuleEnabled: (moduleId: string) => boolean,
    isProPlan: boolean,
): boolean {
    if (destination.requiresPro && !isProPlan) {
        return false;
    }

    if (destination.moduleId && !isModuleEnabled(destination.moduleId)) {
        return false;
    }

    return true;
}

export function resolveMobileBottomNavSlots(
    requestedIds: MobileBottomNavDestinationId[],
    isModuleEnabled: (moduleId: string) => boolean,
    isProPlan: boolean,
): MobileBottomNavDestination[] {
    const available = MOBILE_BOTTOM_NAV_DESTINATIONS.filter((destination) =>
        isDestinationAvailable(destination, isModuleEnabled, isProPlan),
    );

    const resolved: MobileBottomNavDestination[] = [];
    const used = new Set<MobileBottomNavDestinationId>();

    for (const id of requestedIds) {
        if (resolved.length >= MOBILE_BOTTOM_NAV_SLOT_COUNT) {
            break;
        }

        const destination = available.find((entry) => entry.id === id);

        if (destination && !used.has(destination.id)) {
            resolved.push(destination);
            used.add(destination.id);
        }
    }

    for (const destination of available) {
        if (resolved.length >= MOBILE_BOTTOM_NAV_SLOT_COUNT) {
            break;
        }

        if (!used.has(destination.id)) {
            resolved.push(destination);
            used.add(destination.id);
        }
    }

    return resolved.slice(0, MOBILE_BOTTOM_NAV_SLOT_COUNT);
}

let slotOverride: MobileBottomNavDestinationId[] | null = null;
const slotOverrideSubscribers = new Set<() => void>();

function subscribeToSlotOverride(callback: () => void): () => void {
    slotOverrideSubscribers.add(callback);

    return () => {
        slotOverrideSubscribers.delete(callback);
    };
}

function getSlotOverrideSnapshot(): MobileBottomNavDestinationId[] | null {
    return slotOverride;
}

export function setMobileBottomNavSlotsOverride(slots: MobileBottomNavDestinationId[] | null): void {
    slotOverride = slots;
    slotOverrideSubscribers.forEach((callback) => callback());
}

export function useMobileBottomNavSlots(): MobileBottomNavDestination[] {
    const { auth, plan } = usePage<PageProps>().props;
    const { isModuleEnabled } = useModules();
    const isProPlan = plan?.current === 'pro';
    const overrideSlots = useSyncExternalStore(
        subscribeToSlotOverride,
        getSlotOverrideSnapshot,
        getSlotOverrideSnapshot,
    );

    useEffect(() => {
        return router.on('success', () => {
            setMobileBottomNavSlotsOverride(null);
        });
    }, []);

    return useMemo(() => {
        const preferences = auth.user?.preferences;
        const rawSlots = preferences && typeof preferences === 'object'
            ? (preferences as Record<string, unknown>).mobile_bottom_nav
            : null;

        const requestedIds = overrideSlots ?? (Array.isArray(rawSlots)
            ? rawSlots.filter((value): value is MobileBottomNavDestinationId => typeof value === 'string')
            : MOBILE_BOTTOM_NAV_DEFAULT_SLOTS);

        return resolveMobileBottomNavSlots(requestedIds, isModuleEnabled, isProPlan);
    }, [auth.user?.preferences, isModuleEnabled, isProPlan, overrideSlots]);
}
