import PrimaryButton from '@/Components/PrimaryButton';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface GuidedFormStepActionsProps {
    step: number;
    totalSteps: number;
    processing?: boolean;
    canNext?: boolean;
    onBack: () => void;
    cancelHref?: string;
    submitLabel?: string;
    skipLabel?: string;
    onSkip?: () => void;
    extraActions?: ReactNode;
}

export default function GuidedFormStepActions({
    step,
    totalSteps,
    processing = false,
    canNext = true,
    onBack,
    cancelHref,
    submitLabel = 'Salva',
    skipLabel = 'Salta',
    onSkip,
    extraActions,
}: GuidedFormStepActionsProps) {
    const isLast = step === totalSteps - 1;

    return (
        <div className="mt-8 flex items-center justify-between gap-3">
            {step > 0 ? (
                <button
                    type="button"
                    onClick={onBack}
                    className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                >
                    Indietro
                </button>
            ) : cancelHref ? (
                <Link href={cancelHref} className="text-sm text-gray-500">
                    Annulla
                </Link>
            ) : (
                <span />
            )}
            <div className="flex items-center gap-2">
                {extraActions}
                {onSkip && !isLast && (
                    <button
                        type="button"
                        onClick={onSkip}
                        className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    >
                        {skipLabel}
                    </button>
                )}
                <PrimaryButton type="submit" disabled={!canNext || processing}>
                    {isLast ? (processing ? 'Salvataggio...' : submitLabel) : 'Avanti'}
                </PrimaryButton>
            </div>
        </div>
    );
}

