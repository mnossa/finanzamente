import axios from 'axios';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import type { AppNotification } from '@/types';

interface SharedNotifications {
    deferred?: boolean;
    unread_count: number;
    items: AppNotification[];
}

interface HeaderNotificationsState {
    unread_count: number;
    items: AppNotification[];
}

export function useHeaderNotifications(shared: SharedNotifications) {
    const [notifications, setNotifications] = useState<HeaderNotificationsState>({
        unread_count: shared.unread_count,
        items: shared.items,
    });
    const [loading, setLoading] = useState(Boolean(shared.deferred));

    const refresh = useCallback(async () => {
        try {
            const response = await axios.get<HeaderNotificationsState>(route('notifications.header'));
            setNotifications(response.data);
        } catch {
            // Mantiene lo stato precedente in caso di errore transitorio.
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!shared.deferred) {
            setNotifications({
                unread_count: shared.unread_count,
                items: shared.items,
            });
            setLoading(false);

            return;
        }

        void refresh();
    }, [shared.deferred, shared.unread_count, shared.items, refresh]);

    useEffect(() => {
        const unregisterSuccess = router.on('success', () => {
            if (shared.deferred) {
                void refresh();
            }
        });

        return unregisterSuccess;
    }, [refresh, shared.deferred]);

    return { notifications, loading, refresh };
}
