import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';

interface Category {
    id: number;
    name: string;
    type: string;
    color: string | null;
    icon: string | null;
    is_fixed_expense: boolean;
}

interface CategoryTypes {
    [key: string]: string;
}

interface EditProps {
    category: Category;
    categoryTypes: CategoryTypes;
}

function getCategoryTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        income: '📈',
        expense: '📉',
    };
    return icons[type] || '📁';
}

export default function Edit({ category, categoryTypes }: EditProps) {
    const { data, setData, patch, processing, errors } = useForm({
        name: category.name,
        type: category.type,
        color: category.color || '#6366f1',
        icon: category.icon || '💸',
        is_fixed_expense: category.is_fixed_expense || false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('categories.update', category.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={route('categories.index')}
                        className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ← Indietro
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Modifica Categoria
                    </h2>
                </div>
            }
        >
            <Head title="Modifica Categoria" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <form onSubmit={submit} className="space-y-6">
                            {/* Nome */}
                            <div>
                                <InputLabel htmlFor="name" value="Nome della categoria" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="es. Stipendio, Affitto, Spesa, ecc."
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} className="mt-2" />
                            </div>

                            {/* Tipo */}
                            <div>
                                <InputLabel htmlFor="type" value="Tipo di categoria" />
                                <div className="mt-2 grid grid-cols-2 gap-3">
                                    {Object.entries(categoryTypes).map(([value, label]) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => setData('type', value)}
                                            className={clsx(
                                                'flex items-center space-x-2 rounded-lg border-2 p-3 text-left transition-colors',
                                                data.type === value
                                                    ? value === 'income'
                                                        ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                                        : 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                            )}
                                        >
                                            <span className="text-2xl">
                                                {getCategoryTypeIcon(value)}
                                            </span>
                                            <span className="text-sm font-medium text-gray-900 dark:text-white">
                                                {label}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                <InputError message={errors.type} className="mt-2" />
                            </div>

                            {/* Spesa Fissa (solo per categorie di spesa) */}
                            {data.type === 'expense' && (
                                <div>
                                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                                        <label className="flex items-start space-x-3">
                                            <input
                                                type="checkbox"
                                                checked={data.is_fixed_expense}
                                                onChange={(e) => setData('is_fixed_expense', e.target.checked)}
                                                className="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <div className="flex-1">
                                                <span className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                    📌 Spesa Fissa
                                                </span>
                                                <p className="mt-1 text-xs text-blue-700 dark:text-blue-200">
                                                    Marca come spesa fissa per tracciare i contributi nelle household con bilanciamento debiti 
                                                    (es. affitto, bollette, abbonamenti ricorrenti)
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                    <InputError message={errors.is_fixed_expense} className="mt-2" />
                                </div>
                            )}

                            {/* Icona e Colore */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="icon" value="Icona" />
                                    <TextInput
                                        id="icon"
                                        type="text"
                                        className="mt-1 block w-full"
                                        value={data.icon}
                                        onChange={(e) => setData('icon', e.target.value)}
                                        maxLength={10}
                                    />
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Emoji o simbolo breve (es. 💰, 🏠, 🚗)
                                    </p>
                                    <InputError message={errors.icon} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="color" value="Colore" />
                                    <div className="mt-1 flex items-center space-x-3">
                                        <input
                                            id="color"
                                            type="color"
                                            className="h-10 w-14 cursor-pointer rounded border border-gray-300 p-1 dark:border-gray-700"
                                            value={data.color}
                                            onChange={(e) => setData('color', e.target.value)}
                                        />
                                        <TextInput
                                            type="text"
                                            className="block flex-1"
                                            value={data.color}
                                            onChange={(e) => setData('color', e.target.value)}
                                            maxLength={20}
                                        />
                                    </div>
                                    <InputError message={errors.color} className="mt-2" />
                                </div>
                            </div>

                            {/* Anteprima */}
                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <p className="mb-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                                    Anteprima
                                </p>
                                <div className="flex items-center space-x-3">
                                    <span
                                        className="flex h-10 w-10 items-center justify-center rounded-full text-xl"
                                        style={{ backgroundColor: data.color + '20' }}
                                    >
                                        {data.icon || '📁'}
                                    </span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {data.name || 'Nome categoria'}
                                    </span>
                                    <span
                                        className={clsx(
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            data.type === 'income'
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                        )}
                                    >
                                        {categoryTypes[data.type]}
                                    </span>
                                    {data.is_fixed_expense && (
                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                            📋 Spesa Fissa
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Azioni */}
                            <div className="flex items-center justify-end space-x-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                                <Link
                                    href={route('categories.index')}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : 'Salva Modifiche'}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
