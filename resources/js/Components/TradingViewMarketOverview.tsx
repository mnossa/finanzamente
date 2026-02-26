import React from 'react';
import CardBox from '@/Components/CardBox';
import TradingViewWidget from '@/Components/TradingViewWidget';
import clsx from 'clsx';

interface TradingViewMarketOverviewProps {
    className?: string;
}

const WIDGET_SRC = 'https://s3.tradingview.com/external-embedding/embed-widget-market-overview.js';

const WIDGET_CONFIG = {
    dateRange: '12M',
    showChart: true,
    locale: 'it',
    largeChartUrl: '',
    isTransparent: true,
    showSymbolLogo: true,
    showFloatingTooltip: false,
    width: '100%',
    height: '500',
    plotLineColorGrowing: 'rgba(41, 98, 255, 1.0)',
    plotLineColorFalling: 'rgba(41, 98, 255, 1.0)',
    gridLineColor: 'rgba(240, 243, 250, 0)',
    scaleFontColor: 'rgba(106, 109, 120, 1.0)',
    belowLineFillColorGrowing: 'rgba(41, 98, 255, 0.12)',
    belowLineFillColorFalling: 'rgba(41, 98, 255, 0.12)',
    belowLineFillColorGrowingBottom: 'rgba(41, 98, 255, 0)',
    belowLineFillColorFallingBottom: 'rgba(41, 98, 255, 0)',
    symbolActiveColor: 'rgba(41, 98, 255, 0.12)',
    tabs: [
        {
            title: 'Indici',
            symbols: [
                { s: 'EURONEXT:FTSEMIB', d: 'FTSE MIB' },
                { s: 'FOREXCOM:SPXUSD', d: 'S&P 500' },
                { s: 'FOREXCOM:NSXUSD', d: 'Nasdaq 100' },
                { s: 'FOREXCOM:DJI', d: 'Dow Jones' },
                { s: 'INDEX:DEU40', d: 'DAX 40' },
                { s: 'FOREXCOM:UKXGBP', d: 'FTSE 100' },
            ],
            originalTitle: 'Indices',
        },
        {
            title: 'Forex',
            symbols: [
                { s: 'FX_IDC:EURUSD', d: 'EUR/USD' },
                { s: 'FX_IDC:EURGBP', d: 'EUR/GBP' },
                { s: 'FX_IDC:EURCAD', d: 'EUR/CAD' },
                { s: 'FX_IDC:EURJPY', d: 'EUR/JPY' },
                { s: 'FX_IDC:EURCHF', d: 'EUR/CHF' },
                { s: 'FX_IDC:EURCNY', d: 'EUR/CNY' },
            ],
            originalTitle: 'Forex',
        },
        {
            title: 'Materie Prime',
            symbols: [
                { s: 'CME_MINI:NQ1!', d: 'Nasdaq Futures' },
                { s: 'NYMEX:CL1!', d: 'Petrolio WTI' },
                { s: 'NYMEX:NG1!', d: 'Gas Naturale' },
                { s: 'COMEX:GC1!', d: 'Oro' },
                { s: 'COMEX:SI1!', d: 'Argento' },
                { s: 'CBOT:ZW1!', d: 'Grano' },
            ],
            originalTitle: 'Commodities',
        },
        {
            title: 'Crypto',
            symbols: [
                { s: 'CRYPTO:BTCUSD', d: 'Bitcoin' },
                { s: 'CRYPTO:ETHUSD', d: 'Ethereum' },
                { s: 'CRYPTO:SOLUSD', d: 'Solana' },
                { s: 'CRYPTO:BNBUSD', d: 'BNB' },
                { s: 'CRYPTO:ADAUSD', d: 'Cardano' },
            ],
            originalTitle: 'Crypto',
        },
    ],
};

/**
 * TradingViewMarketOverview - widget panoramica mercati finanziari.
 * Mostra indici, forex, materie prime e crypto con supporto tema chiaro/scuro.
 */
const TradingViewMarketOverview: React.FC<TradingViewMarketOverviewProps> = ({ className }) => {
    return (
        <CardBox className={clsx('p-4 shadow-sm', className)}>
            <div className="mb-3">
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                    🌍 Panoramica Mercati
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Andamento dei principali mercati finanziari globali
                </p>
            </div>
            <TradingViewWidget
                widgetSrc={WIDGET_SRC}
                config={WIDGET_CONFIG}
                className="min-h-[500px]"
            />
        </CardBox>
    );
};

export default TradingViewMarketOverview;
