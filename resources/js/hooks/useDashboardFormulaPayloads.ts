import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import type { DashboardLayoutConfig } from '@/types/dashboard';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';
import { isFormulaWidgetId, parseFormulaWidgetNumericId } from '@/types/formulaWidget';
import {
    FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS,
    isFormulaWidgetPayloadCacheFresh,
    readFormulaWidgetPayloadCache,
    writeFormulaWidgetPayloadCache,
} from '@/utils/formulaWidgetPayloadCache';

function visibleFormulaWidgetIds(layout: DashboardLayoutConfig): string[] {
    return (layout.widgets ?? [])
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

export function useDashboardFormulaPayloads(
    dashboardLayout: DashboardLayoutConfig,
    initialPayloads: Record<string, FormulaWidgetPayload> = {},
) {
    const { activeHousehold } = usePage<PageProps>().props;
    const householdId = activeHousehold?.id ?? null;

    const widgetIds = useMemo(() => visibleFormulaWidgetIds(dashboardLayout), [dashboardLayout.widgets]);
    const widgetIdsKey = widgetIds.join(',');

    const cachedEntry = useMemo(
        () => (widgetIdsKey === '' ? null : readFormulaWidgetPayloadCache(householdId, widgetIdsKey)),
        [householdId, widgetIdsKey],
    );

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

        if (cacheComplete && cacheFresh) {
            setLoading(false);

            return;
        }

        if (!hasMissingPayloads(widgetIds, bootstrapPayloads)) {
            setLoading(false);
        } else {
            setLoading(true);
        }

        const controller = new AbortController();

        const headers: Record<string, string> = {};
        if (cachedEntry?.etag) {
            headers['If-None-Match'] = cachedEntry.etag.startsWith('"')
                ? cachedEntry.etag
                : `"${cachedEntry.etag}"`;
        }

        axios
            .get<{ payloads: Record<string, FormulaWidgetPayload> }>(route('dashboard.formula-widget-payloads'), {
                signal: controller.signal,
                headers,
                validateStatus: (status) => status === 200 || status === 304,
            })
            .then((response) => {
                if (response.status === 304 && cachedEntry) {
                    writeFormulaWidgetPayloadCache(
                        householdId,
                        widgetIdsKey,
                        cachedEntry.payloads,
                        cachedEntry.etag,
                    );
                    setPayloads((previous) => ({ ...initialPayloads, ...cachedEntry.payloads, ...previous }));
                    setError(null);

                    return;
                }

                const nextPayloads = { ...initialPayloads, ...response.data.payloads };
                setPayloads(nextPayloads);
                writeFormulaWidgetPayloadCache(
                    householdId,
                    widgetIdsKey,
                    nextPayloads,
                    extractEtag(response.headers as Record<string, unknown>),
                );
                setError(null);
            })
            .catch((err) => {
                if (axios.isAxiosError(err) && err.code === 'ERR_CANCELED') {
                    return;
                }

                if (cacheComplete) {
                    setError(null);

                    return;
                }

                setError('Non sono riuscito a caricare i widget a formula. Riprova tra poco.');
            })
            .finally(() => {
                setLoading(false);
            });

        return () => controller.abort();
    }, [widgetIdsKey, householdId]);

    return { payloads, loading, error, cacheTtlMs: FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS };
}
