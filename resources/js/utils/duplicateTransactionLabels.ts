import { formatCurrency, formatDate } from '@/utils/format';

export interface TransactionSideLabelInput {
    date: string | null;
    amount: number;
    description: string | null;
    account_name: string | null;
    currency_code: string;
}

/** Riga descrittiva per modale (es. «Da ricorrenza: -60,80 € · 21 dic 2025 · Conto corrente»). */
export function formatTransactionSideSummary(side: TransactionSideLabelInput, roleLabel: string): string {
    const date = side.date ? formatDate(side.date) : 'data non disponibile';
    const amount = formatCurrency(side.amount, side.currency_code);
    const account = side.account_name ?? 'conto non indicato';

    return `${roleLabel}: ${amount} · ${date} · ${account}`;
}

/** Etichetta compatta per CTA eliminazione manuale in coppie ricorrenza. */
export function buildManualDeleteButtonLabel(): string {
    return 'Elimina inserimento manuale';
}

/** Etichetta compatta per CTA eliminazione lato A/B (coppie solo manuali). */
export function buildCompactDeleteButtonLabel(letter: 'A' | 'B'): string {
    return `Elimina movimento ${letter}`;
}
