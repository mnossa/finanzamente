import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import TextInput from '@/Components/TextInput';
import EmojiPicker from '@/Components/EmojiPicker';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import CategoryCreateGuided from './CategoryCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { cats } from '@/utils/analytics';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';

interface CategoryTypes {
    [key: string]: string;
}

interface CreateProps {
    categoryTypes: CategoryTypes;
}

function getCategoryTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        income: '📈',
        expense: '📉',
    };
    return icons[type] || '📁';
}

export default function Create({ categoryTypes }: CreateProps) {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features)) {
        return (
            <AuthenticatedLayout
                header={
                    <PageHeader
                        title="Nuova Categoria"
                        mobileTitle="Categoria"
                        backLink={route('categories.index')}
                    />
                }
            >
                <Head title="Nuova Categoria" />
                <PageContent maxWidth="3xl">
                    <CategoryCreateGuided categoryTypes={categoryTypes} />
                </PageContent>
            </AuthenticatedLayout>
        );
    }

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: 'expense',
        color: '#6366f1',
        icon: '💸',
        is_fixed_expense: false,
        exclude_from_lifestyle_score: false,
        expense_distribution: null as 'needs' | 'wants' | 'investments' | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('categories.store'), {
            onSuccess: () => cats.created(data.type as 'expense' | 'income'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuova Categoria"
                    backLink={route('categories.index')}
                />

            }
        >
            <Head title="Nuova Categoria" />

            <PageContent maxWidth="3xl">
                    <CardBox>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            {/* Nome */}
                            <div>
                                <InputLabel htmlFor="name" value="Nome della categoria" />
                                <TextInput
                                    id="name"
                                    name="name"
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

                            {/* Escludi dal Lifestyle Score (solo per categorie di spesa) */}
                            {data.type === 'expense' && (
                                <div>
                                    <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                                        <label className="flex items-start space-x-3">
                                            <input
                                                type="checkbox"
                                                checked={data.exclude_from_lifestyle_score}
                                                onChange={(e) => setData('exclude_from_lifestyle_score', e.target.checked)}
                                                className="mt-0.5 h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <div className="flex-1">
                                                <span className="text-sm font-medium text-purple-900 dark:text-purple-100">
                                                    📈 Escludi dal Lifestyle Score
                                                </span>
                                                <p className="mt-1 text-xs text-purple-700 dark:text-purple-200">
                                                    Le spese in questa categoria non incidono sul tuo Lifestyle Inflation Score
                                                    (es. investimenti, risparmio, previdenza)
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                    <InputError message={errors.exclude_from_lifestyle_score} className="mt-2" />
                                </div>
                            )}

                            {/* Gruppo di spesa per la distribuzione (solo per categorie di spesa) */}
                            {data.type === 'expense' && (
                                <div>
                                    <InputLabel value="Gruppo di spesa" />
                                    <p className="mb-2 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Classifica questa categoria per vederla raggruppata nel widget Distribuzione Spese.
                                    </p>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {([
                                            { value: null,          label: 'Nessuno',       icon: '—',  ring: 'ring-gray-300   dark:ring-gray-600',   bg: 'bg-gray-50   dark:bg-gray-700/30'   },
                                            { value: 'needs',       label: 'Necessità',     icon: '🏠', ring: 'ring-blue-400   dark:ring-blue-500',    bg: 'bg-blue-50   dark:bg-blue-900/20'   },
                                            { value: 'wants',       label: 'Extra',         icon: '🎯', ring: 'ring-violet-400 dark:ring-violet-500',  bg: 'bg-violet-50 dark:bg-violet-900/20' },
                                            { value: 'investments', label: 'Investimenti',  icon: '📈', ring: 'ring-emerald-400 dark:ring-emerald-500', bg: 'bg-emerald-50 dark:bg-emerald-900/20' },
                                        ] as const).map((opt) => (
                                            <button
                                                key={String(opt.value)}
                                                type="button"
                                                onClick={() => setData('expense_distribution', opt.value)}
                                                className={clsx(
                                                    'flex flex-col items-center gap-1 rounded-lg border-2 p-3 text-center transition-colors',
                                                    data.expense_distribution === opt.value
                                                        ? `ring-2 ${opt.ring} ${opt.bg} border-transparent`
                                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                                )}
                                            >
                                                <span className="text-xl">{opt.icon}</span>
                                                <span className="text-xs font-medium text-gray-700 dark:text-gray-300">{opt.label}</span>
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={errors.expense_distribution} className="mt-2" />
                                </div>
                            )}

                            {/* Icona e Colore */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel htmlFor="icon" value="Icona" />
                                    <EmojiPicker
                                        value={data.icon}
                                        onChange={(emoji) => setData('icon', emoji)}
                                        className="mt-1"
                                    />
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
                                            📌 Spesa Fissa
                                        </span>
                                    )}
                                    {data.exclude_from_lifestyle_score && (
                                        <span className="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                            📈 Escluso da Score
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* Azioni */}
                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('categories.index')}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : 'Crea Categoria'}
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
