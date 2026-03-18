import clsx from 'clsx';
import { Fragment } from 'react';

interface Step {
    label: string;
    completed: boolean;
    active: boolean;
}

interface ImportWizardStepProps {
    steps: Step[];
    className?: string;
}

export default function ImportWizardStep({ steps, className }: ImportWizardStepProps) {
    return (
        <nav aria-label="Fasi dell'importazione" className={clsx('flex items-start', className)}>
            {steps.map((step, index) => (
                <Fragment key={index}>
                    {/* Step bullet + label */}
                    <div className="flex flex-shrink-0 flex-col items-center">
                        <div
                            className={clsx(
                                'flex items-center justify-center rounded-full font-semibold',
                                'w-6 h-6 text-[10px] sm:w-8 sm:h-8 sm:text-sm',
                                step.completed && 'bg-blue-600 text-white',
                                step.active && !step.completed && 'bg-blue-600 text-white ring-2 ring-blue-100 sm:ring-4',
                                !step.active && !step.completed && 'bg-gray-200 text-gray-500',
                            )}
                            aria-current={step.active ? 'step' : undefined}
                        >
                            {step.completed ? '✓' : index + 1}
                        </div>
                        <span className={clsx(
                            'mt-0.5 sm:mt-1 text-center',
                            'text-[9px] sm:text-xs',
                            /* su mobile tronca, su sm+ mostra tutto */
                            'max-w-[52px] sm:max-w-none',
                            'overflow-hidden whitespace-nowrap text-ellipsis sm:whitespace-normal sm:overflow-visible',
                            step.active ? 'text-blue-700 font-medium' : 'text-gray-500',
                        )}>
                            {step.label}
                        </span>
                    </div>

                    {/* Linea connettore */}
                    {index < steps.length - 1 && (
                        <div
                            className={clsx(
                                'flex-1 h-0.5 mt-3 sm:mt-4 mx-1 sm:mx-2',
                                step.completed ? 'bg-blue-600' : 'bg-gray-200',
                            )}
                            aria-hidden="true"
                        />
                    )}
                </Fragment>
            ))}
        </nav>
    );
}
