import PrimaryButton from '@/Components/PrimaryButton';
import {
    MOBILE_BOTTOM_NAV_DEFAULT_SLOTS,
    MOBILE_BOTTOM_NAV_DESTINATIONS,
    MOBILE_BOTTOM_NAV_SLOT_COUNT,
    type MobileBottomNavDestination,
    type MobileBottomNavDestinationId,
} from '@/config/mobileBottomNav';
import { resolveMobileBottomNavSlots, setMobileBottomNavSlotsOverride } from '@/hooks/useMobileBottomNav';
import { useModules } from '@/hooks/useModules';
import { renderHubTabIcon } from '@/utils/hubTabIcons';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

function resolveInitialSlots(preferences: Record<string, unknown> | undefined): MobileBottomNavDestinationId[] {
    const raw = preferences?.mobile_bottom_nav;

    if (!Array.isArray(raw)) {
        return [...MOBILE_BOTTOM_NAV_DEFAULT_SLOTS];
    }

    const slots = raw.filter((value): value is MobileBottomNavDestinationId => typeof value === 'string');

    return slots.length === MOBILE_BOTTOM_NAV_SLOT_COUNT ? slots : [...MOBILE_BOTTOM_NAV_DEFAULT_SLOTS];
}

function MobileBottomNavPreviewItem({ destination }: { destination: MobileBottomNavDestination }) {
    return (
        <div className="flex min-w-0 w-full flex-col items-center gap-0.5 text-slate-700 dark:text-slate-200" aria-hidden="true">
            <span className="flex h-6 w-6 items-center justify-center">
                {renderHubTabIcon(destination.icon)}
            </span>
            <span className="w-full truncate text-center text-[10px] font-medium leading-none">
                {destination.label}
            </span>
        </div>
    );
}

export default function MobileBottomNavPreferencesForm() {
    const { auth } = usePage<PageProps>().props;
    const { isModuleEnabled } = useModules();
    const preferences = auth.user?.preferences as Record<string, unknown> | undefined;
    const [slots, setSlots] = useState<MobileBottomNavDestinationId[]>(() => resolveInitialSlots(preferences));
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const availableDestinations = useMemo(
        () => MOBILE_BOTTOM_NAV_DESTINATIONS.filter((destination) => {
            if (destination.moduleId && !isModuleEnabled(destination.moduleId)) {
                return false;
            }

            return true;
        }),
        [isModuleEnabled],
    );

    const previewSlots = useMemo(
        () => resolveMobileBottomNavSlots(slots, isModuleEnabled),
        [slots, isModuleEnabled],
    );

    const updateSlot = (index: number, value: MobileBottomNavDestinationId) => {
        setSlots((current) => current.map((slot, slotIndex) => (slotIndex === index ? value : slot)));
        setSaved(false);
        setError(null);
    };

    const save = () => {
        setSaving(true);
        setSaved(false);
        setError(null);

        axios
            .patch(route('user.preferences.mobile_bottom_nav'), { mobile_bottom_nav: slots })
            .then(() => {
                setSaved(true);
                setMobileBottomNavSlotsOverride(slots);
                router.reload({ only: ['auth'] });
            })
            .catch(() => {
                setError('Impossibile salvare le preferenze. Riprova.');
            })
            .finally(() => {
                setSaving(false);
            });
    };

    return (
        <div className="w-full">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                Navigazione mobile
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Scegli quali aree principali mostrare nella barra in basso su telefono e tablet.
                La barra ha quattro scorciatoie personalizzabili più la voce Altro (sempre presente).
                L&apos;azione Aggiungi/Salva è un pulsante flottante separato, non nella barra né nell&apos;header.
            </p>

            <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Anteprima
                </p>
                <div className="grid h-14 grid-cols-5 items-end rounded-xl border border-slate-200 bg-white px-1 py-2 dark:border-slate-700 dark:bg-slate-800">
                    {previewSlots.map((destination) => (
                        <MobileBottomNavPreviewItem key={destination.id} destination={destination} />
                    ))}
                    <div className="flex min-w-0 flex-1 flex-col items-center gap-0.5 text-slate-700 dark:text-slate-200" aria-hidden="true">
                        <span className="flex h-6 w-6 items-center justify-center text-base">☰</span>
                        <span className="w-full truncate text-center text-[10px] font-medium leading-none">Altro</span>
                    </div>
                </div>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {(['Posizione 1', 'Posizione 2', 'Posizione 3', 'Posizione 4'] as const).map((label, index) => (
                    <div key={label}>
                        <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {label}
                        </label>
                        <select
                            value={slots[index]}
                            onChange={(event) => updateSlot(index, event.target.value as MobileBottomNavDestinationId)}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        >
                            {availableDestinations.map((destination) => (
                                <option
                                    key={destination.id}
                                    value={destination.id}
                                    disabled={slots.some((slot, slotIndex) => slotIndex !== index && slot === destination.id)}
                                >
                                    {destination.label}
                                </option>
                            ))}
                        </select>
                    </div>
                ))}
            </div>

            {error && (
                <p className="mt-3 text-sm text-rose-600 dark:text-rose-400">{error}</p>
            )}

            <div className="mt-5 flex items-center gap-3">
                <PrimaryButton type="button" onClick={save} disabled={saving}>
                    {saving ? 'Salvataggio...' : 'Salva preferenze'}
                </PrimaryButton>
                {saved && (
                    <span className={clsx('text-sm text-emerald-600 dark:text-emerald-400')}>
                        Preferenze salvate
                    </span>
                )}
            </div>
        </div>
    );
}
