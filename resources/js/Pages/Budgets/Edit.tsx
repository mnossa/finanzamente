import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { FormEventHandler } from 'react';
import PageHeader from '@/Components/PageHeader';

interface Category {
    id: number;
    name: string;
    icon: string | null;
}

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Budget {
    id: number;
    category_id: number;
    amount: number;
    currency_code: string;
    period_start: string;
    period_end: string;
    description: string | null;
}

interface EditProps {
    budget: Budget;
    categories: Category[];
    currencies: Currency[];
}

export default function Edit({ budget, categories, currencies }: EditProps) {
    const { data, setData, put, processing, errors } = useForm({
        category_id: budget.category_id.toString(),
        amount: budget.amount.toString(),
        currency_code: budget.currency_code,
        period_start: budget.period_start,
        period_end: budget.period_end,
        description: budget.description || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('budgets.update', budget.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Modifica Budget"
                    backLink={route('budgets.index')}
                />
            }
        >
            <Head title="Modifica Budget" />

            <PageContent maxWidth="3xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Budget" icon={<span className="text-sm leading-none">✏️</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Modifica budget</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Aggiorna categoria, importo e periodo di riferimento.</p>
                        </header>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            <div className="space-y-6">
                                {/* Categoria */}
                                <div>
                                    <InputLabel htmlFor="category_id" value="Categoria *" />
                                    <select
                                        id="category_id"
                                        value={data.category_id}
                                        onChange={(e) =>
                                            setData('category_id', e.target.value)
                                        }
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        <option value="">Seleziona una categoria</option>
                                        {categories.map((cat) => (
                                            <option key={cat.id} value={cat.id}>
                                                {cat.icon || '📁'} {cat.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.category_id}
                                        className="mt-2"
                                    />
                                </div>

                                {/* Importo e Valuta */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="amount" value="Importo *" />
                                        <TextInput
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={data.amount}
                                            className="mt-1 block w-full"
                                            onChange={(e) =>
                                                setData('amount', e.target.value)
                                            }
                                            required
                                            placeholder="0,00"
                                        />
                                        <InputError message={errors.amount} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel
                                            htmlFor="currency_code"
                                            value="Valuta *"
                                        />
                                        <select
                                            id="currency_code"
                                            value={data.currency_code}
                                            onChange={(e) =>
                                                setData('currency_code', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            required
                                        >
                                            {currencies.map((curr) => (
                                                <option key={curr.code} value={curr.code}>
                                                    {curr.symbol} - {curr.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.currency_code}
                                            className="mt-2"
                                        />
                                    </div>
                                </div>

                                {/* Periodo */}
                                <div>
                                    <InputLabel value="Periodo *" />
                                    <div className="mt-2 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel
                                                htmlFor="period_start"
                                                value="Data inizio"
                                            />
                                            <TextInput
                                                id="period_start"
                                                type="date"
                                                value={data.period_start}
                                                className="mt-1 block w-full"
                                                onChange={(e) =>
                                                    setData('period_start', e.target.value)
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.period_start}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel
                                                htmlFor="period_end"
                                                value="Data fine"
                                            />
                                            <TextInput
                                                id="period_end"
                                                type="date"
                                                value={data.period_end}
                                                className="mt-1 block w-full"
                                                onChange={(e) =>
                                                    setData('period_end', e.target.value)
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.period_end}
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Descrizione */}
                                <div>
                                    <InputLabel htmlFor="description" value="Note" />
                                    <textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData('description', e.target.value)
                                        }
                                        rows={3}
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="Note opzionali sul budget..."
                                    />
                                    <InputError
                                        message={errors.description}
                                        className="mt-2"
                                    />
                                </div>
                            </div>

                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('budgets.index')}
                                    className="rounded-lg px-4 py-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    Salva Modifiche
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
