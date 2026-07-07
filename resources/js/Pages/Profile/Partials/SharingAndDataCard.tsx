import { Link } from '@inertiajs/react';

interface SharingHousehold {
    id: number;
    name: string;
    financial_management_type: string;
    financial_management_label: string;
    url: string;
}

interface SharingData {
    households_count: number;
    active_household: SharingHousehold | null;
    households_select_url: string;
}

export default function SharingAndDataCard({
    sharing,
    className = '',
}: {
    sharing: SharingData;
    className?: string;
}) {
    return (
        <section className={className}>
            <h2 className="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                Condivisione e dati
            </h2>
            <p className="mb-5 text-sm text-gray-600 dark:text-gray-300">
                Gestisci la condivisione del nucleo familiare ed esporta i tuoi dati per la portabilità GDPR.
            </p>

            <div className="space-y-4">
                <div className="rounded-xl border border-slate-200 p-4 dark:border-gray-700">
                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Nuclei familiari
                    </p>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {sharing.households_count === 1
                            ? '1 nucleo collegato al tuo account.'
                            : `${sharing.households_count} nuclei collegati al tuo account.`}
                    </p>
                    {sharing.active_household && (
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Nucleo attivo: <strong>{sharing.active_household.name}</strong>
                            {' '}({sharing.active_household.financial_management_label})
                        </p>
                    )}
                    <div className="mt-3 flex flex-wrap gap-3">
                        <Link
                            href={sharing.households_select_url}
                            className="text-sm font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                        >
                            Gestisci nuclei
                        </Link>
                        {sharing.active_household && (
                            <Link
                                href={sharing.active_household.url}
                                className="text-sm font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                            >
                                Apri nucleo attivo
                            </Link>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 p-4 dark:border-gray-700">
                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Portabilità dati
                    </p>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Scarica un export JSON con profilo, nuclei, conti, transazioni e consensi.
                        Per motivi di sicurezza ti verrà chiesta la password.
                    </p>
                    <div className="mt-3 flex flex-wrap gap-3">
                        <Link
                            href={route('profile.data.export')}
                            className="text-sm font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                        >
                            Scarica export dati
                        </Link>
                        <Link
                            href={route('profile.consents.export')}
                            className="text-sm font-medium text-emerald-700 underline hover:text-emerald-800 dark:text-emerald-300"
                        >
                            Export storico consensi
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
