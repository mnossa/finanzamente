import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
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

function runtimeParamsCacheSuffix(paramsByWidgetId: Record<string, Record<string, string>>, widgetIds: string[]): string {
    const parts: string[] = [];

    for (const widgetId of widgetIds) {
        const params = paramsByWidgetId[widgetId];
        if (!params) {
            continue;
        }

        const encoded = Object.entries(params)
            .sort(([a], [b]) => a.localeCompare(b))
            .map(([key, value]) => `${key}=${value}`)
            .join(',');

        parts.push(`${widgetId}:${encoded}`);
    }

    return parts.join(';');
}

function hasMissingPayloads(widgetIds: string[], payloads: Record<string, FormulaWidgetPayload>): boolean {
    return widgetIds.some((id) => payloads[id] === undefined);
}

function mergePayloadSources(
    initialPayloads: Record<string, FormulaWidgetPayload>,
    cachedPayloads: Record<string, FormulaWidgetPayload>,
): Record<string, FormulaWidgetPayload> {
    return { ...cachedPayloads, ...initialPayloads };
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

function mergeFetchedPayloads(
    mergedPayloads: Record<string, FormulaWidgetPayload>,
    widgetId: string,
    result: Awaited<ReturnType<typeof fetchFormulaWidgetPayloads>>,
    cachedEntry: ReturnType<typeof readFormulaWidgetPayloadCache>,
): Record<string, FormulaWidgetPayload> {
    if (!result.notModified) {
        return { ...mergedPayloads, ...result.payloads };
    }

    const cachedPayload = cachedEntry?.payloads[widgetId];
    if (cachedPayload !== undefined) {
        return { ...mergedPayloads, [widgetId]: cachedPayload };
    }

    return mergedPayloads;
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

    const bootstrapPayloads = useMemo(
        () => mergePayloadSources(initialPayloads, cachedEntry?.payloads ?? {}),
        [initialPayloads, cachedEntry],
    );

    const [payloads, setPayloads] = useState<Record<string, FormulaWidgetPayload>>(bootstrapPayloads);
    const [loading, setLoading] = useState(
        () => widgetIds.length > 0 && hasMissingPayloads(widgetIds, bootstrapPayloads),
    );
    const [pendingWidgetIds, setPendingWidgetIds] = useState<Set<string>>(() => new Set());
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        // Mantiene i payload già caricati (anche stale) mentre cambia il cache key
        // per i parametri runtime: evita che il widget si svuoti durante il refetch.
        setPayloads((prev) => ({ ...prev, ...bootstrapPayloads }));
    }, [bootstrapPayloads]);

    useEffect(() => {
        if (widgetIds.length === 0) {
            setLoading(false);
            setError(null);

            return;
        }

        const cacheComplete = cachedEntry !== null && !hasMissingPayloads(widgetIds, cachedEntry.payloads);
        const cacheFresh = cachedEntry !== null && isFormulaWidgetPayloadCacheFresh(cachedEntry);
        const shouldRefreshStaleCache = cacheComplete && !cacheFresh;

        if (cacheComplete && cacheFresh) {
            setLoading(false);

            return;
        }

        if (!shouldRefreshStaleCache && !hasMissingPayloads(widgetIds, bootstrapPayloads)) {
            setLoading(false);
        } else {
            setLoading(true);
        }

        const controller = new AbortController();
        let cancelled = false;

        async function loadBatch(): Promise<void> {
            let mergedPayloads = shouldRefreshStaleCache ? { ...initialPayloads } : { ...bootstrapPayloads };

            const pendingIds = shouldRefreshStaleCache
                ? widgetIds
                : widgetIds.filter((id) => mergedPayloads[id] === undefined);

            if (pendingIds.length === 0) {
                return;
            }

            if (!cancelled) {
                setPendingWidgetIds(new Set(pendingIds));
            }

            let currentEtag = cachedEntry?.etag ?? null;
            let currentDataVersion = cachedEntry?.dataVersion ?? dataVersion;

            try {
                const result = await fetchFormulaWidgetPayloads(
                    pendingIds,
                    runtimeParams,
                    currentEtag,
                    controller.signal,
                    pendingIds.length === widgetIds.length
                        && pendingIds.every((id, index) => id === widgetIds[index]),
                );

                if (result.notModified) {
                    for (const widgetId of pendingIds) {
                        mergedPayloads = mergeFetchedPayloads(mergedPayloads, widgetId, result, cachedEntry);
                    }

                    const missingAfter304 = pendingIds.filter((id) => mergedPayloads[id] === undefined);
                    if (missingAfter304.length > 0) {
                        const retry = await fetchFormulaWidgetPayloads(missingAfter304, runtimeParams, null, controller.signal, false);
                        mergedPayloads = { ...mergedPayloads, ...retry.payloads };
                        if (retry.etag) {
                            currentEtag = retry.etag;
                        }
                        if (retry.dataVersion) {
                            currentDataVersion = retry.dataVersion;
                        }
                    }
                } else {
                    mergedPayloads = { ...mergedPayloads, ...result.payloads };
                    if (result.etag) {
                        currentEtag = result.etag;
                    }
                    if (result.dataVersion) {
                        currentDataVersion = result.dataVersion;
                    }
                }

                if (cancelled) {
                    return;
                }

                setPayloads({ ...mergedPayloads });
                writeFormulaWidgetPayloadCache(
                    householdId,
                    fetchCacheKey,
                    mergedPayloads,
                    currentEtag,
                    currentDataVersion,
                );
                setError(null);
            } catch (err) {
                if (axios.isAxiosError(err) && err.code === 'ERR_CANCELED') {
                    return;
                }

                if (cacheComplete) {
                    setError(null);

                    return;
                }

                setError('Non sono riuscito a caricare i widget a formula. Riprova tra poco.');
            }
        }

        loadBatch()
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
    }, [widgetIdsKey, runtimeParamsKey, householdId, dataVersion, bootstrapPayloads, initialPayloads, cachedEntry, runtimeParams]);

    return { payloads, loading, pendingWidgetIds, error, cacheTtlMs: FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS };
}
