import { useTaxCalculator } from '@/hooks/useTaxCalculator';
import clsx from 'clsx';

interface TaxThermometerProps {
    grossIncome: number;
    taxRate: number;
    inpsRate: number;
    className?: string;
}

/**
 * Widget "Termometro Tasse" per la Dashboard.
 * 
 * Visualizza un indicatore circolare (gauge) che rappresenta la percentuale
 * di accantonamento fiscale (imposta + INPS) rispetto alle entrate lorde.
 * 
 * Le entrate lorde sono calcolate automaticamente dalle transazioni dell'anno corrente.
 * Le aliquote di imposta sostitutiva e contributi INPS sono configurate nel profilo utente.
 * 
 * Calcola e mostra:
 * - Importo imposta sostitutiva
 * - Importo contributi INPS
 * - Margine netto disponibile
 * - Percentuale totale di accantonamento
 */
export default function TaxThermometer({ grossIncome, taxRate, inpsRate, className }: TaxThermometerProps) {
    const {
        calculation,
    } = useTaxCalculator(grossIncome, taxRate, inpsRate);

    const formatCurrency = (amount: number): string => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    };

    // Parametri per il gauge circolare SVG
    const size = 180;
    const strokeWidth = 16;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const percentage = Math.min(calculation.setAsidePercentage, 100);
    const offset = circumference - (percentage / 100) * circumference;

    // Colore del gauge basato sulla percentuale
    const getGaugeColor = (pct: number): string => {
        if (pct < 30) return '#10b981'; // green-500
        if (pct < 50) return '#f59e0b'; // amber-500
        return '#ef4444'; // red-500
    };

    const gaugeColor = getGaugeColor(percentage);

    return (
        <div className={clsx('overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800', className)}>
            <div className="border-b border-gray-100 p-4 dark:border-gray-700">
                <h3 className="flex items-center font-semibold text-gray-900 dark:text-white">
                    <span className="mr-2">📊</span>
                    Termometro Tasse
                </h3>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Calcola l'accantonamento fiscale per la tua Partita IVA
                </p>
            </div>

            <div className="p-6">
                {/* Gauge Circolare */}
                <div className="mb-6 flex justify-center">
                    <div className="relative" style={{ width: size, height: size }}>
                        <svg
                            width={size}
                            height={size}
                            className="transform -rotate-90"
                        >
                            {/* Cerchio di sfondo */}
                            <circle
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                stroke="currentColor"
                                strokeWidth={strokeWidth}
                                fill="none"
                                className="text-gray-200 dark:text-gray-700"
                            />
                            {/* Cerchio di progresso */}
                            <circle
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                stroke={gaugeColor}
                                strokeWidth={strokeWidth}
                                fill="none"
                                strokeDasharray={circumference}
                                strokeDashoffset={offset}
                                strokeLinecap="round"
                                className="transition-all duration-500 ease-in-out"
                            />
                        </svg>
                        {/* Testo centrale */}
                        <div className="absolute inset-0 flex flex-col items-center justify-center">
                            <div className="text-3xl font-bold" style={{ color: gaugeColor }}>
                                {percentage.toFixed(1)}%
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                Accantonamento
                            </div>
                        </div>
                    </div>
                </div>

                {/* Informazioni di input (sola lettura) */}
                <div className="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-600 dark:text-gray-400">Entrate Lorde Annue:</span>
                        <span className="font-semibold text-gray-900 dark:text-white">
                            {formatCurrency(grossIncome)}
                        </span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-600 dark:text-gray-400">Aliquota Imposta:</span>
                        <span className="font-semibold text-gray-900 dark:text-white">
                            {taxRate.toFixed(1)}%
                        </span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-gray-600 dark:text-gray-400">Aliquota INPS:</span>
                        <span className="font-semibold text-gray-900 dark:text-white">
                            {inpsRate.toFixed(2)}%
                        </span>
                    </div>
                </div>

                {/* Risultati */}
                {grossIncome > 0 && (
                    <div className="mt-6 space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-600 dark:text-gray-400">Imposta Sostitutiva:</span>
                            <span className="font-semibold text-red-500">
                                {formatCurrency(calculation.taxAmount)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-gray-600 dark:text-gray-400">Contributi INPS:</span>
                            <span className="font-semibold text-orange-500">
                                {formatCurrency(calculation.inpsAmount)}
                            </span>
                        </div>
                        <div className="border-t border-gray-200 pt-3 dark:border-gray-600">
                            <div className="flex items-center justify-between">
                                <span className="font-medium text-gray-900 dark:text-white">
                                    Margine Netto:
                                </span>
                                <span className="text-lg font-bold text-green-500">
                                    {formatCurrency(calculation.netMargin)}
                                </span>
                            </div>
                        </div>
                    </div>
                )}

                {/* Help text */}
                <div className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <p>
                        💡 Le entrate lorde sono calcolate automaticamente dalle transazioni dell'anno corrente.
                        Puoi modificare le aliquote fiscali dal tuo profilo utente.
                    </p>
                </div>
            </div>
        </div>
    );
}
