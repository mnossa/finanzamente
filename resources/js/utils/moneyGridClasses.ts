/**
 * Classi Tailwind condivise per griglie di importi / KPI finanziari:
 * sotto ~380px una colonna (nessun testo “schiacciato” affiancato), poi breakpoint standard.
 * Usare `moneyTabular` sugli elementi che mostrano importi formattati.
 */
export const moneyTabular = 'tabular-nums fm-sensitive-amount';

/** Due KPI affiancati da ~380px (es. patrimonio + rischio). */
export const moneyKpiGrid2 =
    'grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 min-[380px]:gap-3 sm:gap-4';

/** Tre KPI (report, riepiloghi debiti/budget/detrazioni). */
export const moneyKpiGrid3 =
    'grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 min-[380px]:gap-3 sm:grid-cols-3 sm:gap-4';

/** Quattro KPI (dashboard mensile, investimenti, asset…). */
export const moneyKpiGrid4 =
    'grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 min-[380px]:gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4';

/** Card in elenco: 1 → 2 (380+) → 2 (sm) → 3 (lg). */
export const moneyCardGrid3 =
    'grid grid-cols-1 gap-3 min-[380px]:grid-cols-2 min-[380px]:gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3';

/** Metriche compatte a 3 colonne da `sm` (allineato a Lifestyle Score). */
export const moneyMetricGrid3 =
    'grid grid-cols-1 gap-2 min-[380px]:grid-cols-2 min-[380px]:gap-3 sm:grid-cols-3 sm:gap-3';
