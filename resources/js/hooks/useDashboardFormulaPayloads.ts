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
        route('dashboard.formula-widget-payloads', { ids: widgetIds.join(',') }),
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

    const cachedEntry = useMemo(() => {
        if (widgetIdsKey === '') {
            return null;
        }

        const entry = readFormulaWidgetPayloadCache(householdId, widgetIdsKey);
        if (entry === null) {
            return null;
        }

        if (!isFormulaWidgetPayloadCacheValidForVersion(entry, dataVersion)) {
            clearFormulaWidgetPayloadCacheForHousehold(householdId);

            return null;
        }

        return entry;
    }, [householdId, widgetIdsKey, dataVersion]);

    const bootstrapPayloads = useMemo(
        () => mergePayloadSources(initialPayloads, cachedEntry?.payloads ?? {}),
        [initialPayloads, cachedEntry],
    );

    const [payloads, setPayloads] = useState<Record<string, FormulaWidgetPayload>>(bootstrapPayloads);
    const [loading, setLoading] = useState(
        () => widgetIds.length > 0 && hasMissingPayloads(widgetIds, bootstrapPayloads),
    );
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setPayloads(bootstrapPayloads);
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

        async function loadSequentially(): Promise<void> {
            let mergedPayloads = shouldRefreshStaleCache ? { ...initialPayloads } : { ...bootstrapPayloads };
            let currentEtag = cachedEntry?.etag ?? null;
            let currentDataVersion = cachedEntry?.dataVersion ?? dataVersion;

            const pendingIds = shouldRefreshStaleCache
                ? widgetIds
                : widgetIds.filter((id) => mergedPayloads[id] === undefined);

            for (const widgetId of pendingIds) {
                if (cancelled) {
                    return;
                }

                try {
                    const isFullRequest = pendingIds.length === widgetIds.length
                        && pendingIds.every((id, index) => id === widgetIds[index]);
                    const result = await fetchFormulaWidgetPayloads(
                        [widgetId],
                        currentEtag,
                        controller.signal,
                        isFullRequest,
                    );

                    mergedPayloads = mergeFetchedPayloads(mergedPayloads, widgetId, result, cachedEntry);

                    if (mergedPayloads[widgetId] === undefined && result.notModified) {
                        const retry = await fetchFormulaWidgetPayloads([widgetId], null, controller.signal, false);
                        mergedPayloads = { ...mergedPayloads, ...retry.payloads };
                        if (retry.etag) {
                            currentEtag = retry.etag;
                        }
                        if (retry.dataVersion) {
                            currentDataVersion = retry.dataVersion;
                        }
                    } else {
                        if (result.etag) {
                            currentEtag = result.etag;
                        }
                        if (result.dataVersion) {
                            currentDataVersion = result.dataVersion;
                        }
                    }

                    setPayloads({ ...mergedPayloads });
                    writeFormulaWidgetPayloadCache(
                        householdId,
                        widgetIdsKey,
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

                    return;
                }
            }
        }

        loadSequentially()
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
            controller.abort();
        };
    }, [widgetIdsKey, householdId, dataVersion]);

    return { payloads, loading, error, cacheTtlMs: FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS };
}
