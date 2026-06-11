import axios from 'axios';
import { useEffect, useState } from 'react';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';
import { formulaWidgetRequiresPeriod } from '@/utils/formulaWidgetForm';

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
    const debouncedInput = useDebounce(input, 400);
    const [status, setStatus] = useState<FormulaWidgetPreviewStatus>('idle');
    const [payload, setPayload] = useState<FormulaWidgetPayload | null>(null);
    const [errors, setErrors] = useState<string[]>([]);

    useEffect(() => {
        if (!debouncedInput.financial_variable_id) {
            setStatus('idle');
            setPayload(null);
            setErrors([]);

            return;
        }

        if (formulaWidgetRequiresPeriod(debouncedInput.display_type, debouncedInput.chart_config) && !debouncedInput.period_preset) {
            setStatus('error');
            setPayload(null);
            setErrors(['Seleziona un periodo per vedere l\'anteprima.']);

            return;
        }

        const controller = new AbortController();
        setStatus('loading');

        axios
            .post(
                route('formula-widgets.preview'),
                {
                    name: debouncedInput.name || null,
                    financial_variable_id: debouncedInput.financial_variable_id,
                    display_type: debouncedInput.display_type,
                    period_preset: debouncedInput.period_preset || null,
                    chart_config: debouncedInput.chart_config,
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
            });

        return () => controller.abort();
    }, [debouncedInput]);

    return { status, payload, errors };
}
