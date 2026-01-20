import React from 'react';

export function getAccountTypeIcon(type: string): string {
    const icons: Record<string, string> = {
        bank: '🏦',
        cash: '💵',
        credit_card: '💳',
        debit_card: '💳',
        investment: '📈',
        crypto: '₿',
        other: '💰',
    };
    return icons[type] || '💰';
}
