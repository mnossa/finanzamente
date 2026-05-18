import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import TagCreateGuided from './TagCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { FormEventHandler } from 'react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';

const PRESET_COLORS = [
    '#ef4444', // red
    '#f97316', // orange
    '#f59e0b', // amber
    '#eab308', // yellow
    '#84cc16', // lime
    '#22c55e', // green
    '#14b8a6', // teal
    '#06b6d4', // cyan
    '#0ea5e9', // sky
    '#3b82f6', // blue
    '#6366f1', // indigo
    '#8b5cf6', // violet
    '#a855f7', // purple
    '#d946ef', // fuchsia
    '#ec4899', // pink
    '#f43f5e', // rose
];

export default function Create() {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features)) {
        return (
            <AuthenticatedLayout
                header={
                    <PageHeader
                        title="Nuovo Tag"
                        backLink={route('tags.index')}
                    />
                }
            >
                <Head title="Nuovo Tag" />
                <PageContent maxWidth="2xl">
                    <TagCreateGuided />
                </PageContent>
            </AuthenticatedLayout>
        );
    }

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        color: '#6366f1',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('tags.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuovo Tag"
                    backLink={route('tags.index')}
                />
            }
        >
            <Head title="Nuovo Tag" />

            <PageContent maxWidth="2xl">
                    <CardBox className="overflow-hidden shadow-sm">
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="p-6">
                            <div className="space-y-6">
                                {/* Nome */}
                                <div>
                                    <InputLabel htmlFor="name" value="Nome *" />
                                    <TextInput
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        className="mt-1 block w-full"
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                        autoFocus
                                        placeholder="es. Vacanze, Ristrutturazione, Regalo"
                                    />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                {/* Colore */}
                                <div>
                                    <InputLabel htmlFor="color" value="Colore" />
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {PRESET_COLORS.map((color) => (
                                            <button
                                                key={color}
                                                type="button"
                                                onClick={() => setData('color', color)}
                                                aria-label={`Seleziona il colore ${color}`}
                                                className={clsx(
                                                    'h-8 w-8 rounded-full transition-transform hover:scale-110',
                                                    data.color === color &&
                                                        'ring-2 ring-offset-2 ring-gray-900 dark:ring-white'
                                                )}
                                                style={{ backgroundColor: color }}
                                            />
                                        ))}
                                    </div>
                                    <div className="mt-3 flex items-center space-x-3">
                                        <input
                                            type="color"
                                            value={data.color}
                                            onChange={(e) => setData('color', e.target.value)}
                                            className="h-10 w-10 cursor-pointer rounded border-0"
                                        />
                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                            Oppure scegli un colore personalizzato
                                        </span>
                                    </div>
                                    <InputError message={errors.color} className="mt-2" />
                                </div>

                                {/* Anteprima */}
                                <div>
                                    <InputLabel value="Anteprima" />
                                    <div className="mt-2 flex items-center space-x-3">
                                        <div
                                            className="flex h-10 w-10 items-center justify-center rounded-full"
                                            style={{ backgroundColor: data.color }}
                                        >
                                            <span className="text-lg text-white">🏷️</span>
                                        </div>
                                        <span
                                            className="rounded-full px-3 py-1 text-sm font-medium text-white"
                                            style={{ backgroundColor: data.color }}
                                        >
                                            {data.name || 'Nome tag'}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('tags.index')}
                                    className="rounded-lg px-4 py-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    Crea Tag
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
