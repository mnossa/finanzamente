import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import SimulationsContent, { type SimulationsContentProps } from '@/Components/Simulations/SimulationsContent';

export default function SimulationsIndex(props: SimulationsContentProps) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Simulazioni Finanziarie" />}>
            <Head title="Simulazioni Finanziarie" />
            <PageContent>
                <SimulationsContent {...props} />
            </PageContent>
        </AuthenticatedLayout>
    );
}
