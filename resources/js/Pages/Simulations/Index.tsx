import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import SimulationsContent, { type SimulationsContentProps } from '@/Components/Simulations/SimulationsContent';

type IndexProps = SimulationsContentProps & {
    canSave: boolean;
    savedScenarios: SimulationsContentProps['savedScenarios'];
    pacActiveCount?: number;
};

export default function SimulationsIndex(props: IndexProps) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Simulazioni Finanziarie" />}>
            <Head title="Simulazioni Finanziarie" />
            <PageContent>
                <SimulationsContent {...props} showRegistrationCta={false} />
            </PageContent>
        </AuthenticatedLayout>
    );
}
