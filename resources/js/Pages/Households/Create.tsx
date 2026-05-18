import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedSimpleLayout from '@/Layouts/AuthenticatedSimpleLayout';
import HouseholdCreateGuided from './HouseholdCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features)) {
        return (
            <AuthenticatedSimpleLayout>
                <Head title="Crea Household" />
                <div className="mb-6 text-center">
                    <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        Benvenuto in Finanzamente!
                    </h2>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Per iniziare, crea la tua prima household.
                    </p>
                </div>
                <HouseholdCreateGuided />
            </AuthenticatedSimpleLayout>
        );
    }

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        financial_management_type: 'shared_wallet',
        balance_type: 'equal', // equal o custom
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('households.store'));
    };

    return (
        <AuthenticatedSimpleLayout>
            <Head title="Crea Household" />

            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Benvenuto in Finanzamente!
                </h2>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Per iniziare, crea la tua prima household. Una household è
                    un gruppo (famiglia, coppia o personale) che condivide la
                    gestione finanziaria.
                </p>
            </div>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="name" value="Nome della Household" />

                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        placeholder="Es: Casa Rossi, La mia famiglia, Personale..."
                        isFocused={true}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />

                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="mt-6">
                    <InputLabel 
                        value="Come vuoi gestire le finanze della tua household?" 
                        className="mb-4"
                    />
                    
                    <div className="space-y-4">
                        {/* Portafoglio Comune */}
                        <div 
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.financial_management_type === 'shared_wallet'
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('financial_management_type', 'shared_wallet')}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="financial_management_type"
                                    value="shared_wallet"
                                    checked={data.financial_management_type === 'shared_wallet'}
                                    onChange={() => setData('financial_management_type', 'shared_wallet')}
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            Portafoglio Comune
                                        </h3>
                                        <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            Consigliato
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Tutti i membri condividono conti e spese. Perfetto per famiglie che condividono le finanze completamente.
                                    </p>
                                    <div className="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                        ✓ Visione aggregata di entrate e uscite<br />
                                        ✓ Budget condivisi<br />
                                        ✓ Nessun calcolo di debiti interni
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Bilanciamento Debiti */}
                        <div 
                            className={`relative rounded-lg border-2 p-4 cursor-pointer transition-colors ${
                                data.financial_management_type === 'debt_balancing'
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                            }`}
                            onClick={() => setData('financial_management_type', 'debt_balancing')}
                        >
                            <div className="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="financial_management_type"
                                    value="debt_balancing"
                                    checked={data.financial_management_type === 'debt_balancing'}
                                    onChange={() => setData('financial_management_type', 'debt_balancing')}
                                    className="mt-1 h-4 w-4 border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div className="flex-1">
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        Bilanciamento Debiti
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Ogni membro mantiene i propri conti personali. L'app calcola chi deve a chi per le spese condivise.
                                    </p>
                                    <div className="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                        ✓ Conti individuali separati<br />
                                        ✓ Calcolo automatico dei debiti<br />
                                        ✓ Perfetto per coinquilini o amici
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Configurazione percentuali per Bilanciamento Debiti */}
                    {data.financial_management_type === 'debt_balancing' && (
                        <div className="mt-4 rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/20">
                            <h4 className="text-sm font-medium text-orange-800 dark:text-orange-200 mb-3">
                                Modalità di bilanciamento
                            </h4>
                            
                            <div className="space-y-3">
                                <div 
                                    className={`flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors ${
                                        data.balance_type === 'equal'
                                            ? 'border-orange-300 bg-white dark:border-orange-600 dark:bg-gray-800'
                                            : 'border-gray-200 bg-gray-50 hover:bg-white dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                    onClick={() => setData('balance_type', 'equal')}
                                >
                                    <input
                                        type="radio"
                                        name="balance_type"
                                        value="equal"
                                        checked={data.balance_type === 'equal'}
                                        onChange={() => setData('balance_type', 'equal')}
                                        className="h-4 w-4 border-gray-300 text-orange-600 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <div className="flex-1">
                                        <h5 className="font-medium text-gray-900 dark:text-gray-100">
                                            Divisione equa automatica
                                        </h5>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Le spese condivise saranno sempre divise equamente tra tutti i membri attivi
                                        </p>
                                    </div>
                                </div>

                                <div 
                                    className={`flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors ${
                                        data.balance_type === 'custom'
                                            ? 'border-orange-300 bg-white dark:border-orange-600 dark:bg-gray-800'
                                            : 'border-gray-200 bg-gray-50 hover:bg-white dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-800'
                                    }`}
                                    onClick={() => setData('balance_type', 'custom')}
                                >
                                    <input
                                        type="radio"
                                        name="balance_type"
                                        value="custom"
                                        checked={data.balance_type === 'custom'}
                                        onChange={() => setData('balance_type', 'custom')}
                                        className="h-4 w-4 border-gray-300 text-orange-600 focus:ring-orange-500 dark:border-gray-600 dark:bg-gray-700"
                                    />
                                    <div className="flex-1">
                                        <h5 className="font-medium text-gray-900 dark:text-gray-100">
                                            Percentuali personalizzate
                                        </h5>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Configurare percentuali specifiche per ogni membro (es. 30/70, 40/60)
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <p className="mt-3 text-xs text-orange-600 dark:text-orange-400">
                                💡 Potrai sempre modificare queste impostazioni dopo aver aggiunto i membri alla household.
                            </p>
                        </div>
                    )}

                    <InputError message={errors.financial_management_type} className="mt-2" />
                    
                    <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        💡 Potrai sempre cambiare questa impostazione in seguito dalle impostazioni della household.
                    </p>
                </div>

                <div className="mt-6 flex justify-end">
                    <PrimaryButton disabled={processing}>
                        Crea Household
                    </PrimaryButton>
                </div>
            </form>
        </AuthenticatedSimpleLayout>
    );
}
