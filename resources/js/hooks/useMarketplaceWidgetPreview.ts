import axios from 'axios';
import { useEffect, useState } from 'react';
import type { FormulaWidgetPayload, FormulaWidgetSummary } from '@/types/formulaWidget';
import type { FormulaWidgetPreviewStatus } from '@/hooks/useFormulaWidgetPreview';

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

function previewPayload(widget: FormulaWidgetSummary): Record<string, string | number | null> {
    if (widget.template_slug) {
        return { template_slug: widget.template_slug };
    }

    return { source_widget_id: widget.id };
}

export function useMarketplaceWidgetPreview(widget: FormulaWidgetSummary | null) {
    const [status, setStatus] = useState<FormulaWidgetPreviewStatus>('idle');
    const [payload, setPayload] = useState<FormulaWidgetPayload | null>(null);
    const [errors, setErrors] = useState<string[]>([]);

    useEffect(() => {
        if (widget === null) {
            setStatus('idle');
            setPayload(null);
            setErrors([]);

            return;
        }

        const controller = new AbortController();
        setStatus('loading');
        setPayload(null);
        setErrors([]);

        axios
            .post(route('formula-marketplace.preview'), previewPayload(widget), {
                signal: controller.signal,
            })
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
    }, [widget]);

    return { status, payload, errors };
}
