import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';
import {
    chartConfigHasRuntimeParameters,
    extractRuntimeParamDefaultsFromChartConfig,
    formulaWidgetRequiresPeriod,
} from '@/utils/formulaWidgetForm';

export interface FormulaWidgetPreviewInput {
    name: string;
    financial_variable_id: number | '';
    display_type: string;
    period_preset: string;
    chart_config: Record<string, unknown>;
    /** Override: metrica di periodo anche su KPI senza delta. */
    requirePeriod?: boolean;
}

export type FormulaWidgetPreviewStatus = 'idle' | 'loading' | 'success' | 'error';

type PreviewRequest = {
    name: string;
    financial_variable_id: number | '';
    display_type: string;
    period_preset: string;
    chart_config: Record<string, unknown>;
    runtime_params: Record<string, string>;
    requirePeriod: boolean;
};

function stablePreviewKey(input: Omit<PreviewRequest, 'name'>): string {
    return JSON.stringify({
        financial_variable_id: input.financial_variable_id,
        display_type: input.display_type,
        period_preset: input.period_preset,
        chart_config: input.chart_config,
        runtime_params: input.runtime_params,
        requirePeriod: input.requirePeriod,
    });
}

function useDebouncedValue<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedValue(value), delay);

        return () => window.clearTimeout(timer);
    }, [value, delay]);

    return debouncedValue;
}

function flattenValidationErrors(errors: unknown): string[] {
    if (Array.isArray(errors)) {
        return errors.filter((message): message is string => typeof message === 'string');
    }

    if (errors && typeof errors === 'object') {
        return Object.values(errors as Record<string, string | string[]>).flatMap((message) =>
            Array.isArray(message) ? message : [message],
        );
    }

    return ['Anteprima non disponibile.'];
}

export function useFormulaWidgetPreview(input: FormulaWidgetPreviewInput) {
    const parameterDefaults = useMemo(
        () => extractRuntimeParamDefaultsFromChartConfig(input.chart_config),
        [input.chart_config],
    );
    const parameterDefaultsKey = useMemo(() => JSON.stringify(parameterDefaults), [parameterDefaults]);

    const [runtimeParams, setRuntimeParams] = useState<Record<string, string>>(parameterDefaults);

    useEffect(() => {
        setRuntimeParams(parameterDefaults);
    }, [parameterDefaultsKey, parameterDefaults]);

    const liveRequest = useMemo<PreviewRequest>(
        () => ({
            name: input.name,
            financial_variable_id: input.financial_variable_id,
            display_type: input.display_type,
            period_preset: input.period_preset,
            chart_config: input.chart_config,
            runtime_params: runtimeParams,
            requirePeriod:
                Boolean(input.requirePeriod)
                || formulaWidgetRequiresPeriod(input.display_type, input.chart_config),
        }),
        [
            input.name,
            input.financial_variable_id,
            input.display_type,
            input.period_preset,
            input.chart_config,
            input.requirePeriod,
            runtimeParams,
        ],
    );

    const liveKey = useMemo(() => stablePreviewKey(liveRequest), [liveRequest]);
    const debouncedRequest = useDebouncedValue(liveRequest, 250);
    const debouncedKey = useMemo(() => stablePreviewKey(debouncedRequest), [debouncedRequest]);
    const debouncedRequestRef = useRef(debouncedRequest);
    debouncedRequestRef.current = debouncedRequest;

    const [status, setStatus] = useState<FormulaWidgetPreviewStatus>('idle');
    const [payload, setPayload] = useState<FormulaWidgetPayload | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [isFetching, setIsFetching] = useState(false);
    const [resolvedKey, setResolvedKey] = useState<string | null>(null);
    const requestSeq = useRef(0);

    const isStale = Boolean(payload) && resolvedKey !== null && liveKey !== resolvedKey;

    useEffect(() => {
        if (liveKey !== debouncedKey && liveRequest.financial_variable_id) {
            setIsFetching(true);
        }
    }, [liveKey, debouncedKey, liveRequest.financial_variable_id]);

    useEffect(() => {
        const request = debouncedRequestRef.current;

        if (!request.financial_variable_id) {
            requestSeq.current += 1;
            setStatus('idle');
            setPayload(null);
            setErrors([]);
            setIsFetching(false);
            setResolvedKey(null);

            return;
        }

        const needsPeriod = request.requirePeriod;

        if (needsPeriod && !request.period_preset) {
            requestSeq.current += 1;
            setStatus('error');
            setPayload(null);
            setErrors(['Seleziona un periodo per vedere l\'anteprima.']);
            setIsFetching(false);
            setResolvedKey(debouncedKey);

            return;
        }

        const controller = new AbortController();
        const seq = ++requestSeq.current;
        setIsFetching(true);

        axios
            .post(
                route('formula-widgets.preview'),
                {
                    name: request.name || null,
                    financial_variable_id: request.financial_variable_id,
                    display_type: request.display_type,
                    period_preset: request.period_preset || null,
                    chart_config: request.chart_config,
                    runtime_params: request.runtime_params,
                },
                { signal: controller.signal },
            )
            .then((response) => {
                if (seq !== requestSeq.current) {
                    return;
                }

                setPayload(response.data.payload as FormulaWidgetPayload);
                setErrors([]);
                setStatus('success');
                setResolvedKey(debouncedKey);
            })
            .catch((error) => {
                if (seq !== requestSeq.current) {
                    return;
                }

                if (axios.isAxiosError(error) && error.code === 'ERR_CANCELED') {
                    return;
                }

                setPayload(null);
                setErrors(flattenValidationErrors(error?.response?.data?.errors));
                setStatus('error');
                setResolvedKey(debouncedKey);
            })
            .finally(() => {
                if (seq === requestSeq.current) {
                    setIsFetching(false);
                }
            });

        return () => {
            controller.abort();
        };
    }, [debouncedKey]);

    const handleParameterChange = useCallback((key: string, value: string) => {
        setRuntimeParams((current) => ({
            ...current,
            [key]: value,
        }));
    }, []);

    const isRefreshing = (isFetching || isStale) && payload !== null;
    const hasRuntimeParameters = chartConfigHasRuntimeParameters(input.chart_config);

    return {
        status,
        payload,
        errors,
        onParameterChange: hasRuntimeParameters ? handleParameterChange : undefined,
        isRefreshing,
        isFetching: isFetching && !payload,
        hasRuntimeParameters,
    };
}
