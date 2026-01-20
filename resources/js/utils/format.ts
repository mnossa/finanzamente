// Utility per formattazione valuta, data e paginazione
// Tutte le funzioni sono centralizzate qui per evitare duplicazioni

export function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(amount);
}

export function formatDate(dateStr: string | null, opts?: Intl.DateTimeFormatOptions): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return new Intl.DateTimeFormat('it-IT', opts || {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

export function formatDateTime(dateStr: string | null): string {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formatNumber(value: number, decimals: number = 2): string {
    return new Intl.NumberFormat('it-IT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    }).format(value);
}
