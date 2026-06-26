import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import type { DashboardLayoutConfig } from '@/types/dashboard';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';
import { isFormulaWidgetId, parseFormulaWidgetNumericId } from '@/types/formulaWidget';
import {
    FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS,
    clearFormulaWidgetPayloadCacheForHousehold,
    isFormulaWidgetPayloadCacheFresh,
    isFormulaWidgetPayloadCacheValidForVersion,
    readFormulaWidgetPayloadCache,
    writeFormulaWidgetPayloadCache,
} from '@/utils/formulaWidgetPayloadCache';

function visibleFormulaWidgetIds(layout: DashboardLayoutConfig): string[] {
    return [...(layout.widgets ?? [])]
        .sort((a, b) => a.position - b.position)
        .filter((widget) => widget.visible && isFormulaWidgetId(widget.id))
        .map((widget) => parseFormulaWidgetNumericId(widget.id))
        .filter((id): id is string => id !== null);
}

function runtimeParamsByWidgetId(layout: DashboardLayoutConfig): Record<string, Record<string, string>> {
    const map: Record<string, Record<string, string>> = {};

    for (const widget of layout.widgets ?? []) {
        if (!widget.visible || !isFormulaWidgetId(widget.id)) {
            continue;
        }

        const numericId = parseFormulaWidgetNumericId(widget.id);
        if (!numericId || !widget.runtime_params || Object.keys(widget.runtime_params).length === 0) {
            continue;
        }

        map[numericId] = widget.runtime_params;
    }

    return map;
}

/** Firma stabile dei parametri runtime di un singolo widget (ordine-indipendente). */
function widgetParamsSignature(params?: Record<string, string>): string {
    if (!params) {
        return '';
    }

    return Object.entries(params)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([key, value]) => `${key}=${value}`)
        .join(',');
}

function runtimeParamsCacheSuffix(paramsByWidgetId: Record<string, Record<string, string>>, widgetIds: string[]): string {
    const parts: string[] = [];

    for (const widgetId of widgetIds) {
        const params = paramsByWidgetId[widgetId];
        if (!params) {
            continue;
        }

        parts.push(`${widgetId}:${widgetParamsSignature(params)}`);
    }

    return parts.join(';');
}

function widgetStoreKey(widgetId: string, signature: string): string {
    return `${widgetId}|${signature}`;
}

function extractEtag(headers: Record<string, unknown>): string | null {
    const raw = headers.etag ?? headers.ETag;
    if (typeof raw !== 'string' || raw === '') {
        return null;
    }

    return raw.replace(/^W\//, '').replace(/^"|"$/g, '');
}

function extractDataVersion(
    headers: Record<string, unknown>,
    body?: { dataVersion?: string | null },
): string | null {
    const headerValue = headers['x-formula-widget-data-version'] ?? headers['X-Formula-Widget-Data-Version'];
    if (typeof headerValue === 'string' && headerValue !== '') {
        return headerValue;
    }

    if (typeof body?.dataVersion === 'string' && body.dataVersion !== '') {
        return body.dataVersion;
    }

    return null;
}

function samePayloadMap(
    a: Record<string, FormulaWidgetPayload>,
    b: Record<string, FormulaWidgetPayload>,
): boolean {
    const keysA = Object.keys(a);
    const keysB = Object.keys(b);
    if (keysA.length !== keysB.length) {
        return false;
    }

    return keysA.every((key) => a[key] === b[key]);
}

async function fetchFormulaWidgetPayloads(
    widgetIds: string[],
    runtimeParams: Record<string, Record<string, string>>,
    etag: string | null,
    signal: AbortSignal,
    useConditionalRequest: boolean,
): Promise<{
    payloads: Record<string, FormulaWidgetPayload>;
    etag: string | null;
    dataVersion: string | null;
    notModified: boolean;
}> {
    const headers: Record<string, string> = {};
    if (useConditionalRequest && etag) {
        headers['If-None-Match'] = etag.startsWith('"') ? etag : `"${etag}"`;
    }

    const response = await axios.get<{ payloads: Record<string, FormulaWidgetPayload>; dataVersion?: string }>(
        route('dashboard.formula-widget-payloads', {
            ids: widgetIds.join(','),
            params: Object.fromEntries(
                widgetIds
                    .filter((widgetId) => runtimeParams[widgetId])
                    .map((widgetId) => [widgetId, runtimeParams[widgetId]]),
            ),
        }),
        {
            signal,
            headers,
            validateStatus: (status) => status === 200 || status === 304,
        },
    );

    if (response.status === 304) {
        return {
            payloads: {},
            etag,
            dataVersion: extractDataVersion(response.headers as Record<string, unknown>),
            notModified: true,
        };
    }

    return {
        payloads: response.data.payloads ?? {},
        etag: extractEtag(response.headers as Record<string, unknown>),
        dataVersion: extractDataVersion(response.headers as Record<string, unknown>, response.data),
        notModified: false,
    };
}

interface WidgetPayloadStore {
    householdId: number | null;
    dataVersion: string | null;
    etag: string | null;
    map: Record<string, FormulaWidgetPayload>;
}

export function useDashboardFormulaPayloads(
    dashboardLayout: DashboardLayoutConfig,
    initialPayloads: Record<string, FormulaWidgetPayload> = {},
    initialDataVersion: string | null = null,
) {
    const { activeHousehold, formulaWidgetDataVersion } = usePage<PageProps>().props;
    const householdId = activeHousehold?.id ?? null;
    const dataVersion = initialDataVersion ?? formulaWidgetDataVersion ?? null;

    const widgetIds = useMemo(() => visibleFormulaWidgetIds(dashboardLayout), [dashboardLayout.widgets]);
    const widgetIdsKey = widgetIds.join(',');
    const runtimeParams = useMemo(() => runtimeParamsByWidgetId(dashboardLayout), [dashboardLayout.widgets]);

    const signatureByWidgetId = useMemo(() => {
        const map: Record<string, string> = {};
        for (const id of widgetIds) {
            map[id] = widgetParamsSignature(runtimeParams[id]);
        }

        return map;
    }, [runtimeParams, widgetIdsKey]);

    const runtimeParamsKey = useMemo(
        () => runtimeParamsCacheSuffix(runtimeParams, widgetIds),
        [runtimeParams, widgetIds],
    );
    const fetchCacheKey = widgetIdsKey === '' ? '' : `${widgetIdsKey}|${runtimeParamsKey}`;

    const cachedEntry = useMemo(() => {
        if (widgetIdsKey === '') {
            return null;
        }

        const entry = readFormulaWidgetPayloadCache(householdId, fetchCacheKey);
        if (entry === null) {
            return null;
        }

        if (!isFormulaWidgetPayloadCacheValidForVersion(entry, dataVersion)) {
            clearFormulaWidgetPayloadCacheForHousehold(householdId);

            return null;
        }

        return entry;
    }, [householdId, fetchCacheKey, dataVersion]);

    // Store per-widget in-sessione: chiave `${widgetId}|${firmaParametri}`.
    // Permette di NON ricalcolare i widget il cui filtro non è cambiato quando
    // l'utente filtra un singolo widget.
    const storeRef = useRef<WidgetPayloadStore>({
        householdId,
        dataVersion,
        etag: cachedEntry?.etag ?? null,
        map: {},
    });

    if (storeRef.current.householdId !== householdId || storeRef.current.dataVersion !== dataVersion) {
        storeRef.current = { householdId, dataVersion, etag: null, map: {} };
    }

    // Seed dei payload iniziali (server-side) una sola volta, con le firme al mount.
    const mountSignaturesRef = useRef<Record<string, string> | null>(null);
    if (mountSignaturesRef.current === null) {
        mountSignaturesRef.current = signatureByWidgetId;
    }
    const initialSeededRef = useRef(false);
    if (!initialSeededRef.current && Object.keys(initialPayloads).length > 0) {
        for (const id of Object.keys(initialPayloads)) {
            const key = widgetStoreKey(id, mountSignaturesRef.current[id] ?? '');
            if (storeRef.current.map[key] === undefined) {
                storeRef.current.map[key] = initialPayloads[id];
            }
        }
        initialSeededRef.current = true;
    }

    const [payloads, setPayloads] = useState<Record<string, FormulaWidgetPayload>>(() => ({
        ...(cachedEntry?.payloads ?? {}),
        ...initialPayloads,
    }));
    const payloadsRef = useRef(payloads);
    useEffect(() => {
        payloadsRef.current = payloads;
    });

    const resolveFromStore = (): Record<string, FormulaWidgetPayload> => {
        const out: Record<string, FormulaWidgetPayload> = {};
        for (const id of widgetIds) {
            const fromStore = storeRef.current.map[widgetStoreKey(id, signatureByWidgetId[id] ?? '')];
            if (fromStore !== undefined) {
                out[id] = fromStore;
            } else if (payloadsRef.current[id] !== undefined) {
                // Placeholder stale: evita che il widget si svuoti durante il refetch.
                out[id] = payloadsRef.current[id];
            }
        }

        return out;
    };

    const [loading, setLoading] = useState(
        () => widgetIds.length > 0 && widgetIds.some((id) => initialPayloads[id] === undefined && (cachedEntry?.payloads[id]) === undefined),
    );
    const [pendingWidgetIds, setPendingWidgetIds] = useState<Set<string>>(() => new Set());
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (widgetIds.length === 0) {
            setLoading(false);
            setError(null);
            setPendingWidgetIds(new Set());

            return;
        }

        // Seed dallo storage cross-sessione (solo se fresco): i payload sono già
        // relativi alle firme correnti perché la chiave include i parametri runtime.
        if (cachedEntry !== null && isFormulaWidgetPayloadCacheFresh(cachedEntry)) {
            for (const id of Object.keys(cachedEntry.payloads)) {
                const key = widgetStoreKey(id, signatureByWidgetId[id] ?? '');
                if (storeRef.current.map[key] === undefined) {
                    storeRef.current.map[key] = cachedEntry.payloads[id];
                }
            }
            if (storeRef.current.etag === null && cachedEntry.etag) {
                storeRef.current.etag = cachedEntry.etag;
            }
        }

        const pendingIds = widgetIds.filter(
            (id) => storeRef.current.map[widgetStoreKey(id, signatureByWidgetId[id] ?? '')] === undefined,
        );

        const resolved = resolveFromStore();
        setPayloads((prev) => (samePayloadMap(prev, resolved) ? prev : resolved));

        if (pendingIds.length === 0) {
            setLoading(false);
            setPendingWidgetIds(new Set());
            setError(null);

            return;
        }

        const anyWithoutPlaceholder = pendingIds.some((id) => resolved[id] === undefined);
        setLoading(anyWithoutPlaceholder);

        const controller = new AbortController();
        let cancelled = false;

        async function loadPending(): Promise<void> {
            if (!cancelled) {
                setPendingWidgetIds(new Set(pendingIds));
            }

            const useConditionalRequest =
                storeRef.current.etag !== null
                && pendingIds.length === widgetIds.length
                && pendingIds.every((id, index) => id === widgetIds[index]);

            const result = await fetchFormulaWidgetPayloads(
                pendingIds,
                runtimeParams,
                storeRef.current.etag,
                controller.signal,
                useConditionalRequest,
            );

            let fetched: Record<string, FormulaWidgetPayload> = result.payloads;

            if (result.notModified) {
                fetched = {};
                for (const id of pendingIds) {
                    const cachedPayload = cachedEntry?.payloads[id];
                    if (cachedPayload !== undefined) {
                        fetched[id] = cachedPayload;
                    }
                }

                const missing = pendingIds.filter((id) => fetched[id] === undefined);
                if (missing.length > 0) {
                    const retry = await fetchFormulaWidgetPayloads(missing, runtimeParams, null, controller.signal, false);
                    fetched = { ...fetched, ...retry.payloads };
                    if (retry.etag) {
                        storeRef.current.etag = retry.etag;
                    }
                }
            } else if (result.etag) {
                storeRef.current.etag = result.etag;
            }

            if (cancelled) {
                return;
            }

            for (const id of Object.keys(fetched)) {
                storeRef.current.map[widgetStoreKey(id, signatureByWidgetId[id] ?? '')] = fetched[id];
            }

            const merged = resolveFromStore();
            setPayloads(merged);
            writeFormulaWidgetPayloadCache(
                householdId,
                fetchCacheKey,
                merged,
                storeRef.current.etag,
                dataVersion,
            );
            setError(null);
        }

        loadPending()
            .catch((err) => {
                if (axios.isAxiosError(err) && err.code === 'ERR_CANCELED') {
                    return;
                }

                if (!anyWithoutPlaceholder) {
                    // Mostriamo comunque i dati stale: errore silenzioso.
                    setError(null);

                    return;
                }

                setError('Non sono riuscito a caricare i widget a formula. Riprova tra poco.');
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                    setPendingWidgetIds(new Set());
                }
            });

        return () => {
            cancelled = true;
            controller.abort();
        };
    }, [widgetIdsKey, runtimeParamsKey, householdId, dataVersion, fetchCacheKey, signatureByWidgetId, runtimeParams, cachedEntry]);

    return { payloads, loading, pendingWidgetIds, error, cacheTtlMs: FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS };
}
