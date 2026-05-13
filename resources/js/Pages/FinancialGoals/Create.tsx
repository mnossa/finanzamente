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
import { goals } from '@/utils/analytics';
import clsx from 'clsx';
import { FormEventHandler, useState } from 'react';
import PageHeader from '@/Components/PageHeader';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface CreateProps {
    currencies: Currency[];
    suggestedIcons: string[];
}

export default function Create({ currencies, suggestedIcons }: CreateProps) {
    const [showIconPicker, setShowIconPicker] = useState(false);
    
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        target_amount: '',
        target_date: '',
        currency_code: 'EUR',
        icon: '🎯',
        color: '#6366f1',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('financial-goals.store'), {
            onSuccess: () => goals.created(!!data.target_date),
        });
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
                <PageHeader
                    title="Nuovo Obiettivo"
                    backLink={route('financial-goals.index')}
                />
            }
        >
            <Head title="Nuovo Obiettivo" />

            <PageContent maxWidth="2xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Obiettivi" icon={<span className="text-sm leading-none">🎯</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Nuovo obiettivo finanziario</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Definisci target, scadenza e stile visuale per il tuo traguardo.</p>
                        </header>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
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
                                            name="name"
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
                                                aria-label={`Seleziona il colore ${color}`}
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

                                {/* Importo Target e Valuta */}
                                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="target_amount" value="Importo Obiettivo *" />
                                        <div className="relative mt-2">
                                            <TextInput
                                                id="target_amount"
                                                name="target_amount"
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
                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('financial-goals.index')}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Creazione...' : '🎯 Crea Obiettivo'}
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
