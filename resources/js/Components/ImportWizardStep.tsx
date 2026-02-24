import clsx from 'clsx';

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
        <nav aria-label="Fasi dell'importazione" className={clsx('flex items-center overflow-x-auto', className)}>
            {steps.map((step, index) => (
                <div key={index} className="flex items-center flex-shrink-0">
                    <div className="flex flex-col items-center">
                        <div
                            className={clsx(
                                'flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold',
                                step.completed && 'bg-blue-600 text-white',
                                step.active && !step.completed && 'bg-blue-600 text-white ring-4 ring-blue-100',
                                !step.active && !step.completed && 'bg-gray-200 text-gray-500',
                            )}
                            aria-current={step.active ? 'step' : undefined}
                        >
                            {step.completed ? '✓' : index + 1}
                        </div>
                        <span className={clsx(
                            'mt-1 text-xs text-center whitespace-nowrap',
                            step.active ? 'text-blue-700 font-medium' : 'text-gray-500',
                        )}>
                            {step.label}
                        </span>
                    </div>
                    {index < steps.length - 1 && (
                        <div
                            className={clsx(
                                'h-0.5 w-8 sm:w-16 mx-1 mb-5 flex-shrink-0',
                                step.completed ? 'bg-blue-600' : 'bg-gray-200',
                            )}
                            aria-hidden="true"
                        />
                    )}
                </div>
            ))}
        </nav>
    );
}
