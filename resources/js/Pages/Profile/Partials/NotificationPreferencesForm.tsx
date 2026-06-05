import CardBox from '@/Components/CardBox';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import type { PageProps } from '@/types';

type RecurringReminderPrefs = {
    enabled: boolean;
    channels: Array<'in_app' | 'email'>;
};
type MonthlySpendingPrefs = {
    enabled: boolean;
    channels: Array<'in_app' | 'email'>;
};
type InvestmentPacReminderPrefs = {
    enabled: boolean;
    channels: Array<'in_app' | 'email'>;
};

export default function NotificationPreferencesForm() {
    const { auth } = usePage<PageProps>().props;
    const prefs = (auth.user?.preferences as Record<string, unknown> | undefined)?.notifications as
        | {
              recurring_reminder?: RecurringReminderPrefs;
              monthly_spending?: MonthlySpendingPrefs;
              investment_pac_reminder?: InvestmentPacReminderPrefs;
          }
        | undefined;

    const initial: RecurringReminderPrefs = {
        enabled: prefs?.recurring_reminder?.enabled ?? true,
        channels: prefs?.recurring_reminder?.channels ?? ['in_app', 'email'],
    };

    const initialPac: InvestmentPacReminderPrefs = {
        enabled: prefs?.investment_pac_reminder?.enabled ?? true,
        channels: prefs?.investment_pac_reminder?.channels ?? ['in_app', 'email'],
    };

    const [enabled, setEnabled] = useState(initial.enabled);
    const [inApp, setInApp] = useState(initial.channels.includes('in_app'));
    const [email, setEmail] = useState(initial.channels.includes('email'));
    const [monthlyEnabled, setMonthlyEnabled] = useState(prefs?.monthly_spending?.enabled ?? true);
    const [monthlyInApp, setMonthlyInApp] = useState((prefs?.monthly_spending?.channels ?? ['in_app']).includes('in_app'));
    const [monthlyEmail, setMonthlyEmail] = useState((prefs?.monthly_spending?.channels ?? ['in_app']).includes('email'));
    const [pacEnabled, setPacEnabled] = useState(initialPac.enabled);
    const [pacInApp, setPacInApp] = useState(initialPac.channels.includes('in_app'));
    const [pacEmail, setPacEmail] = useState(initialPac.channels.includes('email'));
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);

    const save = () => {
        setSaving(true);
        setSaved(false);
        const channels: Array<'in_app' | 'email'> = [];
        if (inApp) channels.push('in_app');
        if (email) channels.push('email');

        const monthlyChannels: Array<'in_app' | 'email'> = [
            ...(monthlyInApp ? ['in_app' as const] : []),
            ...(monthlyEmail ? ['email' as const] : []),
        ];
        const pacChannels: Array<'in_app' | 'email'> = [
            ...(pacInApp ? ['in_app' as const] : []),
            ...(pacEmail ? ['email' as const] : []),
        ];

        axios
            .patch(
                route('user.preferences.notifications'),
                {
                    recurring_reminder: {
                        enabled,
                        channels: channels.length > 0 ? channels : ['in_app'],
                    },
                    monthly_spending: {
                        enabled: monthlyEnabled,
                        channels: monthlyChannels.length > 0 ? monthlyChannels : ['in_app'],
                    },
                    investment_pac_reminder: {
                        enabled: pacEnabled,
                        channels: pacChannels.length > 0 ? pacChannels : ['in_app'],
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
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Promemoria il giorno prima di una transazione ricorrente in scadenza.
            </p>

            <label className="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input
                    type="checkbox"
                    checked={enabled}
                    onChange={(e) => setEnabled(e.target.checked)}
                    className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                Attiva promemoria ricorrenze
            </label>

            {enabled && (
                <div className="mt-3 space-y-2 pl-6">
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={inApp}
                            onChange={(e) => setInApp(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Notifica in app
                    </label>
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={email}
                            onChange={(e) => setEmail(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Email
                    </label>
                </div>
            )}

            <label className="mt-4 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
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
                    checked={pacEnabled}
                    onChange={(e) => setPacEnabled(e.target.checked)}
                    className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                />
                Attiva promemoria PAC investimenti
            </label>

            {pacEnabled && (
                <div className="mt-3 space-y-2 pl-6">
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={pacInApp}
                            onChange={(e) => setPacInApp(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Notifica in app
                    </label>
                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            checked={pacEmail}
                            onChange={(e) => setPacEmail(e.target.checked)}
                            className="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Email
                    </label>
                </div>
            )}

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
