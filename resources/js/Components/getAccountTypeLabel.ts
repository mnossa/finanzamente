/**
 * Label italiana per tipo conto (allineata a Account::uiTypes / alias legacy).
 */
export function getAccountTypeLabel(type: string): string {
    const types: Record<string, string> = {
        bank: 'Conto Bancario',
        cash: 'Contanti',
        card: 'Carta',
        credit_card: 'Carta di Credito',
        debit_card: 'Carta di Debito',
        broker: 'Broker',
        investment: 'Investimento',
        crypto: 'Crypto',
        savings_deposit: 'Conto Deposito',
        meal_voucher: 'Buoni pasto',
        pension_fund: 'Fondo pensione / previdenza',
        other: 'Altro',
    };

    return types[type] || type;
}
