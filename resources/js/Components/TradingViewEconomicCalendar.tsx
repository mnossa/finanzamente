import React from 'react';
import CardBox from '@/Components/CardBox';
import TradingViewWidget from '@/Components/TradingViewWidget';
import clsx from 'clsx';

interface TradingViewEconomicCalendarProps {
    className?: string;
}

const WIDGET_SRC = 'https://s3.tradingview.com/external-embedding/embed-widget-events.js';

const WIDGET_CONFIG = {
    width: '100%',
    height: '450',
    locale: 'it',
    importanceFilter: '-1,0,1',
    countryFilter: 'it,us,eu,de,gb,jp,cn,ca',
    isTransparent: true,
};

/**
 * TradingViewEconomicCalendar - widget calendario economico macroeconomico.
 * Mostra i principali eventi macro per Italia, USA, Europa e altri mercati chiave.
 */
const TradingViewEconomicCalendar: React.FC<TradingViewEconomicCalendarProps> = ({ className }) => {
    return (
        <CardBox className={clsx('p-4 shadow-sm', className)}>
            <div className="mb-3">
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                    📅 Calendario Economico
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Prossimi eventi macroeconomici rilevanti
                </p>
            </div>
            <TradingViewWidget
                widgetSrc={WIDGET_SRC}
                config={WIDGET_CONFIG}
                className="min-h-[450px]"
            />
        </CardBox>
    );
};

export default TradingViewEconomicCalendar;
