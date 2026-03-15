import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { InvestmentColumnMapping } from '@/Components/InvestmentColumnMapper';

interface Layout {
    id: number;
    name: string;
    bank_name: string;
    icon: string | null;
    column_mapping: InvestmentColumnMapping;
    delimiter: string;
    date_format: string;
    has_header: boolean;
    encoding: string;
}

interface ImportLayoutsProps {
    layouts: Layout[];
}

export default function ImportLayouts({ layouts }: ImportLayoutsProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [layoutToDelete, setLayoutToDelete] = useState<Layout | null>(null);

    const handleDelete = (layout: Layout) => {
        setLayoutToDelete(layout);
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!layoutToDelete) return;
        router.delete(route('investments.import.layouts.destroy', layoutToDelete.id), {
            onFinish: () => {
                setDeleteDialogOpen(false);
                setLayoutToDelete(null);
            },
        });
    };

    const fieldLabels: Record<keyof InvestmentColumnMapping, string> = {
        buy_date:  'Data acquisto',
        quantity:  'Quantità',
        buy_price: 'Prezzo',
        ticker:    'Ticker',
        isin:      'ISIN',
        fees:      'Commissioni',
        notes:     'Note',
    };

    return (
        <AuthenticatedLayout>
            <Head title="Layout Import Investimenti" />

            <div className="py-6">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Layout Import Investimenti"
                        subtitle="Gestisci le configurazioni salvate per l'importazione CSV degli investimenti."
                    />

                    <div className="mt-6 flex justify-end">
                        <LinkButton href={route('investments.import')}>
                            ← Torna all'importazione
                        </LinkButton>
                    </div>

                    {layouts.length === 0 ? (
                        <EmptyState
                            icon="📋"
                            title="Nessun layout salvato"
                            description="Importa investimenti da CSV e salva la configurazione come layout per riutilizzarla."
                        />
                    ) : (
                        <div className="mt-4 space-y-4">
                            {layouts.map((layout) => (
                                <div
                                    key={layout.id}
                                    className="bg-white dark:bg-gray-800 rounded-xl shadow p-4 flex flex-wrap items-start justify-between gap-4"
                                >
                                    <div className="flex items-center gap-3">
                                        {layout.icon && (
                                            <span className="text-2xl">{layout.icon}</span>
                                        )}
                                        <div>
                                            <p className="font-semibold text-gray-900 dark:text-gray-100">
                                                {layout.name}
                                            </p>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                Separatore: <code>{layout.delimiter}</code>
                                                {' · '}Formato data: <code>{layout.date_format}</code>
                                                {' · '}Codifica: <code>{layout.encoding}</code>
                                            </p>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {(Object.entries(layout.column_mapping) as [keyof InvestmentColumnMapping, number | null][])
                                                    .filter(([, v]) => v !== null)
                                                    .map(([k, v]) => (
                                                        <span
                                                            key={k}
                                                            className="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300"
                                                        >
                                                            {fieldLabels[k]}: col. {(v as number) + 1}
                                                        </span>
                                                    ))}
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(layout)}
                                        className="text-sm text-red-600 dark:text-red-400 hover:underline"
                                    >
                                        Elimina
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Elimina layout"
                description={`Sei sicuro di voler eliminare il layout "${layoutToDelete?.name}"?`}
                onConfirm={confirmDelete}
                onCancel={() => { setDeleteDialogOpen(false); setLayoutToDelete(null); }}
            />
        </AuthenticatedLayout>
    );
}
