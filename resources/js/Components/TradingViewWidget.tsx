import React, { useEffect, useRef } from 'react';
import { useChartDarkMode } from '@/Components/Charts/chartConfig';
import clsx from 'clsx';

interface TradingViewWidgetProps {
    widgetSrc: string;
    config: Record<string, unknown>;
    className?: string;
}

/**
 * TradingViewWidget - componente generico per l'embedding di widget TradingView.
 * Gestisce il ciclo di vita dello script e il tema chiaro/scuro.
 */
const TradingViewWidget: React.FC<TradingViewWidgetProps> = ({ widgetSrc, config, className }) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const isDark = useChartDarkMode();

    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        container.innerHTML = '';

        const widgetWrapper = document.createElement('div');
        widgetWrapper.className = 'tradingview-widget-container__widget';

        const script = document.createElement('script');
        script.src = widgetSrc;
        script.type = 'text/javascript';
        script.async = true;
        script.innerHTML = JSON.stringify({
            ...config,
            colorTheme: isDark ? 'dark' : 'light',
        });

        container.appendChild(widgetWrapper);
        container.appendChild(script);

        return () => {
            container.innerHTML = '';
        };
    }, [isDark, widgetSrc, config]);

    return (
        <div
            ref={containerRef}
            className={clsx('tradingview-widget-container w-full', className)}
        />
    );
};

export default TradingViewWidget;
