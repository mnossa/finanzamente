import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedSimpleLayout from '@/Layouts/AuthenticatedSimpleLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
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

                <div className="mt-6 flex justify-end">
                    <PrimaryButton disabled={processing}>
                        Crea Household
                    </PrimaryButton>
                </div>
            </form>
        </AuthenticatedSimpleLayout>
    );
}
