/**
 * Formattazione importi nel wizard: valore completo (tooltip) e forma compatta per |importo| ≥ 10.000 €.
 */
export function getImportAmountDisplay(amount: number): { full: string; short: string } {
    const full = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(amount);
    const abs = Math.abs(amount);
    if (abs < 10_000) {
        return { full, short: full };
    }

    const sign = amount < 0 ? '\u2212' : '';
    const fmt = (n: number, maxFrac: number) =>
        new Intl.NumberFormat('it-IT', { maximumFractionDigits: maxFrac, minimumFractionDigits: 0 }).format(n);

    if (abs < 1_000_000) {
        const k = abs / 1000;
        const maxFrac = abs >= 100_000 ? 0 : 1;

        return { full, short: `${sign}${fmt(k, maxFrac)}k €` };
    }

    const m = abs / 1_000_000;
    const maxFrac = abs >= 10_000_000 ? 0 : 1;

    return { full, short: `${sign}${fmt(m, maxFrac)}M €` };
}
