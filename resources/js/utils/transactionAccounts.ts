export interface MealVoucherLotOption {
    id: number;
    unit_value: number;
    quantity_remaining: number;
    acquired_on: string;
    euro_value: number;
}

export interface MealVoucherUnitValueHistoryRow {
    unit_value: number;
    effective_from: string;
}

export interface TransactionAccount {
    id: number;
    name: string;
    currency_code: string;
    is_savings_deposit?: boolean;
    is_meal_voucher?: boolean;
    is_pension_fund?: boolean;
    ticket_unit_value?: number | null;
    meal_voucher_lots?: MealVoucherLotOption[];
    meal_voucher_unit_value_history?: MealVoucherUnitValueHistoryRow[];
}

/**
 * Valore ticket vigente alla data (ultimo effective_from <= date).
 */
export function mealVoucherUnitValueOnDate(
    account: TransactionAccount | null | undefined,
    date: string,
): number | null {
    if (!account?.is_meal_voucher) {
        return null;
    }

    const history = account.meal_voucher_unit_value_history ?? [];
    if (history.length > 0 && date) {
        const eligible = history
            .filter((row) => row.effective_from <= date)
            .sort((a, b) => b.effective_from.localeCompare(a.effective_from));

        if (eligible[0]) {
            return eligible[0].unit_value;
        }
    }

    return account.ticket_unit_value ?? null;
}

export function accountsForTransactionType(
    accounts: TransactionAccount[],
    transactionType: 'income' | 'expense' | null | undefined,
    options?: { keepAccountId?: string | number | null },
): TransactionAccount[] {
    if (transactionType !== 'expense' && transactionType !== 'income') {
        return accounts;
    }

    const keepAccountId = options?.keepAccountId != null ? String(options.keepAccountId) : null;

    return accounts.filter((account) => {
        if (keepAccountId !== null && String(account.id) === keepAccountId) {
            return true;
        }

        if (account.is_pension_fund) {
            return false;
        }

        if (transactionType === 'expense' && account.is_savings_deposit) {
            return false;
        }

        return true;
    });
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
