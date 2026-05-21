import { Head } from '@inertiajs/react';
import MarketingToolLayout from '@/Layouts/MarketingToolLayout';
import SimulationsContent, { type SimulationsContentProps } from '@/Components/Simulations/SimulationsContent';

interface PublicIndexProps extends SimulationsContentProps {
    canRegister: boolean;
}

export default function SimulationsPublicIndex({ canRegister, ...simulationProps }: PublicIndexProps) {
    return (
        <MarketingToolLayout>
            <Head title="Simulazioni Finanziarie — Strumenti gratuiti" />
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">
                    Simulazioni finanziarie
                </h1>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400 sm:text-base">
                    Strumenti educativi gratuiti per esplorare risparmio, investimenti e fondo di emergenza.
                </p>
            </div>
            <SimulationsContent {...simulationProps} showRegistrationCta={canRegister} />
        </MarketingToolLayout>
    );
}
