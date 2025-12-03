import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler, useState } from 'react';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface FinancialGoal {
    id: number;
    name: string;
    description: string | null;
    target_amount: number;
    current_amount: number;
    currency_code: string;
    target_date: string | null;
    status: string;
    icon: string | null;
    color: string | null;
}

interface Statuses {
    [key: string]: string;
}

interface EditProps {
    goal: FinancialGoal;
    currencies: Currency[];
    suggestedIcons: string[];
    statuses: Statuses;
}

export default function Edit({ goal, currencies, suggestedIcons, statuses }: EditProps) {
    const [showIconPicker, setShowIconPicker] = useState(false);
    
    const { data, setData, put, processing, errors } = useForm({
        name: goal.name,
        description: goal.description || '',
        target_amount: goal.target_amount.toString(),
        current_amount: goal.current_amount.toString(),
        target_date: goal.target_date || '',
        currency_code: goal.currency_code,
        icon: goal.icon || '🎯',
        color: goal.color || '#6366f1',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('financial-goals.update', goal.id));
    };

    const selectIcon = (icon: string) => {
        setData('icon', icon);
        setShowIconPicker(false);
    };

    const colorPresets = [
        '#6366f1', // Indigo
        '#8b5cf6', // Violet
        '#ec4899', // Pink
        '#ef4444', // Red
        '#f97316', // Orange
        '#eab308', // Yellow
        '#22c55e', // Green
        '#14b8a6', // Teal
        '#06b6d4', // Cyan
        '#3b82f6', // Blue
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link
                        href={route('financial-goals.show', goal.id)}
                        className="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                        ←
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Modifica Obiettivo
                    </h2>
                </div>
            }
        >
            <Head title={`Modifica ${goal.name}`} />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <form onSubmit={submit}>
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="p-6">
                                {/* Icon e Nome */}
                                <div className="mb-6">
                                    <InputLabel value="Icona e Nome *" />
                                    <div className="mt-2 flex gap-3">
                                        <div className="relative">
                                            <button
                                                type="button"
                                                onClick={() => setShowIconPicker(!showIconPicker)}
                                                className="flex h-12 w-12 items-center justify-center rounded-xl border-2 border-gray-300 text-2xl transition-colors hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500"
                                                style={{ backgroundColor: `${data.color}20` }}
                                            >
                                                {data.icon}
                                            </button>
                                            
                                            {showIconPicker && (
                                                <div className="absolute left-0 top-14 z-10 w-64 rounded-xl bg-white p-4 shadow-lg dark:bg-gray-700">
                                                    <p className="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                                        Seleziona un'icona
                                                    </p>
                                                    <div className="grid grid-cols-6 gap-2">
                                                        {suggestedIcons.map((icon) => (
                                                            <button
                                                                key={icon}
                                                                type="button"
                                                                onClick={() => selectIcon(icon)}
                                                                className={clsx(
                                                                    'flex h-9 w-9 items-center justify-center rounded-lg text-xl transition-colors',
                                                                    data.icon === icon
                                                                        ? 'bg-emerald-100 dark:bg-emerald-900'
                                                                        : 'hover:bg-gray-100 dark:hover:bg-gray-600'
                                                                )}
                                                            >
                                                                {icon}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                        
                                        <TextInput
                                            id="name"
                                            type="text"
                                            className="flex-1"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="Es. Vacanza estiva, Nuova auto, Fondo emergenza..."
                                            required
                                        />
                                    </div>
                                    <InputError message={errors.name} className="mt-2" />
                                    <InputError message={errors.icon} className="mt-2" />
                                </div>

                                {/* Colore */}
                                <div className="mb-6">
                                    <InputLabel value="Colore" />
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {colorPresets.map((color) => (
                                            <button
                                                key={color}
                                                type="button"
                                                onClick={() => setData('color', color)}
                                                className={clsx(
                                                    'h-8 w-8 rounded-full transition-transform',
                                                    data.color === color && 'scale-125 ring-2 ring-offset-2 ring-gray-400'
                                                )}
                                                style={{ backgroundColor: color }}
                                            />
                                        ))}
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="color"
                                                value={data.color}
                                                onChange={(e) => setData('color', e.target.value)}
                                                className="h-8 w-8 cursor-pointer rounded-full border-0"
                                            />
                                            <span className="text-sm text-gray-500 dark:text-gray-400">
                                                Personalizzato
                                            </span>
                                        </div>
                                    </div>
                                    <InputError message={errors.color} className="mt-2" />
                                </div>

                                {/* Descrizione */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="description" value="Descrizione" />
                                    <textarea
                                        id="description"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Descrivi il tuo obiettivo..."
                                        rows={3}
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Importi */}
                                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="target_amount" value="Importo Obiettivo *" />
                                        <div className="relative mt-2">
                                            <TextInput
                                                id="target_amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                className="w-full pr-12"
                                                value={data.target_amount}
                                                onChange={(e) => setData('target_amount', e.target.value)}
                                                placeholder="0,00"
                                                required
                                            />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                {currencies.find((c) => c.code === data.currency_code)?.symbol || '€'}
                                            </span>
                                        </div>
                                        <InputError message={errors.target_amount} className="mt-2" />
                                    </div>
                                    
                                    <div>
                                        <InputLabel htmlFor="current_amount" value="Importo Attuale" />
                                        <div className="relative mt-2">
                                            <TextInput
                                                id="current_amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="w-full pr-12"
                                                value={data.current_amount}
                                                onChange={(e) => setData('current_amount', e.target.value)}
                                                placeholder="0,00"
                                            />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                {currencies.find((c) => c.code === data.currency_code)?.symbol || '€'}
                                            </span>
                                        </div>
                                        <InputError message={errors.current_amount} className="mt-2" />
                                    </div>
                                </div>

                                {/* Valuta */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="currency_code" value="Valuta" />
                                    <select
                                        id="currency_code"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.currency_code}
                                        onChange={(e) => setData('currency_code', e.target.value)}
                                    >
                                        {currencies.map((currency) => (
                                            <option key={currency.code} value={currency.code}>
                                                {currency.symbol} - {currency.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.currency_code} className="mt-2" />
                                </div>

                                {/* Data Obiettivo */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="target_date" value="Data Obiettivo" />
                                    <TextInput
                                        id="target_date"
                                        type="date"
                                        className="mt-2 w-full"
                                        value={data.target_date}
                                        onChange={(e) => setData('target_date', e.target.value)}
                                    />
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Opzionale: quando vorresti raggiungere questo obiettivo?
                                    </p>
                                    <InputError message={errors.target_date} className="mt-2" />
                                </div>
                            </div>

                            {/* Footer */}
                            <div className="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                                <Link
                                    href={route('financial-goals.show', goal.id)}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : '💾 Salva Modifiche'}
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
