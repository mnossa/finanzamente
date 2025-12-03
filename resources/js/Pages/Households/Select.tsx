import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedSimpleLayout from '@/Layouts/AuthenticatedSimpleLayout';
import { Household, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Props extends PageProps {
    households: Household[];
}

export default function Select({ households }: Props) {
    const selectHousehold = (householdId: number) => {
        router.post(route('households.set-active', householdId));
    };

    return (
        <AuthenticatedSimpleLayout>
            <Head title="Seleziona Household" />

            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Seleziona Household
                </h2>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Scegli la household con cui vuoi lavorare.
                </p>
            </div>

            <div className="space-y-3">
                {households.map((household) => (
                    <div
                        key={household.id}
                        className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-600"
                    >
                        <div className="flex-1">
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                {household.name}
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {household.is_owner ? (
                                    <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Proprietario
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        {household.role === 'guest'
                                            ? 'Ospite'
                                            : 'Membro'}
                                    </span>
                                )}
                                <span className="ml-2">
                                    {household.members_count}{' '}
                                    {household.members_count === 1
                                        ? 'membro'
                                        : 'membri'}
                                </span>
                            </p>
                        </div>
                        <PrimaryButton
                            onClick={() => selectHousehold(household.id)}
                        >
                            Seleziona
                        </PrimaryButton>
                    </div>
                ))}
            </div>

            <div className="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                <Link
                    href={route('households.create')}
                    className="flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-4 text-sm font-medium text-slate-600 transition-colors hover:border-emerald-400 hover:text-emerald-600"
                >
                    + Crea nuova household
                </Link>
            </div>
        </AuthenticatedSimpleLayout>
    );
}
