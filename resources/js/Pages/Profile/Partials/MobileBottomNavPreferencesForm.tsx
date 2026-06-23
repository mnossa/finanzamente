import PrimaryButton from '@/Components/PrimaryButton';
import {
    MOBILE_BOTTOM_NAV_DEFAULT_SLOTS,
    MOBILE_BOTTOM_NAV_DESTINATIONS,
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

    return slots.length === 3 ? slots : [...MOBILE_BOTTOM_NAV_DEFAULT_SLOTS];
}

function MobileBottomNavPreviewItem({ destination }: { destination: MobileBottomNavDestination }) {
    return (
        <div className="flex min-w-0 flex-col items-center gap-1 text-slate-700 dark:text-slate-200">
            <span aria-hidden="true">{renderHubTabIcon(destination.icon)}</span>
            <span className="max-w-[4.5rem] truncate text-[10px] font-medium">{destination.label}</span>
        </div>
    );
}

export default function MobileBottomNavPreferencesForm() {
    const { auth, plan } = usePage<PageProps>().props;
    const { isModuleEnabled } = useModules();
    const isProPlan = plan?.current === 'pro';

    const preferences = auth.user?.preferences as Record<string, unknown> | undefined;
    const [slots, setSlots] = useState<MobileBottomNavDestinationId[]>(() => resolveInitialSlots(preferences));
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const availableDestinations = useMemo(
        () => MOBILE_BOTTOM_NAV_DESTINATIONS.filter((destination) => {
            if (destination.requiresPro && !isProPlan) {
                return false;
            }

            if (destination.moduleId && !isModuleEnabled(destination.moduleId)) {
                return false;
            }

            return true;
        }),
        [isModuleEnabled, isProPlan],
    );

    const previewSlots = useMemo(
        () => resolveMobileBottomNavSlots(slots, isModuleEnabled, isProPlan),
        [slots, isModuleEnabled, isProPlan],
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
                Scegli quali aree principali mostrare nella barra in basso. Il pulsante centrale (+) e la voce Altro restano sempre disponibili.
            </p>

            <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40">
                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Anteprima
                </p>
                <div className="flex items-center justify-around rounded-xl border border-slate-200 bg-white px-2 py-3 dark:border-slate-700 dark:bg-slate-800">
                    {previewSlots.slice(0, 2).map((destination) => (
                        <MobileBottomNavPreviewItem key={destination.id} destination={destination} />
                    ))}
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-white dark:border-slate-800" aria-hidden="true">
                        +
                    </div>
                    {previewSlots[2] && (
                        <MobileBottomNavPreviewItem destination={previewSlots[2]} />
                    )}
                    <div className="flex min-w-0 flex-col items-center gap-1 text-slate-700 dark:text-slate-200">
                        <span className="text-xs">☰</span>
                        <span className="text-[10px] font-medium">Altro</span>
                    </div>
                </div>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-3">
                {(['Sinistra', 'Centro-sinistra', 'Destra (prima di Altro)'] as const).map((label, index) => (
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
