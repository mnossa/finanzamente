import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { transfers } from '@/utils/analytics';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

interface Account {
    id: number;
    name: string;
    currency_code: string;
    current_balance: number;
}

interface Props {
    accounts: Account[];
}

const STEP_COUNT = 7;

function formatCurrency(amount: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(amount);
}

export default function TransferCreateGuided({ accounts }: Props) {
    const [step, setStep] = useState(0);
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        source_account_id: '',
        destination_account_id: '',
        amount: '',
        exchange_rate: '',
        fee: '',
        date: today,
        description: '',
        is_private: false,
    });

    const sourceAccount = useMemo(
        () => accounts.find((a) => a.id === Number(data.source_account_id)),
        [accounts, data.source_account_id],
    );
    const destAccount = useMemo(
        () => accounts.find((a) => a.id === Number(data.destination_account_id)),
        [accounts, data.destination_account_id],
    );
    const isCrossCurrency = Boolean(
        sourceAccount && destAccount && sourceAccount.currency_code !== destAccount.currency_code,
    );

    const visualSteps = useMemo(() => {
        if (isCrossCurrency) {
            return [0, 1, 2, 3, 4, 5, 6];
        }
        return [0, 1, 2, 4, 5, 6];
    }, [isCrossCurrency]);
    const visualStep = visualSteps[step] ?? 0;
    const visualCount = visualSteps.length;

    const estimatedDestAmount = useMemo(() => {
        if (!data.amount || !sourceAccount || !destAccount) return null;
        const amount = Number.parseFloat(data.amount);
        if (Number.isNaN(amount)) return null;
        if (!isCrossCurrency) return amount;
        if (!data.exchange_rate) return null;
        const rate = Number.parseFloat(data.exchange_rate);
        if (Number.isNaN(rate)) return null;
        return amount * rate;
    }, [data.amount, data.exchange_rate, sourceAccount, destAccount, isCrossCurrency]);

    const canNext = (): boolean => {
        if (visualStep === 0) return data.source_account_id !== '';
        if (visualStep === 1) return data.destination_account_id !== '';
        if (visualStep === 2) return data.amount !== '' && Number(data.amount) > 0;
        if (visualStep === 3) return data.exchange_rate !== '' && Number(data.exchange_rate) > 0;
        if (visualStep === 4) return true;
        if (visualStep === 5) return true;
        return true;
    };

    const goNext = () => {
        if (step < visualCount - 1 && canNext()) setStep((s) => s + 1);
    };

    const stepMetaMap: Record<number, { title: string; subtitle: string }> = {
        0: { title: 'Conto di origine', subtitle: 'Da dove partono i fondi?' },
        1: { title: 'Conto di destinazione', subtitle: 'Dove devono arrivare?' },
        2: { title: 'Importo', subtitle: "Quanto vuoi trasferire dall'origine?" },
        3: { title: 'Tasso di cambio', subtitle: 'Richiesto per valute diverse.' },
        4: { title: 'Data e commissione', subtitle: 'Commissione opzionale.' },
        5: { title: 'Dettagli extra', subtitle: 'Descrizione e privacy opzionali.' },
        6: { title: 'Conferma', subtitle: 'Controlla e registra il trasferimento.' },
    };
    const stepMeta = stepMetaMap[visualStep] ?? stepMetaMap[0];

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(event) => {
                event.preventDefault();
                if (step < visualCount - 1) {
                    goNext();
                    return;
                }
                post(route('transfers.store'), {
                    onSuccess: () => transfers.created(),
                });
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(visualCount)}
                currentStep={step}
                title={stepMeta.title}
                subtitle={stepMeta.subtitle}
            >
                {visualStep === 0 && (
                    <div className="space-y-2">
                        {accounts
                            .filter((a) => a.id !== Number(data.destination_account_id))
                            .map((account) => (
                                <button
                                    key={account.id}
                                    type="button"
                                    onClick={() => setData('source_account_id', String(account.id))}
                                    className={clsx(
                                        'w-full rounded-xl border-2 p-3 text-left',
                                        data.source_account_id === String(account.id)
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700',
                                    )}
                                >
                                    <div className="font-medium">{account.name}</div>
                                    <div className="text-xs text-gray-500">
                                        {formatCurrency(account.current_balance, account.currency_code)}
                                    </div>
                                </button>
                            ))}
                        <InputError message={errors.source_account_id} className="mt-2" />
                    </div>
                )}

                {visualStep === 1 && (
                    <div className="space-y-2">
                        {accounts
                            .filter((a) => a.id !== Number(data.source_account_id))
                            .map((account) => (
                                <button
                                    key={account.id}
                                    type="button"
                                    onClick={() => setData('destination_account_id', String(account.id))}
                                    className={clsx(
                                        'w-full rounded-xl border-2 p-3 text-left',
                                        data.destination_account_id === String(account.id)
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700',
                                    )}
                                >
                                    <div className="font-medium">{account.name}</div>
                                    <div className="text-xs text-gray-500">{account.currency_code}</div>
                                </button>
                            ))}
                        <InputError message={errors.destination_account_id} className="mt-2" />
                    </div>
                )}

                {visualStep === 2 && (
                    <div>
                        <InputLabel htmlFor="amount" value="Importo" />
                        <div className="mt-1 flex items-center gap-2">
                            <TextInput
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="block w-full"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                autoFocus
                            />
                            {sourceAccount && <span className="text-sm text-gray-500">{sourceAccount.currency_code}</span>}
                        </div>
                        <InputError message={errors.amount} className="mt-2" />
                    </div>
                )}

                {visualStep === 3 && (
                    <div>
                        <InputLabel
                            htmlFor="exchange_rate"
                            value={`Tasso di cambio (1 ${sourceAccount?.currency_code} = ? ${destAccount?.currency_code})`}
                        />
                        <TextInput
                            id="exchange_rate"
                            type="number"
                            step="0.00000001"
                            min="0.00000001"
                            className="mt-1 block w-full"
                            value={data.exchange_rate}
                            onChange={(e) => setData('exchange_rate', e.target.value)}
                            autoFocus
                        />
                        <InputError message={errors.exchange_rate} className="mt-2" />
                    </div>
                )}

                {visualStep === 4 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="date" value="Data" />
                            <TextInput
                                id="date"
                                type="date"
                                className="mt-1 block w-full"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                            />
                            <InputError message={errors.date} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="fee" value="Commissione (opzionale)" />
                            <TextInput
                                id="fee"
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.fee}
                                onChange={(e) => setData('fee', e.target.value)}
                            />
                            <InputError message={errors.fee} className="mt-2" />
                        </div>
                    </div>
                )}

                {visualStep === 5 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                            <textarea
                                id="description"
                                rows={2}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                            <InputError message={errors.description} className="mt-2" />
                        </div>
                        <label className="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                                checked={data.is_private}
                                onChange={(e) => setData('is_private', e.target.checked)}
                            />
                            <span className="text-sm">Trasferimento privato</span>
                        </label>
                    </div>
                )}

                {visualStep === 6 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Da</dt>
                            <dd>{sourceAccount?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">A</dt>
                            <dd>{destAccount?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Importo</dt>
                            <dd className="font-medium">
                                {data.amount || '0'} {sourceAccount?.currency_code || ''}
                            </dd>
                        </div>
                        {isCrossCurrency && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Tasso</dt>
                                <dd>{data.exchange_rate || '-'}</dd>
                            </div>
                        )}
                        {estimatedDestAmount !== null && destAccount && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Arrivo stimato</dt>
                                <dd className="font-medium">{formatCurrency(estimatedDestAmount, destAccount.currency_code)}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Data</dt>
                            <dd>{formatItalianDate(data.date)}</dd>
                        </div>
                        {data.fee && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Commissione</dt>
                                <dd>{data.fee}</dd>
                            </div>
                        )}
                        {data.description && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Descrizione</dt>
                                <dd className="text-right">{data.description}</dd>
                            </div>
                        )}
                        {data.is_private && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Privacy</dt>
                                <dd>Privato</dd>
                            </div>
                        )}
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={visualCount}
                    processing={processing}
                    canNext={canNext()}
                    onBack={() => setStep((s) => Math.max(0, s - 1))}
                    onSkip={visualStep === 4 || visualStep === 5 ? goNext : undefined}
                    cancelHref={route('transfers.index')}
                    submitLabel="Trasferisci"
                />
            </GuidedFormWizard>
        </form>
    );
}
