import React from 'react';

export function getAccountTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        bank: '🏦',
        cash: '💵',
        card: '💳',
        credit_card: '💳',
        debit_card: '💳',
        broker: '📈',
        investment: '📈',
        crypto: '₿',
        savings_deposit: '🏦',
        meal_voucher: '🎫',
        pension_fund: '🧓',
        other: '💰',
    };
    return icons[type] || '💰';
}
