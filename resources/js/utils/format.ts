// Utility per formattazione valuta, data e paginazione
// Tutte le funzioni sono centralizzate qui per evitare duplicazioni

/** Evita shift timezone su date Y-m-d (mezzogiorno locale). */
export function parseDateSafe(dateStr: string): Date {
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        return new Date(`${dateStr}T12:00:00`);
    }

    return new Date(dateStr);
}

export function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(amount);
}

export function formatDate(dateStr: string | null, opts?: Intl.DateTimeFormatOptions): string {
    if (!dateStr) {
        return '-';
    }
    const date = parseDateSafe(dateStr);
    if (isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat('it-IT', opts || {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

/** Giorno + mese breve (es. widget dashboard). */
export function formatDateShort(dateStr: string | null): string {
    return formatDate(dateStr, {
        day: '2-digit',
        month: 'short',
    });
}

/** Data completa con mese esteso (es. dettaglio debito/credito). */
export function formatDateLong(dateStr: string | null): string {
    return formatDate(dateStr, {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

export function formatDateTime(dateStr: string | null): string {
    if (!dateStr) {
        return '-';
    }
    const date = parseDateSafe(dateStr);
    if (isNaN(date.getTime())) {
        return '-';
    }

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
