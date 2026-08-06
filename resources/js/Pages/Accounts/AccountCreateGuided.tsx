import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';

const STEPS = [{ label: 'Nome' }, { label: 'Tipo' }, { label: 'Saldo' }, { label: 'Dettaglio' }];

interface Props {
    accountTypes: Record<string, string>;
    defaultCurrency: string;
}

export default function AccountCreateGuided({ accountTypes, defaultCurrency }: Props) {
    const [step, setStep] = useState(0);
    const types = Object.entries(accountTypes);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        type: types[0]?.[0] ?? 'bank',
        initial_balance: '0',
        interest_rate: '',
        ticket_unit_value: '',
        external_url: '',
        currency_code: defaultCurrency,
        is_private: false,
    });

    const isSavingsDeposit = data.type === 'savings_deposit';
    const isMealVoucher = data.type === 'meal_voucher';
    const isPensionFund = data.type === 'pension_fund';
    const shouldShowDetailStep = isSavingsDeposit || isMealVoucher || isPensionFund;
    const lastStep = shouldShowDetailStep ? 3 : 2;

    const meta = [
        { title: 'Come si chiama il conto?', subtitle: 'Es. Conto corrente, Carta, Risparmi' },
        { title: 'Che tipo di conto è?', subtitle: "Scegli l'opzione più adatta." },
        {
            title: isPensionFund ? 'Qual è la posizione attuale?' : 'Qual è il saldo iniziale?',
            subtitle: isPensionFund
                ? 'Il montante che vedi nel portale del fondo.'
                : 'Puoi usare 0 se non sei sicuro.',
        },
        isMealVoucher
            ? { title: 'Valore di un ticket', subtitle: 'Importo in euro di un singolo buono pasto.' }
            : isPensionFund
                ? { title: 'Area riservata', subtitle: 'Link opzionale al portale del fondo.' }
                : { title: 'Tasso del conto deposito', subtitle: 'Solo se scegli conto deposito. Altrimenti puoi lasciare vuoto.' },
    ][step];

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                if (step < lastStep) {
                    if (step === 0 && !data.name.trim()) return;
                    setStep((s) => s + 1);
                } else {
                    if (isSavingsDeposit && !data.interest_rate.trim()) return;
                    if (isMealVoucher && !data.ticket_unit_value.trim()) return;
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
                {step === 3 && isSavingsDeposit && (
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
                            required={isSavingsDeposit}
                        />
                        <InputError message={errors.interest_rate} className="mt-2" />
                    </div>
                )}
                {step === 3 && isMealVoucher && (
                    <div>
                        <InputLabel htmlFor="ticket_unit_value" value="Valore di un ticket" />
                        <TextInput
                            id="ticket_unit_value"
                            type="number"
                            step="0.01"
                            min="0.01"
                            className="mt-1 block w-full"
                            value={data.ticket_unit_value}
                            onChange={(e) => setData('ticket_unit_value', e.target.value)}
                            placeholder="es. 8.00"
                            required={isMealVoucher}
                        />
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            I ticket disponibili si calcolano dal saldo del conto.
                        </p>
                        <InputError message={errors.ticket_unit_value} className="mt-2" />
                    </div>
                )}
                {step === 3 && isPensionFund && (
                    <div>
                        <InputLabel htmlFor="external_url" value="URL area riservata (opzionale)" />
                        <TextInput
                            id="external_url"
                            type="url"
                            className="mt-1 block w-full"
                            value={data.external_url}
                            onChange={(e) => setData('external_url', e.target.value)}
                            placeholder="https://..."
                        />
                        <InputError message={errors.external_url} className="mt-2" />
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
