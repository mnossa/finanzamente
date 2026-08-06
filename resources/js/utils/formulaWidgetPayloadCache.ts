import type { FormulaWidgetPayload } from '@/types/formulaWidget';

const CACHE_PREFIX = 'fm.dashboard.formulaPayloads.v1';
export const FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS = 5 * 60 * 1000;

export interface FormulaWidgetPayloadCacheEntry {
    savedAt: number;
    etag: string | null;
    dataVersion: string | null;
    payloads: Record<string, FormulaWidgetPayload>;
}

function storageKey(householdId: number | null, widgetIdsKey: string): string {
    return `${CACHE_PREFIX}:${householdId ?? 0}:${widgetIdsKey}`;
}

export function readFormulaWidgetPayloadCache(
    householdId: number | null,
    widgetIdsKey: string,
): FormulaWidgetPayloadCacheEntry | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = sessionStorage.getItem(storageKey(householdId, widgetIdsKey));
        if (!raw) {
            return null;
        }

        const entry = JSON.parse(raw) as FormulaWidgetPayloadCacheEntry;
        if (!entry.payloads || typeof entry.savedAt !== 'number') {
            return null;
        }

        return entry;
    } catch {
        return null;
    }
}

export function writeFormulaWidgetPayloadCache(
    householdId: number | null,
    widgetIdsKey: string,
    payloads: Record<string, FormulaWidgetPayload>,
    etag: string | null,
    dataVersion: string | null,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const entry: FormulaWidgetPayloadCacheEntry = {
            savedAt: Date.now(),
            etag,
            dataVersion,
            payloads,
        };
        sessionStorage.setItem(storageKey(householdId, widgetIdsKey), JSON.stringify(entry));
    } catch {
        // Quota exceeded o storage disabilitato: ignora.
    }
}

export function clearFormulaWidgetPayloadCacheForHousehold(householdId: number | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    const suffix = `:${householdId ?? 0}:`;

    try {
        for (let index = sessionStorage.length - 1; index >= 0; index -= 1) {
            const key = sessionStorage.key(index);
            if (key?.startsWith(CACHE_PREFIX) && key.includes(suffix)) {
                sessionStorage.removeItem(key);
            }
        }
    } catch {
        // Ignora.
    }
}

export function isFormulaWidgetPayloadCacheFresh(
    entry: FormulaWidgetPayloadCacheEntry,
    ttlMs: number = FORMULA_WIDGET_PAYLOAD_CACHE_TTL_MS,
): boolean {
    return Date.now() - entry.savedAt < ttlMs;
}

export function isFormulaWidgetPayloadCacheValidForVersion(
    entry: FormulaWidgetPayloadCacheEntry,
    dataVersion: string | null,
): boolean {
    if (!dataVersion) {
        return true;
    }

    return entry.dataVersion === dataVersion;
}
