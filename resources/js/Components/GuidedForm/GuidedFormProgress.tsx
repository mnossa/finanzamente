import clsx from 'clsx';

interface GuidedFormProgressProps {
    currentStep: number;
    totalSteps: number;
    className?: string;
}

/**
 * Indicatore compatto: solo barra + testo "Passo X di Y" (niente etichette per step).
 * Ideale su schermi stretti con molti passaggi.
 */
export default function GuidedFormProgress({ currentStep, totalSteps, className }: GuidedFormProgressProps) {
    const progress = totalSteps > 0 ? ((currentStep + 1) / totalSteps) * 100 : 0;

    return (
        <div className={clsx('space-y-2', className)} aria-label={`Passo ${currentStep + 1} di ${totalSteps}`}>
            <div className="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span className="font-medium text-emerald-600 dark:text-emerald-400">
                    Passo {currentStep + 1} di {totalSteps}
                </span>
                <span>{Math.round(progress)}%</span>
            </div>
            <div
                className="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                role="progressbar"
                aria-valuenow={currentStep + 1}
                aria-valuemin={1}
                aria-valuemax={totalSteps}
            >
                <div
                    className="h-full rounded-full bg-emerald-500 transition-all duration-300 ease-out"
                    style={{ width: `${progress}%` }}
                />
            </div>
        </div>
    );
}
