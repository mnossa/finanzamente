/**
 * Tipi di detrazioni fiscali supportati per il 730
 */
export const TAX_DEDUCTION_TYPES = [
    { value: 'mediche', label: '🏥 Spese Mediche (19%)', defaultRate: 19 },
    { value: 'veterinarie', label: '🐾 Spese Veterinarie (19%)', defaultRate: 19 },
    { value: 'istruzione', label: '🎓 Istruzione (19%)', defaultRate: 19 },
    { value: 'mutuo', label: '🏠 Mutuo Prima Casa (19%)', defaultRate: 19 },
    { value: 'ristrutturazione', label: '🔨 Ristrutturazione (50%)', defaultRate: 50 },
    { value: 'assicurazioni', label: '🛡️ Assicurazioni (19%)', defaultRate: 19 },
    { value: 'previdenza', label: '💼 Previdenza Complementare', defaultRate: 19 },
    { value: 'donazioni', label: '❤️ Donazioni (19%-26%)', defaultRate: 19 },
    { value: 'altro', label: '📌 Altro', defaultRate: 19 },
] as const;

export type TaxDeductionType = typeof TAX_DEDUCTION_TYPES[number]['value'];
