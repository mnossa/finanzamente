import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

/**
 * Hook per chiedere al server il rate di cambio "1 unità di `from` → `to`"
 * alla data indicata, con debounce e gestione cancellazione richieste.
 *
 * Endpoint: GET /transazioni/anteprima-cambio
 * Sfrutta la cache `exchange_rates` lato server e Frankfurter solo in cache miss.
 */
export interface FxPreviewState {
    rate: number | null;
    source: 'identity' | 'exchange_rates' | null;
    effectiveDate: string | null;
    isLoading: boolean;
    error: string | null;
}

const INITIAL: FxPreviewState = {
    rate: null,
    source: null,
    effectiveDate: null,
    isLoading: false,
    error: null,
};

interface UseFxPreviewOptions {
    enabled: boolean;
    from: string;
    to: string;
    date: string;
    debounceMs?: number;
}

export function useFxPreview({ enabled, from, to, date, debounceMs = 300 }: UseFxPreviewOptions): FxPreviewState {
    const [state, setState] = useState<FxPreviewState>(INITIAL);
    const requestIdRef = useRef(0);

    useEffect(() => {
        // Disabilitato o input incompleti → reset
        if (!enabled || !from || !to || from.length !== 3 || to.length !== 3) {
            setState(INITIAL);
            return;
        }

        // Stessa valuta → rate identity, niente request
        if (from === to) {
            setState({ rate: 1, source: 'identity', effectiveDate: date, isLoading: false, error: null });
            return;
        }

        const currentId = ++requestIdRef.current;
        const timeoutId = window.setTimeout(() => {
            setState((prev) => ({ ...prev, isLoading: true, error: null }));

            axios
                .get(route('transactions.fx-preview'), { params: { from, to, date } })
                .then((response) => {
                    // Scarta risposte stantie per richieste superate da una più recente
                    if (currentId !== requestIdRef.current) return;
                    setState({
                        rate: response.data.rate,
                        source: response.data.source,
                        effectiveDate: response.data.date,
                        isLoading: false,
                        error: null,
                    });
                })
                .catch((error) => {
                    if (currentId !== requestIdRef.current) return;
                    setState({
                        rate: null,
                        source: null,
                        effectiveDate: null,
                        isLoading: false,
                        error: error.response?.data?.message ?? 'Anteprima cambio non disponibile',
                    });
                });
        }, debounceMs);

        return () => window.clearTimeout(timeoutId);
    }, [enabled, from, to, date, debounceMs]);

    return state;
}
