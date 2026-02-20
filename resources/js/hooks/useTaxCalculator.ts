import { useMemo } from 'react';

/**
 * Hook per il calcolo delle tasse e contributi per Partita IVA.
 * 
 * Gestisce:
 * - Entrate lorde (calcolate automaticamente dalle transazioni)
 * - Aliquota imposta sostitutiva (%) (dalla configurazione utente)
 * - Percentuale contributi INPS (%) (dalla configurazione utente)
 * - Calcoli automatici di imposta, contributi e margine netto
 */
export interface TaxCalculation {
    grossIncome: number;
    taxRate: number;
    inpsRate: number;
    taxAmount: number;
    inpsAmount: number;
    netMargin: number;
    totalSetAside: number;
    setAsidePercentage: number;
}

export function useTaxCalculator(
    grossIncome: number,
    taxRate: number,
    inpsRate: number
) {
    const calculation = useMemo((): TaxCalculation => {
        const taxAmount = (grossIncome * taxRate) / 100;
        const inpsAmount = (grossIncome * inpsRate) / 100;
        const totalSetAside = taxAmount + inpsAmount;
        const netMargin = grossIncome - totalSetAside;
        const setAsidePercentage = grossIncome > 0 ? (totalSetAside / grossIncome) * 100 : 0;

        return {
            grossIncome,
            taxRate,
            inpsRate,
            taxAmount,
            inpsAmount,
            netMargin,
            totalSetAside,
            setAsidePercentage,
        };
    }, [grossIncome, taxRate, inpsRate]);

    return {
        calculation,
    };
}
