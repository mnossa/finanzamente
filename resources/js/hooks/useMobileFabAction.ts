import { FM_MOBILE_FAB_ACTION_EVENT } from '@/utils/mobilePrimaryFab';
import { useEffect } from 'react';

export function useMobileFabAction(actionId: string, handler: () => void): void {
    useEffect(() => {
        const onFabAction = (event: Event) => {
            const detail = (event as CustomEvent<{ actionId?: string }>).detail;

            if (detail?.actionId === actionId) {
                handler();
            }
        };

        window.addEventListener(FM_MOBILE_FAB_ACTION_EVENT, onFabAction);

        return () => {
            window.removeEventListener(FM_MOBILE_FAB_ACTION_EVENT, onFabAction);
        };
    }, [actionId, handler]);
}
