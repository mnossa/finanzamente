import CardBox from '@/Components/CardBox';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import type { PageProps } from '@/types';

type DueFrequency = 'daily' | 'weekly' | 'never';

type UpcomingDuePrefs = {
    frequency: DueFrequency;
    channels: Array<'in_app' | 'email'>;
};

type MonthlySpendingPrefs = {
    enabled: boolean;
    channels: Array<'in_app' | 'email'>;
};

function resolveInitialFrequency(prefs: {
    upcoming_due_dates?: UpcomingDuePrefs;
    recurring_reminder?: { enabled: boolean };
    investment_pac_reminder?: { enabled: boolean };
} | undefined): DueFrequency {
    if (prefs?.upcoming_due_dates?.frequency) {
        return prefs.upcoming_due_dates.frequency;
    }

    const recurringEnabled = prefs?.recurring_reminder?.enabled ?? true;
    const pacEnabled = prefs?.investment_pac_reminder?.enabled ?? true;

    if (!recurringEnabled && !pacEnabled) {
        return 'never';
    }

    return 'daily';
}

function resolveInitialChannels(prefs: {
    upcoming_due_dates?: UpcomingDuePrefs;
    recurring_reminder?: { channels?: Array<'in_app' | 'email'> };
} | undefined): Array<'in_app' | 'email'> {
    if (prefs?.upcoming_due_dates?.channels?.length) {
        return prefs.upcoming_due_dates.channels;
    }

    return prefs?.recurring_reminder?.channels ?? ['in_app', 'email'];
}

export default function NotificationPreferencesForm() {
    const { auth } = usePage<PageProps>().props;
    const prefs = (auth.user?.preferences as Record<string, unknown> | undefined)?.notifications as
        | {
              upcoming_due_dates?: UpcomingDuePrefs;
              recurring_reminder?: { enabled: boolean; channels?: Array<'in_app' | 'email'> };
              investment_pac_reminder?: { enabled: boolean };
              monthly_spending?: MonthlySpendingPrefs;
              educational_suggestions?: { enabled: boolean };
          }
        | undefined;

    const [dueFrequency, setDueFrequency] = useState<DueFrequency>(resolveInitialFrequency(prefs));
    const [dueInApp, setDueInApp] = useState(resolveInitialChannels(prefs).includes('in_app'));
    const [dueEmail, setDueEmail] = useState(resolveInitialChannels(prefs).includes('email'));
    const [monthlyEnabled, setMonthlyEnabled] = useState(prefs?.monthly_spending?.enabled ?? true);
    const [monthlyInApp, setMonthlyInApp] = useState((prefs?.monthly_spending?.channels ?? ['in_app']).includes('in_app'));
    const [monthlyEmail, setMonthlyEmail] = useState((prefs?.monthly_spending?.channels ?? ['in_app']).includes('email'));
    const [suggestionsEnabled, setSuggestionsEnabled] = useState(prefs?.educational_suggestions?.enabled ?? true);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);

    const save = () => {
        setSaving(true);
        setSaved(false);

        const dueChannels: Array<'in_app' | 'email'> = [
            ...(dueInApp ? ['in_app' as const] : []),
            ...(dueEmail ? ['email' as const] : []),
        ];

        const monthlyChannels: Array<'in_app' | 'email'> = [
            ...(monthlyInApp ? ['in_app' as const] : []),
            ...(monthlyEmail ? ['email' as const] : []),
        ];

        axios
            .patch(
                route('user.preferences.notifications'),
                {
                    upcoming_due_dates: {
                        frequency: dueFrequency,
                        channels: dueFrequency === 'never'
                            ? (dueChannels.length > 0 ? dueChannels : ['in_app'])
                            : (dueChannels.length > 0 ? dueChannels : ['in_app']),
                    },
                    monthly_spending: {
                        enabled: monthlyEnabled,
                        channels: monthlyChannels.length > 0 ? monthlyChannels : ['in_app'],
                    },
                    educational_suggestions: {
                        enabled: suggestionsEnabled,
                    },
                },
                {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                        Accept: 'application/json',
                    },
                    withCredentials: true,
                },
            )
            .then(() => setSaved(true))
            .finally(() => setSaving(false));
    };

    return (
        <CardBox>
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Notifiche</h3>

            <div className="mt-4">
                <p className="text-sm font-medium text-gray-800 dark:text-gray-200">Prossime scadenze</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Ricorrenze e PAC programmati: scegli quando ricevere un promemoria, solo se ci sono scadenze imminenti.
                </p>

                <fieldset className="mt-3 space-y-2">
                    <label className="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="radio"
                            name="upcoming_due_frequency"
                            value="daily"
                            checked={dueFrequency === 'daily'}
                            onChange={() => setDueFrequency('daily')}
                            className="mt-0.5 border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>
                            <span className="font-medium">Ogni giorno</span>
                            <span className="block text-xs text-gray-500 dark:text-gray-400">
                                Consigliato — promemoria il giorno prima di ogni scadenza.
                            </span>
                        </span>
                    </label>
                    <label className="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="radio"
                            name="upcoming_due_frequency"
                            value="weekly"
                            checked={dueFrequency === 'weekly'}
                            onChange={() => setDueFrequency('weekly')}
                            className="mt-0.5 border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>
                            <span className="font-medium">Una volta a settimana</span>
                            <span className="block text-xs text-gray-500 dark:text-gray-400">
                                Riepilogo del lunedì con le scadenze dei prossimi 7 giorni.
                            </span>
                        </span>
                    </label>
                    <label className="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="radio"
                            name="upcoming_due_frequency"
                            value="never"
                            checked={dueFrequency === 'never'}
                            onChange={() => setDueFrequency('never')}
                            className="mt-0.5 border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>
                            <span className="font-medium">Mai</span>
                            <span className="block text-xs text-amber-600 dark:text-amber-400">
                                Sconsigliato — potresti dimenticare pagamenti o versamenti importanti.
                            </span>
                        </span>
                    </label>
                </fieldset>

                {dueFrequency !== 'never' && (
                    <div className="mt-3 space-y-2 pl-1">
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={dueInApp}
                                onChange={(e) => setDueInApp(e.target.checked)}
                                className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            Notifica in app
                        </label>
                        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={dueEmail}
                                onChange={(e) => setDueEmail(e.target.checked)}
                                className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            Email
                        </label>
                    </div>
                )}
            </div>

            <label className="mt-6 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    checked={monthlyEnabled}
                    onChange={(e) => setMonthlyEnabled(e.target.checked)}
                    className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                Attiva riepilogo spesa mensile
            </label>

            {monthlyEnabled && (
                <div className="mt-3 space-y-2 pl-6">
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={monthlyInApp}
                            onChange={(e) => setMonthlyInApp(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Notifica in app
                    </label>
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={monthlyEmail}
                            onChange={(e) => setMonthlyEmail(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Email
                    </label>
                </div>
            )}

            <label className="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    checked={suggestionsEnabled}
                    onChange={(e) => setSuggestionsEnabled(e.target.checked)}
                    className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                Suggerimenti educativi in app (trend, duplicati, promemoria contestuali)
            </label>

            <div className="mt-4 flex items-center gap-3">
                <button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                >
                    {saving ? 'Salvataggio...' : 'Salva preferenze'}
                </button>
                {saved && <span className="text-sm text-emerald-600">Salvato</span>}
            </div>
        </CardBox>
    );
}
