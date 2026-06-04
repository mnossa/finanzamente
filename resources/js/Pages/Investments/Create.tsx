import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import InvestmentCreateGuided from './InvestmentCreateGuided';
import { PageProps } from '@/types';
import { isGuidedCreateEnabled } from '@/utils/guidedCreate';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import clsx from 'clsx';
import { FormEventHandler, useMemo, useState, useEffect, useCallback } from 'react';
import PageHeader from '@/Components/PageHeader';
import axios from 'axios';

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

interface CreateProps {
    accounts: Account[];
    assets: Asset[];
    assetTypes: AssetTypes;
}

export default function Create({ accounts, assets, assetTypes }: CreateProps) {
    const { features } = usePage<PageProps & { features?: Record<string, boolean> }>().props;

    if (isGuidedCreateEnabled(features)) {
        return (
            <AuthenticatedLayout
                header={
                    <PageHeader
                        title="Nuovo Investimento"
                        backLink={route('investments.index')}
                    />
                }
            >
                <Head title="Nuovo Investimento" />
                <PageContent maxWidth="3xl">
                    <InvestmentCreateGuided accounts={accounts} assets={assets} assetTypes={assetTypes} />
                </PageContent>
            </AuthenticatedLayout>
        );
    }

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

    const [isLoadingPrice, setIsLoadingPrice] = useState(false);
    const [priceError, setPriceError] = useState<string | null>(null);
    const [priceInfo, setPriceInfo] = useState<{
        price: number;
        date: string;
        requested_date: string;
    } | null>(null);

    const selectedAsset = useMemo(() => {
        return assets.find(a => a.id === Number(data.asset_id));
    }, [data.asset_id, assets]);

    const totalValue = useMemo(() => {
        const qty = parseFloat(data.quantity) || 0;
        const price = parseFloat(data.buy_price) || 0;
        return qty * price;
    }, [data.quantity, data.buy_price]);

    // Funzione per recuperare il prezzo storico
    const fetchHistoricalPrice = useCallback(async (symbol: string, date: string) => {
        if (!symbol || !date) return;
        
        setIsLoadingPrice(true);
        setPriceError(null);
        setPriceInfo(null);

        try {
            const response = await axios.get(`/api/assets/price/${encodeURIComponent(symbol)}/history?date=${date}`);
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
    }, []);

    // Effetto per recuperare il prezzo quando cambia asset o data
    useEffect(() => {
        if (selectedAsset?.symbol && data.buy_date) {
            // Debounce per evitare troppe chiamate
            const timer = setTimeout(() => {
                fetchHistoricalPrice(selectedAsset.symbol!, data.buy_date);
            }, 500);
            return () => clearTimeout(timer);
        } else {
            setPriceInfo(null);
            setPriceError(null);
        }
    }, [selectedAsset?.symbol, data.buy_date, fetchHistoricalPrice]);

    // Funzione per applicare il prezzo suggerito
    const applyPrice = () => {
        if (priceInfo?.price) {
            setData('buy_price', priceInfo.price.toString());
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('investments.store'));
    };

    // Raggruppa asset per tipo
    const groupedAssets = useMemo(() => {
        return assets.reduce((acc, asset) => {
            if (!acc[asset.type]) {
                acc[asset.type] = [];
            }
            acc[asset.type].push(asset);
            return acc;
        }, {} as Record<string, Asset[]>);
    }, [assets]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuovo Investimento"
                    backLink={route('investments.index')}
                />
            }
        >
            <Head title="Nuovo Investimento" />

            <PageContent maxWidth="3xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Investimenti" icon={<span className="text-sm leading-none">📈</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Registra investimento</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Inserisci quantità, prezzo, data e metadati della posizione.</p>
                        </header>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            <div>
                                {/* Asset */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="asset_id" value="Asset *" />
                                    <select
                                        id="asset_id"
                                        name="asset_id"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.asset_id}
                                        onChange={(e) => setData('asset_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Seleziona un asset...</option>
                                        {Object.entries(groupedAssets).map(([type, typeAssets]) => (
                                            <optgroup key={type} label={assetTypes[type] || type}>
                                                {typeAssets.map((asset) => (
                                                    <option key={asset.id} value={asset.id}>
                                                        {asset.type_icon} {asset.name}
                                                        {asset.symbol && ` (${asset.symbol})`}
                                                        {` - ${asset.currency.code}`}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        ))}
                                    </select>
                                    {assets.length === 0 && (
                                        <p className="mt-2 text-sm text-amber-600">
                                            Nessun asset disponibile.{' '}
                                            <Link href={route('investment-assets.create')} className="underline">
                                                Crea un asset
                                            </Link>{' '}
                                            prima di registrare un investimento.
                                        </p>
                                    )}
                                    <InputError message={errors.asset_id} className="mt-2" />
                                </div>

                                {/* Quantità e Prezzo */}
                                <div className="mb-6 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="quantity" value="Quantità *" />
                                        <TextInput
                                            id="quantity"
                                            name="quantity"
                                            type="number"
                                            step="0.00000001"
                                            min="0"
                                            className="mt-2 w-full"
                                            value={data.quantity}
                                            onChange={(e) => setData('quantity', e.target.value)}
                                            placeholder="0.00000000"
                                            required
                                        />
                                        <InputError message={errors.quantity} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="buy_price" value="Prezzo di Acquisto *" />
                                        <div className="relative mt-2">
                                            <TextInput
                                                id="buy_price"
                                                name="buy_price"
                                                type="number"
                                                step="0.00000001"
                                                min="0"
                                                className="w-full pr-12"
                                                value={data.buy_price}
                                                onChange={(e) => setData('buy_price', e.target.value)}
                                                placeholder="0.00"
                                                required
                                            />
                                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                                {selectedAsset?.currency.symbol || '€'}
                                            </span>
                                        </div>
                                        
                                        {/* Suggerimento prezzo da API */}
                                        {selectedAsset?.symbol && (
                                            <div className="mt-2">
                                                {isLoadingPrice && (
                                                    <div className="flex items-center gap-2 text-sm text-gray-500">
                                                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent" />
                                                        <span>Recupero prezzo per {data.buy_date}...</span>
                                                    </div>
                                                )}
                                                {priceInfo && !isLoadingPrice && (
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                                            💡 Prezzo al {priceInfo.date}:
                                                        </span>
                                                        <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                                            {new Intl.NumberFormat('it-IT', {
                                                                style: 'currency',
                                                                currency: selectedAsset?.currency.code || 'EUR',
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 8,
                                                            }).format(priceInfo.price)}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={applyPrice}
                                                            className="rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50"
                                                        >
                                                            Usa questo prezzo
                                                        </button>
                                                        {priceInfo.date !== priceInfo.requested_date && (
                                                            <span className="text-xs text-amber-600 dark:text-amber-400">
                                                                ⚠️ Data più vicina disponibile
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                                {priceError && !isLoadingPrice && (
                                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                                        {priceError}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        
                                        <InputError message={errors.buy_price} className="mt-2" />
                                    </div>
                                </div>

                                {/* Totale Calcolato */}
                                {totalValue > 0 && (
                                    <div className="mb-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Valore Totale Acquisto
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                                            {new Intl.NumberFormat('it-IT', {
                                                style: 'currency',
                                                currency: selectedAsset?.currency.code || 'EUR',
                                            }).format(totalValue)}
                                        </p>
                                    </div>
                                )}

                                {/* Data Acquisto */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="buy_date" value="Data di Acquisto *" />
                                    <TextInput
                                        id="buy_date"
                                        type="date"
                                        className="mt-2 w-full"
                                        value={data.buy_date}
                                        onChange={(e) => setData('buy_date', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.buy_date} className="mt-2" />
                                </div>

                                {/* Conto di riferimento */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="account_id" value="Conto di Riferimento" />
                                    <select
                                        id="account_id"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Opzionale: associa l'investimento a un conto
                                    </p>
                                    <InputError message={errors.account_id} className="mt-2" />
                                </div>

                                {/* Commissioni */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="fees" value="Commissioni" />
                                    <div className="relative mt-2">
                                        <TextInput
                                            id="fees"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="w-full pr-12"
                                            value={data.fees}
                                            onChange={(e) => setData('fees', e.target.value)}
                                            placeholder="0.00"
                                        />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                            {selectedAsset?.currency.symbol || '€'}
                                        </span>
                                    </div>
                                    <InputError message={errors.fees} className="mt-2" />
                                </div>

                                {/* Note */}
                                <div className="mb-6">
                                    <InputLabel htmlFor="notes" value="Note" />
                                    <textarea
                                        id="notes"
                                        className="mt-2 w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Aggiungi note sull'investimento..."
                                        rows={3}
                                    />
                                    <InputError message={errors.notes} className="mt-2" />
                                </div>

                                {/* Privato */}
                                <div className="mb-6">
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            className="rounded border-gray-300 text-emerald-500 shadow-sm focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                            checked={data.is_private}
                                            onChange={(e) => setData('is_private', e.target.checked)}
                                        />
                                        <span className="text-sm text-gray-700 dark:text-gray-300">
                                            🔒 Investimento privato (visibile solo a te)
                                        </span>
                                    </label>
                                </div>
                            </div>

                            {/* Footer */}
                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('investments.index')}
                                    className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing || assets.length === 0}>
                                    {processing ? 'Registrazione...' : '📈 Registra Investimento'}
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
