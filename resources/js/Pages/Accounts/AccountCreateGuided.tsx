import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';

const STEPS = [{ label: 'Nome' }, { label: 'Tipo' }, { label: 'Saldo' }, { label: 'Interesse' }];

interface Props {
    accountTypes: Record<string, string>;
    defaultCurrency: string;
}

export default function AccountCreateGuided({ accountTypes, defaultCurrency }: Props) {
    const [step, setStep] = useState(0);
    const types = Object.entries(accountTypes);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: types[0]?.[0] ?? 'checking',
        initial_balance: '0',
        interest_rate: '',
        currency_code: defaultCurrency,
        is_private: false,
    });

    const meta = [
        { title: 'Come si chiama il conto?', subtitle: 'Es. Conto corrente, Carta, Risparmi' },
        { title: 'Che tipo di conto è?', subtitle: 'Scegli l\'opzione più adatta.' },
        { title: 'Qual è il saldo iniziale?', subtitle: 'Puoi usare 0 se non sei sicuro.' },
        { title: 'Tasso del conto deposito', subtitle: 'Solo se scegli conto deposito. Altrimenti puoi lasciare vuoto.' },
    ][step];
    const shouldShowInterestStep = data.type === 'savings_deposit';
    const lastStep = shouldShowInterestStep ? 3 : 2;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                if (step < lastStep) {
                    if (step === 0 && !data.name.trim()) return;
                    setStep((s) => s + 1);
                } else {
                    if (shouldShowInterestStep && !data.interest_rate.trim()) return;
                    post(route('accounts.store'));
                }
            }}
        >
            <GuidedFormWizard steps={STEPS} currentStep={step} title={meta.title} subtitle={meta.subtitle}>
                {step === 0 && (
                    <div>
                        <InputLabel htmlFor="name" value="Nome conto" />
                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>
                )}
                {step === 1 && (
                    <div className="grid grid-cols-2 gap-2">
                        {types.map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setData('type', key)}
                                className={clsx(
                                    'rounded-xl border p-3 text-sm font-medium',
                                    data.type === key
                                        ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                        : 'border-gray-200 dark:border-gray-600',
                                )}
                            >
                                <span className="mr-1">{getAccountTypeIcon(key)}</span>
                                {label}
                            </button>
                        ))}
                    </div>
                )}
                {step === 2 && (
                    <div>
                        <InputLabel htmlFor="initial_balance" value="Saldo iniziale" />
                        <TextInput
                            id="initial_balance"
                            type="number"
                            step="0.01"
                            className="mt-1 block w-full"
                            value={data.initial_balance}
                            onChange={(e) => setData('initial_balance', e.target.value)}
                        />
                        <InputError message={errors.initial_balance} className="mt-2" />
                    </div>
                )}
                {step === 3 && shouldShowInterestStep && (
                    <div>
                        <InputLabel htmlFor="interest_rate" value="Tasso interesse annuo (%)" />
                        <TextInput
                            id="interest_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            className="mt-1 block w-full"
                            value={data.interest_rate}
                            onChange={(e) => setData('interest_rate', e.target.value)}
                            disabled={data.type !== 'savings_deposit'}
                            required={data.type === 'savings_deposit'}
                        />
                        {data.type !== 'savings_deposit' && (
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Non necessario: il conto selezionato non e un conto deposito.
                            </p>
                        )}
                        <InputError message={errors.interest_rate} className="mt-2" />
                    </div>
                )}
                <div className="mt-8 flex justify-end">
                    <PrimaryButton type="submit" disabled={processing}>
                        {step === lastStep ? (processing ? 'Salvataggio...' : 'Crea conto') : 'Avanti'}
                    </PrimaryButton>
                </div>
            </GuidedFormWizard>
        </form>
    );
}
