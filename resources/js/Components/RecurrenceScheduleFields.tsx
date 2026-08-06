import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { useEffect } from 'react';

export type DayOfMonthMode = 'start_date' | 'fixed' | 'last_day';
export type NonWorkingDayPolicy = 'postpone' | 'anticipate' | 'keep';

type FieldName = 'day_of_month_mode' | 'day_of_month' | 'non_working_day_policy';

interface Props {
    frequency: string;
    dayOfMonthMode: DayOfMonthMode;
    dayOfMonth: string;
    nonWorkingDayPolicy: NonWorkingDayPolicy;
    errors?: Partial<Record<FieldName, string>>;
    onChange: (field: FieldName, value: string) => void;
}

const selectClassName =
    'mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';

export function supportsDayOfMonthMode(frequency: string): boolean {
    return frequency === 'monthly' || frequency === 'yearly';
}

export function formatRecurrenceScheduleRule(
    frequency: string,
    dayOfMonthMode: DayOfMonthMode,
    dayOfMonth: number | null,
    nonWorkingDayPolicy: NonWorkingDayPolicy,
): string {
    const dayRule = supportsDayOfMonthMode(frequency)
        ? dayOfMonthMode === 'fixed'
            ? `giorno ${dayOfMonth ?? '-'}`
            : dayOfMonthMode === 'last_day'
              ? 'ultimo giorno del mese'
              : 'giorno della data di inizio'
        : 'giorno della data di inizio';

    const holidayRule = {
        postpone: 'posticipa al primo lavorativo',
        anticipate: 'anticipa al lavorativo precedente',
        keep: 'mantiene la data',
    }[nonWorkingDayPolicy];

    return `${dayRule}; se cade in festivo o weekend: ${holidayRule}`;
}

export default function RecurrenceScheduleFields({
    frequency,
    dayOfMonthMode,
    dayOfMonth,
    nonWorkingDayPolicy,
    errors,
    onChange,
}: Props) {
    const canChooseDay = supportsDayOfMonthMode(frequency);

    useEffect(() => {
        if (!canChooseDay && dayOfMonthMode !== 'start_date') {
            onChange('day_of_month_mode', 'start_date');
            onChange('day_of_month', '');
        }
    }, [canChooseDay, dayOfMonthMode, onChange]);

    return (
        <div className="rounded-xl border border-violet-200 bg-violet-50/70 p-4 dark:border-violet-800 dark:bg-violet-950/20">
            <div className="mb-3">
                <p className="text-sm font-semibold text-violet-900 dark:text-violet-100">Regola calendario</p>
                <p className="mt-1 text-xs text-violet-800 dark:text-violet-200">
                    Scegli il giorno della ricorrenza e cosa fare quando cade in un festivo o nel weekend.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {canChooseDay && (
                    <div>
                        <InputLabel htmlFor="day_of_month_mode" value="Giorno della ricorrenza" />
                        <select
                            id="day_of_month_mode"
                            className={selectClassName}
                            value={dayOfMonthMode}
                            onChange={(event) => onChange('day_of_month_mode', event.target.value)}
                        >
                            <option value="start_date">Usa il giorno della data di inizio</option>
                            <option value="fixed">Giorno fisso del mese</option>
                            <option value="last_day">Ultimo giorno del mese</option>
                        </select>
                        <InputError message={errors?.day_of_month_mode} className="mt-2" />
                    </div>
                )}

                {canChooseDay && dayOfMonthMode === 'fixed' && (
                    <div>
                        <InputLabel htmlFor="day_of_month" value="Giorno fisso" />
                        <input
                            id="day_of_month"
                            type="number"
                            min="1"
                            max="31"
                            className={selectClassName}
                            value={dayOfMonth}
                            onChange={(event) => onChange('day_of_month', event.target.value)}
                            placeholder="Es. 5"
                        />
                        <p className="mt-1 text-xs text-violet-700 dark:text-violet-300">
                            Nei mesi più corti viene usato l&apos;ultimo giorno disponibile.
                        </p>
                        <InputError message={errors?.day_of_month} className="mt-2" />
                    </div>
                )}

                <div className={canChooseDay && dayOfMonthMode === 'fixed' ? '' : 'sm:col-span-2'}>
                    <InputLabel htmlFor="non_working_day_policy" value="Se cade in festivo o weekend" />
                    <select
                        id="non_working_day_policy"
                        className={selectClassName}
                        value={nonWorkingDayPolicy}
                        onChange={(event) => onChange('non_working_day_policy', event.target.value)}
                    >
                        <option value="postpone">Posticipa al primo giorno lavorativo</option>
                        <option value="anticipate">Anticipa al giorno lavorativo precedente</option>
                        <option value="keep">Mantieni la data originale</option>
                    </select>
                    <InputError message={errors?.non_working_day_policy} className="mt-2" />
                </div>
            </div>
        </div>
    );
}
