export interface TransactionAccount {
    id: number;
    name: string;
    currency_code: string;
    is_savings_deposit?: boolean;
}

export function accountsForTransactionType(
    accounts: TransactionAccount[],
    transactionType: 'income' | 'expense' | null | undefined,
    options?: { keepAccountId?: string | number | null },
): TransactionAccount[] {
    if (transactionType !== 'expense') {
        return accounts;
    }

    const keepAccountId = options?.keepAccountId != null ? String(options.keepAccountId) : null;

    return accounts.filter(
        (account) => !account.is_savings_deposit || (keepAccountId !== null && String(account.id) === keepAccountId),
    );
}

export function resolveTransactionAccountId(
    accounts: TransactionAccount[],
    currentAccountId: string,
): string {
    if (accounts.some((account) => String(account.id) === currentAccountId)) {
        return currentAccountId;
    }

    return accounts[0] ? String(accounts[0].id) : '';
}
