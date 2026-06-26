import axios from 'axios';
import { useCallback, useEffect, useMemo, useState } from 'react';
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
}

export type FormulaWidgetPreviewStatus = 'idle' | 'loading' | 'success' | 'error';

function useDebounce<T>(value: T, delay: number): T {
    const [debouncedValue, setDebouncedValue] = useState<T>(value);

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

    const previewRequest = useMemo(
        () => ({
            ...input,
            runtime_params: runtimeParams,
        }),
        [input, runtimeParams],
    );

    const debouncedInput = useDebounce(previewRequest, 400);
    const [status, setStatus] = useState<FormulaWidgetPreviewStatus>('idle');
    const [payload, setPayload] = useState<FormulaWidgetPayload | null>(null);
    const [errors, setErrors] = useState<string[]>([]);
    const [isFetching, setIsFetching] = useState(false);

    useEffect(() => {
        if (!debouncedInput.financial_variable_id) {
            setStatus('idle');
            setPayload(null);
            setErrors([]);
            setIsFetching(false);

            return;
        }

        if (formulaWidgetRequiresPeriod(debouncedInput.display_type, debouncedInput.chart_config) && !debouncedInput.period_preset) {
            setStatus('error');
            setPayload(null);
            setErrors(['Seleziona un periodo per vedere l\'anteprima.']);
            setIsFetching(false);

            return;
        }

        const controller = new AbortController();
        setIsFetching(true);

        axios
            .post(
                route('formula-widgets.preview'),
                {
                    name: debouncedInput.name || null,
                    financial_variable_id: debouncedInput.financial_variable_id,
                    display_type: debouncedInput.display_type,
                    period_preset: debouncedInput.period_preset || null,
                    chart_config: debouncedInput.chart_config,
                    runtime_params: debouncedInput.runtime_params,
                },
                { signal: controller.signal },
            )
            .then((response) => {
                setPayload(response.data.payload as FormulaWidgetPayload);
                setErrors([]);
                setStatus('success');
            })
            .catch((error) => {
                if (axios.isAxiosError(error) && error.code === 'ERR_CANCELED') {
                    return;
                }

                setPayload(null);
                setErrors(flattenValidationErrors(error?.response?.data?.errors));
                setStatus('error');
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setIsFetching(false);
                }
            });

        return () => controller.abort();
    }, [debouncedInput]);

    const handleParameterChange = useCallback((key: string, value: string) => {
        setRuntimeParams((current) => ({
            ...current,
            [key]: value,
        }));
    }, []);

    const isRefreshing = isFetching && payload !== null;
    const hasRuntimeParameters = chartConfigHasRuntimeParameters(input.chart_config);

    return {
        status,
        payload,
        errors,
        onParameterChange: hasRuntimeParameters ? handleParameterChange : undefined,
        isRefreshing,
        isFetching,
        hasRuntimeParameters,
    };
}
