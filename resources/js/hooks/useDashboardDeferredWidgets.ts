import axios from 'axios';
import { useEffect, useState } from 'react';
import {
    type DashboardDeferredWidgetsData,
    emptyDeferredWidgetsData,
} from '@/utils/dashboardDeferredDefaults';

export function useDashboardDeferredWidgets() {
    const [data, setData] = useState<DashboardDeferredWidgetsData>(emptyDeferredWidgetsData);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        axios
            .get<DashboardDeferredWidgetsData>(route('dashboard.deferred-widgets'), {
                signal: controller.signal,
            })
            .then((response) => {
                setData(response.data);
                setError(null);
            })
            .catch((err) => {
                if (axios.isCancel(err)) {
                    return;
                }

                setError('Non sono riuscito a caricare alcuni widget della dashboard. Riprova tra poco.');
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, []);

    return { data, loading, error };
}
