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

/** ISO `YYYY-MM-DD` → display `dd/mm/yyyy`. */
export function isoToItalianDate(iso: string | null | undefined): string {
    if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return '';
    }
    const [y, m, d] = iso.split('-');

    return `${d}/${m}/${y}`;
}

/**
 * Parse data italiana (o ISO) → `YYYY-MM-DD`, oppure `null` se invalida.
 * Accetta `d/m/yyyy`, `dd/mm/yyyy`, `yyyy-mm-dd`.
 */
export function italianDateToIso(raw: string | null | undefined): string | null {
    if (!raw) {
        return null;
    }
    const trimmed = raw.trim();
    if (trimmed === '') {
        return null;
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
        return isValidIsoDate(trimmed) ? trimmed : null;
    }

    const match = trimmed.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/);
    if (!match) {
        return null;
    }
    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const iso = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    return isValidIsoDate(iso) ? iso : null;
}

function isValidIsoDate(iso: string): boolean {
    const date = parseDateSafe(iso);
    if (isNaN(date.getTime())) {
        return false;
    }
    const [y, m, d] = iso.split('-').map(Number);

    return date.getFullYear() === y && date.getMonth() + 1 === m && date.getDate() === d;
}
