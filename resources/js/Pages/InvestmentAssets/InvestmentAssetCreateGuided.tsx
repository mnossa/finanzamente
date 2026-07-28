import AssetSearch from '@/Components/AssetSearch';
import GuidedFormStepActions from '@/Components/GuidedForm/GuidedFormStepActions';
import GuidedFormWizard from '@/Components/GuidedForm/GuidedFormWizard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { wizardSteps } from '@/utils/guidedCreate';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Types {
    [key: string]: string;
}

interface TypeIcons {
    [key: string]: string;
}

interface Props {
    currencies: Currency[];
    types: Types;
    typeIcons: TypeIcons;
    incomePolicies?: Record<string, string>;
}

const STEP_COUNT = 6;

export default function InvestmentAssetCreateGuided({
    currencies,
    types,
    typeIcons,
    incomePolicies = { accumulating: 'Accumulo', distributing: 'Distribuzione' },
}: Props) {
    const [step, setStep] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        type: 'stock',
        symbol: '',
        isin: '',
        exchange: '',
        name: '',
        currency_code: 'EUR',
        income_policy: '',
    });

    const selectedCurrency = useMemo(
        () => currencies.find((currency) => currency.code === data.currency_code),
        [currencies, data.currency_code],
    );

    const showIncomePolicy = ['etf', 'stock', 'bond'].includes(data.type);

    const handleAssetSelect = (asset: { symbol: string; name: string; type: string; currency: string; region?: string }) => {
        setData({
            ...data,
            symbol: asset.symbol || '',
            name: asset.name || '',
            type: asset.type || 'stock',
            currency_code: asset.currency || 'EUR',
            exchange: asset.region || '',
        });
    };

    const canNext = (): boolean => {
        if (step === 0) return Boolean(data.type);
        if (step === 1) return data.name.trim().length > 0;
        if (step === 3) return Boolean(data.currency_code);
        return true;
    };

    const goNext = () => {
        if (step < STEP_COUNT - 1 && canNext()) {
            if (step === 3 && !showIncomePolicy) {
                setStep(5);
                return;
            }
            setStep((s) => s + 1);
        }
    };

    const goBack = () => {
        if (step === 5 && !showIncomePolicy) {
            setStep(3);
            return;
        }
        setStep((s) => Math.max(0, s - 1));
    };

    const meta = [
        { title: 'Tipo e ricerca asset', subtitle: 'Seleziona tipo o usa la ricerca online.' },
        { title: 'Nome e simbolo', subtitle: 'Nome obbligatorio, ticker opzionale.' },
        { title: 'ISIN e borsa', subtitle: 'Campi opzionali per identificazione.' },
        { title: 'Valuta', subtitle: "Valuta principale dell'asset." },
        { title: 'Dividendi / cedole', subtitle: 'Accumulo o distribuzione (ETF, azioni, obbligazioni).' },
        { title: 'Conferma', subtitle: 'Controlla i dettagli prima del salvataggio.' },
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
                post(route('investment-assets.store'));
            }}
        >
            <GuidedFormWizard
                steps={wizardSteps(STEP_COUNT)}
                currentStep={step}
                title={meta.title}
                subtitle={meta.subtitle}
            >
                {step === 0 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel value="Ricerca rapida" />
                            <AssetSearch onSelect={handleAssetSelect} className="mt-2" />
                        </div>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {Object.entries(types).map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setData('type', value)}
                                    className={clsx(
                                        'rounded-lg border-2 p-3 text-center',
                                        data.type === value
                                            ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                            : 'border-gray-200 dark:border-gray-700',
                                    )}
                                >
                                    <div className="text-xl">{typeIcons[value] || '💼'}</div>
                                    <div className="text-xs font-medium">{label}</div>
                                </button>
                            ))}
                        </div>
                        <InputError message={errors.type} className="mt-2" />
                    </div>
                )}

                {step === 1 && (
                    <div className="space-y-4">
                        <div>
                            <InputLabel htmlFor="name" value="Nome asset" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoFocus
                            />
                            <InputError message={errors.name} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="symbol" value="Simbolo / ticker (opzionale)" />
                            <TextInput
                                id="symbol"
                                className="mt-1 block w-full"
                                value={data.symbol}
                                onChange={(e) => setData('symbol', e.target.value.toUpperCase())}
                                maxLength={20}
                            />
                            <InputError message={errors.symbol} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="isin" value="Codice ISIN (opzionale)" />
                            <TextInput
                                id="isin"
                                className="mt-1 block w-full font-mono"
                                value={data.isin}
                                onChange={(e) => setData('isin', e.target.value.toUpperCase())}
                                maxLength={12}
                            />
                            <InputError message={errors.isin} className="mt-2" />
                        </div>
                        <div>
                            <InputLabel htmlFor="exchange" value="Borsa (opzionale)" />
                            <TextInput
                                id="exchange"
                                className="mt-1 block w-full"
                                value={data.exchange}
                                onChange={(e) => setData('exchange', e.target.value)}
                                maxLength={50}
                            />
                            <InputError message={errors.exchange} className="mt-2" />
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <InputLabel htmlFor="currency_code" value="Valuta" />
                        <select
                            id="currency_code"
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                )}

                {step === 4 && showIncomePolicy && (
                    <div>
                        <InputLabel htmlFor="income_policy" value="Dividendi / cedole" />
                        <select
                            id="income_policy"
                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            value={data.income_policy}
                            onChange={(e) => setData('income_policy', e.target.value)}
                        >
                            <option value="">— Non specificata —</option>
                            {Object.entries(incomePolicies).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Accumulo = proventi reinvestiti; Distribuzione = stacco cash.
                        </p>
                        <InputError message={errors.income_policy} className="mt-2" />
                    </div>
                )}

                {step === 5 && (
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Tipo</dt>
                            <dd>{typeIcons[data.type] || '💼'} {types[data.type] || data.type}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Nome</dt>
                            <dd className="text-right font-medium">{data.name}</dd>
                        </div>
                        {data.symbol && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Ticker</dt>
                                <dd>{data.symbol}</dd>
                            </div>
                        )}
                        {data.isin && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">ISIN</dt>
                                <dd>{data.isin}</dd>
                            </div>
                        )}
                        {data.exchange && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Borsa</dt>
                                <dd>{data.exchange}</dd>
                            </div>
                        )}
                        <div className="flex justify-between gap-4">
                            <dt className="text-gray-500">Valuta</dt>
                            <dd>{selectedCurrency?.symbol || data.currency_code}</dd>
                        </div>
                        {showIncomePolicy && data.income_policy && (
                            <div className="flex justify-between gap-4">
                                <dt className="text-gray-500">Dividendi</dt>
                                <dd>{incomePolicies[data.income_policy] || data.income_policy}</dd>
                            </div>
                        )}
                    </dl>
                )}

                <GuidedFormStepActions
                    step={step}
                    totalSteps={STEP_COUNT}
                    processing={processing}
                    canNext={canNext()}
                    onBack={goBack}
                    onSkip={step === 2 || step === 4 ? goNext : undefined}
                    cancelHref={route('investment-assets.index')}
                    submitLabel="Crea asset"
                />
            </GuidedFormWizard>
        </form>
    );
}
