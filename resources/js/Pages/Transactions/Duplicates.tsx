import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import { Head, router } from '@inertiajs/react';

interface Item {
    id: number;
    distance_days: number;
    primary: { id: number; date: string; amount: number; description: string | null };
    candidate: { id: number; date: string; amount: number; description: string | null };
}

export default function Duplicates({ items }: { items: Item[] }) {
    return (
        <AuthenticatedLayout header={<PageHeader title="Possibili duplicati" />}>
            <Head title="Possibili duplicati" />
            <PageContent>
                <CardBox className="p-4 space-y-3">
                    {items.length === 0 && <p className="text-sm text-gray-500">Nessun duplicato in revisione.</p>}
                    {items.map((item) => (
                        <div key={item.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p className="text-sm font-semibold">{item.primary.description ?? 'Senza descrizione'}</p>
                            <p className="text-xs text-gray-500">Distanza: {item.distance_days} giorni</p>
                            <p className="text-sm">{item.primary.date} ({item.primary.amount.toFixed(2)}) vs {item.candidate.date} ({item.candidate.amount.toFixed(2)})</p>
                            <div className="mt-2 flex gap-2">
                                <button onClick={() => router.post(route('transactions.duplicates.ignore', item.id))} className="rounded bg-gray-200 px-2 py-1 text-xs dark:bg-gray-700">Ignora</button>
                                <button onClick={() => router.post(route('transactions.duplicates.valid', item.id))} className="rounded bg-emerald-600 px-2 py-1 text-xs text-white">Valido</button>
                            </div>
                        </div>
                    ))}
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
