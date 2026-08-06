import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useEffect, useMemo, useState } from 'react';

interface Account {
    id: number;
    name: string;
    currency_code: string;
    current_balance: number;
    currency?: {
        code: string;
        symbol: string;
    };
}

interface Household {
    id: number;
    name: string;
    exclude_inter_transfers_from_stats: boolean;
}

interface Props {
    sourceAccounts: Account[];
    userHouseholds: Household[];
    activeHouseholdExcludesDefault: boolean;
}

const STEP_COUNT = 5;

function formatCurrency(amount: number, currency = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency }).format(amount);
}

export default function InterHouseholdTransferCreateGuided({
    sourceAccounts,
    userHouseholds,
    activeHouseholdExcludesDefault,
}: Props) {
    const [step, setStep] = useState(0);
    const [destAccounts, setDestAccounts] = useState<Account[]>([]);
    const [loadingAccounts, setLoadingAccounts] = useState(false);
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        source_account_id: '',
        dest_household_id: '',
        dest_account_id: '',
        dest_user_id: '',
        source_amount: '',
        dest_amount: '',
        source_currency: '',
        dest_currency: '',
        exchange_rate: '',
        fee: '',
        transfer_date: today,
        description: '',
        notes: '',
        exclude_from_stats: activeHouseholdExcludesDefault,
    });

    const sourceAccount = useMemo(
        () => sourceAccounts.find((account) => account.id === Number(data.source_account_id)),
        [sourceAccounts, data.source_account_id],
    );
    const destAccount = useMemo(
        () => destAccounts.find((account) => account.id === Number(data.dest_account_id)),
        [destAccounts, data.dest_account_id],
    );

    useEffect(() => {
        if (data.dest_household_id) {
            setLoadingAccounts(true);
            axios
                .get(route('households.accounts', data.dest_household_id))
                .then((response) => setDestAccounts(response.data))
                .catch(() => setDestAccounts([]))
                .finally(() => setLoadingAccounts(false));

            const destHousehold = userHouseholds.find((h) => h.id === Number(data.dest_household_id));
            const shouldExclude =
                activeHouseholdExcludesDefault || (destHousehold?.exclude_inter_transfers_from_stats ?? false);
            setData('exclude_from_stats', shouldExclude);
        } else {
            setDestAccounts([]);
            setData('dest_account_id', '');
        }
    }, [activeHouseholdExcludesDefault, data.dest_household_id, setData, userHouseholds]);

    useEffect(() => {
        if (sourceAccount) setData('source_currency', sourceAccount.currency_code);
    }, [sourceAccount, setData]);

    useEffect(() => {
        if (destAccount) setData('dest_currency', destAccount.currency_code);
    }, [destAccount, setData]);

    const isCrossCurrency =
        data.source_currency && data.dest_currency && data.source_currency !== data.dest_currency;

    useEffect(() => {
        if (isCrossCurrency && data.source_amount && data.exchange_rate) {
            const sourceAmount = Number.parseFloat(data.source_amount);
            const rate = Number.parseFloat(data.exchange_rate);
            if (!Number.isNaN(sourceAmount) && !Number.isNaN(rate) && rate > 0) {
                setData('dest_amount', (sourceAmount * rate).toFixed(2));
            }
        } else if (!isCrossCurrency && data.source_amount) {
            setData('dest_amount', data.source_amount);
        }
    }, [data.exchange_rate, data.source_amount, isCrossCurrency, setData]);

    const canNext = (): boolean => {
        if (step === 0) return Boolean(data.source_account_id);
        if (step === 1) return Boolean(data.dest_household_id) && Boolean(data.dest_account_id);
        if (step === 2) {
            if (!data.source_amount || Number(data.source_amount) <= 0) return false;
            if (isCrossCurrency) {
                return Boolean(data.exchange_rate) && Number(data.exchange_rate) > 0 && Boolean(data.dest_amount);
            }
            return true;
        }
        return true;
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) setStep((s) => s + 1);
    };

    const meta = [
        { title: 'Account sorgente', subtitle: 'Da quale account prelevi i fondi.' },
        { title: 'Destinazione household', subtitle: 'Seleziona household e account di arrivo.' },
        { title: 'Importi e cambio', subtitle: 'Compila importo e cambio se le valute differiscono.' },
        { title: 'Dettagli trasferimento', subtitle: 'Data, fee, note e opzioni statistiche.' },
        { title: 'Conferma', subtitle: 'Controlla tutto prima di creare.' },
    ][step];

    return (
        <form
            id={FM_MOBILE_PRIMARY_FORM_ID}
            onSubmit={(event) => {
                event.preventDefault();
                if (step < STEP_COUNT - 1) {
                    goNext();
                    return;
                }
                post(route('inter-household-transfers.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={meta.title}
                subtitle={meta.subtitle}
            >
                {step === 0 && (
                    <div className="max-h-[min(40vh,16rem)] space-y-2 overflow-y-auto">
                        {sourceAccounts.map((account) => (
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

                {step === 1 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="dest_household_id" value="Household destinataria" />
                            <select
                                id="dest_household_id"
                                value={data.dest_household_id}
                                onChange={(e) => setData('dest_household_id', e.target.value)}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option value="">Seleziona household</option>
                                {userHouseholds.map((household) => (
                                    <option key={household.id} value={household.id}>
                                        {household.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.dest_household_id} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="dest_account_id" value="Account destinatario" />
                            <select
                                id="dest_account_id"
                                value={data.dest_account_id}
                                onChange={(e) => setData('dest_account_id', e.target.value)}
                                disabled={!data.dest_household_id || loadingAccounts}
                                className={clsx(
                                    'mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                    (!data.dest_household_id || loadingAccounts) && 'cursor-not-allowed opacity-60',
                                )}
                            >
                                <option value="">
                                    {loadingAccounts
                                        ? 'Caricamento...'
                                        : destAccounts.length === 0
                                          ? 'Nessun account disponibile'
                                          : 'Seleziona account'}
                                </option>
                                {destAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.name} ({account.currency_code})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.dest_account_id} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="source_amount" value="Importo sorgente" />
                            <TextInput
                                id="source_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="mt-1 block w-full"
                                value={data.source_amount}
                                onChange={(e) => setData('source_amount', e.target.value)}
                            />
                            <InputError message={errors.source_amount} className="mt-2" />
                        </div>
                        {isCrossCurrency && (
                            <>
                                <div>
                                    <InputLabel htmlFor="exchange_rate" value="Tasso di cambio" />
                                    <TextInput
                                        id="exchange_rate"
                                        type="number"
                                        step="0.000001"
                                        min="0.000001"
                                        className="mt-1 block w-full"
                                        value={data.exchange_rate}
                                        onChange={(e) => setData('exchange_rate', e.target.value)}
                                    />
                                    <InputError message={errors.exchange_rate} className="mt-2" />
                                </div>
                                <div>
                                    <InputLabel htmlFor="dest_amount" value="Importo destinazione" />
                                    <TextInput
                                        id="dest_amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        className="mt-1 block w-full"
                                        value={data.dest_amount}
                                        onChange={(e) => setData('dest_amount', e.target.value)}
                                    />
                                    <InputError message={errors.dest_amount} className="mt-2" />
                                </div>
                            </>
                        )}
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="transfer_date" value="Data trasferimento" />
                                <TextInput
                                    id="transfer_date"
                                    type="date"
                                    max={today}
                                    className="mt-1 block w-full"
                                    value={data.transfer_date}
                                    onChange={(e) => setData('transfer_date', e.target.value)}
                                />
                                <InputError message={errors.transfer_date} className="mt-2" />
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
                        <div>
                            <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                            <textarea
                                id="description"
                                rows={3}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                            />
                            <InputError message={errors.description} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="notes" value="Note (opzionali)" />
                            <textarea
                                id="notes"
                                rows={3}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                            <InputError message={errors.notes} className="mt-2" />
                        </div>
                        <label className="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <input
                                type="checkbox"
                                checked={data.exclude_from_stats}
                                onChange={(e) => setData('exclude_from_stats', e.target.checked)}
                                className="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600"
                            />
                            <span className="text-sm">
                                Escludi dai calcoli statistici per entrambe le households.
                            </span>
                        </label>
                    </div>
                )}

                {step === 4 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Da</dt>
                            <dd>{sourceAccount?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">A household</dt>
                            <dd>{userHouseholds.find((h) => String(h.id) === data.dest_household_id)?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">A account</dt>
                            <dd>{destAccount?.name || '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Importo</dt>
                            <dd>
                                {data.source_amount || '0'} {data.source_currency}
                            </dd>
                        </div>
                        {isCrossCurrency && (
                            <>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Cambio</dt>
                                    <dd>{data.exchange_rate || '-'}</dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-gray-500">Arrivo</dt>
                                    <dd>{data.dest_amount || '0'} {data.dest_currency}</dd>
                                </div>
                            </>
                        )}
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Data</dt>
                            <dd>{formatItalianDate(data.transfer_date)}</dd>
                        </div>
                        {data.fee && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Commissione</dt>
                                <dd>{data.fee} {data.source_currency}</dd>
                            </div>
                        )}
                        {data.description && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Descrizione</dt>
                                <dd className="text-right">{data.description}</dd>
                            </div>
                        )}
                        {data.notes && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Note</dt>
                                <dd className="text-right">{data.notes}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Statistiche</dt>
                            <dd>{data.exclude_from_stats ? 'Escluso' : 'Incluso'}</dd>
                        </div>
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={STEP_COUNT}
                    processing={processing}
                    canNext={canNext()}
                    onBack={() => setStep((s) => Math.max(0, s - 1))}
                    onSkip={step === 3 ? goNext : undefined}
                    cancelHref={route('inter-household-transfers.index')}
                    submitLabel="Crea trasferimento"
                />
            </GuidedFormWizard>
        </form>
    );
}
