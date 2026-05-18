import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatItalianDate, wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { Link, useForm } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

interface Currency {
    code: string;
    symbol: string;
}

interface Asset {
    id: number;
    name: string;
    symbol: string | null;
    type: string;
    type_label: string;
    type_icon: string;
    currency: Currency;
}

interface Account {
    id: number;
    name: string;
    type: string;
}

interface AssetTypes {
    [key: string]: string;
}

interface Props {
    accounts: Account[];
    assets: Asset[];
    assetTypes: AssetTypes;
}

const STEP_COUNT = 6;

export default function InvestmentCreateGuided({ accounts, assets, assetTypes }: Props) {
    const [step, setStep] = useState(0);
    const [isLoadingPrice, setIsLoadingPrice] = useState(false);
    const [priceError, setPriceError] = useState<string | null>(null);
    const [priceInfo, setPriceInfo] = useState<{
        price: number;
        date: string;
        requested_date: string;
    } | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        asset_id: '',
        account_id: '',
        quantity: '',
        buy_price: '',
        buy_date: new Date().toISOString().split('T')[0],
        fees: '',
        notes: '',
        is_private: false,
    });

    const selectedAsset = useMemo(
        () => assets.find((asset) => asset.id === Number(data.asset_id)),
        [assets, data.asset_id],
    );
    const selectedAccount = useMemo(
        () => accounts.find((account) => account.id === Number(data.account_id)),
        [accounts, data.account_id],
    );

    const totalValue = useMemo(() => {
        const quantity = Number.parseFloat(data.quantity) || 0;
        const buyPrice = Number.parseFloat(data.buy_price) || 0;
        return quantity * buyPrice;
    }, [data.quantity, data.buy_price]);

    const groupedAssets = useMemo(() => {
        return assets.reduce((acc, asset) => {
            if (!acc[asset.type]) {
                acc[asset.type] = [];
            }
            acc[asset.type].push(asset);
            return acc;
        }, {} as Record<string, Asset[]>);
    }, [assets]);

    const fetchHistoricalPrice = async () => {
        if (!selectedAsset?.symbol || !data.buy_date) return;

        setIsLoadingPrice(true);
        setPriceError(null);
        setPriceInfo(null);
        try {
            const response = await axios.get(
                `/api/assets/price/${encodeURIComponent(selectedAsset.symbol)}/history?date=${data.buy_date}`,
            );
            const result = response.data;

            if (result.success && result.data) {
                setPriceInfo({
                    price: result.data.price,
                    date: result.data.date,
                    requested_date: result.data.requested_date,
                });
            } else {
                setPriceError(result.error || 'Prezzo non disponibile');
            }
        } catch {
            setPriceError('Errore nel recupero del prezzo');
        } finally {
            setIsLoadingPrice(false);
        }
    };

    const applyPrice = () => {
        if (priceInfo?.price) {
            setData('buy_price', String(priceInfo.price));
        }
    };

    const canNext = (): boolean => {
        if (step === 0) return Boolean(data.asset_id);
        if (step === 1) return true;
        if (step === 2) return data.quantity !== '' && Number(data.quantity) > 0;
        if (step === 3) return data.buy_price !== '' && Number(data.buy_price) >= 0;
        if (step === 4) return Boolean(data.buy_date);
        return true;
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) setStep((s) => s + 1);
    };

    const meta = [
        { title: 'Seleziona asset', subtitle: "Scegli l'asset su cui investi." },
        { title: 'Conto di riferimento', subtitle: 'Opzionale: collega un conto.' },
        { title: 'Quantità', subtitle: 'Numero di quote o unità acquistate.' },
        { title: 'Prezzo acquisto', subtitle: 'Puoi recuperarlo dalla cronologia.' },
        { title: 'Data e dettagli', subtitle: 'Data obbligatoria, altri campi opzionali.' },
        { title: 'Conferma', subtitle: 'Verifica la posizione prima di registrare.' },
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
                post(route('investments.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={meta.title}
                subtitle={meta.subtitle}
            >
                {step === 0 && (
                    <div>
                        <InputLabel htmlFor="asset_id" value="Asset" />
                        <select
                            id="asset_id"
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.asset_id}
                            onChange={(e) => setData('asset_id', e.target.value)}
                        >
                            <option value="">Seleziona un asset...</option>
                            {Object.entries(groupedAssets).map(([type, typeAssets]) => (
                                <optgroup key={type} label={assetTypes[type] || type}>
                                    {typeAssets.map((asset) => (
                                        <option key={asset.id} value={asset.id}>
                                            {asset.type_icon} {asset.name}
                                            {asset.symbol ? ` (${asset.symbol})` : ''} - {asset.currency.code}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                        </select>
                        {assets.length === 0 && (
                            <p className="mt-2 text-sm text-amber-600">
                                Nessun asset disponibile. <Link href={route('investment-assets.create')} className="underline">Crea un asset</Link>.
                            </p>
                        )}
                        <InputError message={errors.asset_id} className="mt-2" />
                    </div>
                )}

                {step === 1 && (
                    <div>
                        <InputLabel htmlFor="account_id" value="Conto di riferimento (opzionale)" />
                        <select
                            id="account_id"
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.account_id}
                            onChange={(e) => setData('account_id', e.target.value)}
                        >
                            <option value="">Nessun conto collegato</option>
                            {accounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.account_id} className="mt-2" />
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <InputLabel htmlFor="quantity" value="Quantità" />
                        <TextInput
                            id="quantity"
                            type="number"
                            step="0.00000001"
                            min="0"
                            className="mt-1 block w-full"
                            value={data.quantity}
                            onChange={(e) => setData('quantity', e.target.value)}
                            autoFocus
                        />
                        <InputError message={errors.quantity} className="mt-2" />
                    </div>
                )}

                {step === 3 && (
                    <div className="space-y-3">
                        <div>
                            <InputLabel htmlFor="buy_price" value="Prezzo di acquisto" />
                            <TextInput
                                id="buy_price"
                                type="number"
                                step="0.00000001"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.buy_price}
                                onChange={(e) => setData('buy_price', e.target.value)}
                            />
                            <InputError message={errors.buy_price} className="mt-2" />
                        </div>
                        <div>
                            <button
                                type="button"
                                onClick={fetchHistoricalPrice}
                                disabled={!selectedAsset?.symbol || !data.buy_date || isLoadingPrice}
                                className={clsx(
                                    'rounded-md px-3 py-2 text-sm',
                                    !selectedAsset?.symbol || !data.buy_date
                                        ? 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-800'
                                        : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300',
                                )}
                            >
                                {isLoadingPrice ? 'Recupero prezzo...' : 'Recupera prezzo storico'}
                            </button>
                        </div>
                        {priceInfo && (
                            <div className="rounded-lg bg-emerald-50 p-3 text-sm dark:bg-emerald-900/20">
                                <div>
                                    Prezzo al {priceInfo.date}:{' '}
                                    <strong>
                                        {new Intl.NumberFormat('it-IT', {
                                            style: 'currency',
                                            currency: selectedAsset?.currency.code || 'EUR',
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 8,
                                        }).format(priceInfo.price)}
                                    </strong>
                                </div>
                                <button type="button" onClick={applyPrice} className="mt-2 text-xs underline">
                                    Usa questo prezzo
                                </button>
                            </div>
                        )}
                        {priceError && <p className="text-sm text-red-500">{priceError}</p>}
                    </div>
                )}

                {step === 4 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="buy_date" value="Data acquisto" />
                            <TextInput
                                id="buy_date"
                                type="date"
                                className="mt-1 block w-full"
                                value={data.buy_date}
                                onChange={(e) => setData('buy_date', e.target.value)}
                            />
                            <InputError message={errors.buy_date} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="fees" value="Commissioni (opzionale)" />
                            <TextInput
                                id="fees"
                                type="number"
                                step="0.01"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.fees}
                                onChange={(e) => setData('fees', e.target.value)}
                            />
                            <InputError message={errors.fees} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="notes" value="Note (opzionale)" />
                            <textarea
                                id="notes"
                                rows={3}
                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                            <InputError message={errors.notes} className="mt-2" />
                        </div>
                        <label className="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                            <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-gray-300 text-emerald-600"
                                checked={data.is_private}
                                onChange={(e) => setData('is_private', e.target.checked)}
                            />
                            <span className="text-sm">Investimento privato</span>
                        </label>
                    </div>
                )}

                {step === 5 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Asset</dt>
                            <dd className="text-right">{selectedAsset ? `${selectedAsset.type_icon} ${selectedAsset.name}` : '-'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Conto</dt>
                            <dd>{selectedAccount?.name || 'Nessuno'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Quantità</dt>
                            <dd>{data.quantity || '0'}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Prezzo</dt>
                            <dd>
                                {data.buy_price || '0'} {selectedAsset?.currency.symbol || ''}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Data acquisto</dt>
                            <dd>{formatItalianDate(data.buy_date)}</dd>
                        </div>
                        {data.fees && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Commissioni</dt>
                                <dd>{data.fees}</dd>
                            </div>
                        )}
                        {totalValue > 0 && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Valore totale</dt>
                                <dd className="font-medium">
                                    {new Intl.NumberFormat('it-IT', {
                                        style: 'currency',
                                        currency: selectedAsset?.currency.code || 'EUR',
                                    }).format(totalValue)}
                                </dd>
                            </div>
                        )}
                        {data.notes && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Note</dt>
                                <dd className="text-right">{data.notes}</dd>
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
                    totalSteps={STEP_COUNT}
                    processing={processing}
                    canNext={canNext()}
                    onBack={() => setStep((s) => Math.max(0, s - 1))}
                    onSkip={step === 1 || step === 4 ? goNext : undefined}
                    cancelHref={route('investments.index')}
                    submitLabel="Registra investimento"
                />
            </GuidedFormWizard>
        </form>
    );
}
