import { useState, useMemo } from 'react';

/**
 * Hook per il calcolo delle tasse e contributi per Partita IVA.
 * 
 * Gestisce:
 * - Entrate lorde
 * - Aliquota imposta sostitutiva (%)
 * - Percentuale contributi INPS (%)
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
    initialGrossIncome: number = 0,
    initialTaxRate: number = 15,
    initialInpsRate: number = 26.23
) {
    const [grossIncome, setGrossIncome] = useState<number>(initialGrossIncome);
    const [taxRate, setTaxRate] = useState<number>(initialTaxRate);
    const [inpsRate, setInpsRate] = useState<number>(initialInpsRate);

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
        grossIncome,
        setGrossIncome,
        taxRate,
        setTaxRate,
        inpsRate,
        setInpsRate,
        calculation,
    };
}
