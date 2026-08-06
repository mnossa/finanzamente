import GuidedFormProgress from '@/Components/GuidedForm/GuidedFormProgress';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface Step {
    /** Opzionale: non mostrato in UI (solo per conteggio passi). */
    label?: string;
}

interface GuidedFormWizardProps {
    steps: Step[];
    currentStep: number;
    title: string;
    subtitle?: string;
    children: ReactNode;
    className?: string;
}

export default function GuidedFormWizard({
    steps,
    currentStep,
    title,
    subtitle,
    children,
    className,
}: GuidedFormWizardProps) {
    return (
        <div className={clsx('mx-auto flex w-full max-w-lg flex-col', className)}>
            <GuidedFormProgress currentStep={currentStep} totalSteps={steps.length} className="mb-6" />
            <div className="flex min-h-0 flex-col justify-start rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:min-h-[min(70vh,32rem)] sm:justify-center sm:p-8">
                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">{title}</h2>
                {subtitle && (
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>
                )}
                <div className="mt-6">{children}</div>
            </div>
        </div>
    );
}
