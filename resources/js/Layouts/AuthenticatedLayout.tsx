import clsx from 'clsx';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { useHeaderNotifications } from '@/hooks/useHeaderNotifications';
import { nav } from '@/utils/analytics';
import Dropdown from '@/Components/Dropdown';
import ThemeToggle from '@/Components/ThemeToggle';
import BalancePrivacyToggle from '@/Components/BalancePrivacyToggle';
import ProBadge from '@/Components/ProBadge';
import { ActiveHousehold, AppNotification, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import React, { PropsWithChildren, ReactNode, useState, useEffect, FormEvent, useRef } from 'react';
import { useModules } from '@/hooks/useModules';
import UmamiAnalytics from '@/Components/UmamiAnalytics';
import OfflineGate from '@/Components/OfflineGate';
import PwaInstallBanner from '@/Components/PwaInstallBanner';
import axios from 'axios';
import { FM_MOBILE_PRIMARY_FORM_ID, resolveMobilePrimaryFab } from '@/utils/mobilePrimaryFab';

const BLADE_ANALYTICS_CONSENT_KEY = 'fm_analytics_consent';
type UINotification = AppNotification & { action_url?: string | null; severity?: 'info' | 'warning' | 'critical' };

// Icone SVG inline per evitare dipendenze esterne
const Icons = {
    Menu: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="4" x2="20" y1="12" y2="12" /><line x1="4" x2="20" y1="6" y2="6" /><line x1="4" x2="20" y1="18" y2="18" />
        </svg>
    ),
    X: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M18 6 6 18" /><path d="m6 6 12 12" />
        </svg>
    ),
    Dashboard: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />
        </svg>
    ),
    Wallet: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1" /><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4" />
        </svg>
    ),
    ArrowLeftRight: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M8 3 4 7l4 4" /><path d="M4 7h16" /><path d="m16 21 4-4-4-4" /><path d="M20 17H4" />
        </svg>
    ),
    Tags: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19" /><path d="M9.586 5.586A2 2 0 0 0 8.172 5H3a1 1 0 0 0-1 1v5.172a2 2 0 0 0 .586 1.414L8 18.414a2 2 0 0 0 2.828 0L17 12.172a2 2 0 0 0 0-2.828L9.586 5.586Z" /><circle cx="6.5" cy="9.5" r="1.5" />
        </svg>
    ),
    Repeat: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="m17 2 4 4-4 4" /><path d="M3 11v-1a4 4 0 0 1 4-4h14" /><path d="m7 22-4-4 4-4" /><path d="M21 13v1a4 4 0 0 1-4 4H3" />
        </svg>
    ),
    Transfer: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 2v20" /><path d="m17 7-5-5-5 5" /><path d="m17 17-5 5-5-5" />
        </svg>
    ),
    Undo: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 7v6h6" /><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13" />
        </svg>
    ),
    PiggyBank: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M19 5c-1.5 0-2.8 1.4-3 2-3.5-1.5-11-.3-11 5 0 1.8 0 3 2 4.5V20h4v-2h3v2h4v-4c1-.5 1.7-1 2-2h2v-4h-2c0-1-.5-1.5-1-2h0V5z" /><path d="M2 9v1c0 1.1.9 2 2 2h1" /><path d="M16 11h0" />
        </svg>
    ),
    Target: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />
        </svg>
    ),
    HandCoins: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17" /><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9" /><path d="m2 16 6 6" /><circle cx="16" cy="9" r="2.9" /><circle cx="6" cy="5" r="3" />
        </svg>
    ),
    TrendingUp: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" /><polyline points="16 7 22 7 22 13" />
        </svg>
    ),
    BarChart2: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="18" x2="18" y1="20" y2="10" /><line x1="12" x2="12" y1="20" y2="4" /><line x1="6" x2="6" y1="20" y2="14" />
        </svg>
    ),
    Home: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" />
        </svg>
    ),
    Shield: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        </svg>
    ),
    Settings: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" /><circle cx="12" cy="12" r="3" />
        </svg>
    ),
    User: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
        </svg>
    ),
    LogOut: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" x2="9" y1="12" y2="12" />
        </svg>
    ),
    ChevronDown: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="m6 9 6 6 6-6" />
        </svg>
    ),
    ChevronRight: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="m9 18 6-6-6-6" />
        </svg>
    ),
    Bell: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
    ),
    Search: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
        </svg>
    ),
    Briefcase: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect width="20" height="14" x="2" y="7" rx="2" ry="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
        </svg>
    ),
    Simulation: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 3v18h18" /><path d="m19 9-5 5-4-4-3 3" />
        </svg>
    ),
    Zap: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z" />
        </svg>
    ),
};

// Tipo per gli elementi di navigazione
interface NavigationItem {
    name: string;
    href: string;
    routeMatch: string;
    icon: () => React.JSX.Element;
    altRouteMatch?: string;
    excludeRouteMatch?: string;
    routeMatchPatterns?: string[];
    hrefParams?: Record<string, string | number | boolean | null | undefined>;
    moduleId?: string;
    requiresPro?: boolean;
}

// Tipo per le sezioni di navigazione
interface NavigationSection {
    title: string;
    items: NavigationItem[];
    defaultExpanded?: boolean;
}

// Definizione sezioni menu navigazione
const navigationSections: NavigationSection[] = [
    {
        title: 'Panoramica',
        defaultExpanded: true,
        items: [
            { name: 'Dashboard', href: 'dashboard', routeMatch: 'dashboard', icon: Icons.Dashboard, moduleId: 'dashboard' },
            { name: 'Widget a formula', href: 'formula-marketplace.index', routeMatch: 'formula-*', icon: Icons.BarChart2 },
            { name: 'Simulazioni', href: 'simulations.public', routeMatch: 'simulations.*', icon: Icons.Simulation },
        ]
    },
    {
        title: 'Conti & Movimenti',
        defaultExpanded: true,
        items: [
            {
                name: 'Conti e movimenti',
                href: 'transactions.index',
                routeMatch: 'transactions.index',
                routeMatchPatterns: ['accounts.*', 'transactions.*', 'transfers.*', 'inter-household-transfers.*'],
                icon: Icons.ArrowLeftRight,
            },
        ],
    },
    {
        title: 'Organizzazione',
        defaultExpanded: false,
        items: [
            {
                name: 'Organizzazione',
                href: 'categories.index',
                routeMatch: 'categories.index',
                routeMatchPatterns: ['categories.*', 'inbox.*', 'refunds.*', 'recurring-transactions.*'],
                icon: Icons.Tags,
            },
        ],
    },
    {
        title: 'Pianificazione & Risparmio',
        defaultExpanded: false,
        items: [
            {
                name: 'Pianificazione',
                href: 'budgets.index',
                routeMatch: 'budgets.index',
                routeMatchPatterns: ['budgets.*', 'debts-credits.*', 'financial-goals.*', 'tax-deductions.*'],
                icon: Icons.PiggyBank,
            },
        ],
    },
    {
        title: 'Investimenti',
        defaultExpanded: false,
        items: [
            {
                name: 'Investimenti',
                href: 'investments.index',
                routeMatch: 'investments.index',
                routeMatchPatterns: [
                    'investments.*',
                    'investment-pacs.*',
                    'asset-allocation.*',
                    'investment-assets.*',
                    'investment-analyses.*',
                ],
                icon: Icons.TrendingUp,
                moduleId: 'investments',
                requiresPro: true,
            },
        ],
    },
];

function PlanAlertBanner() {
    const { plan } = usePage<PageProps>().props;
    const [dismissed, setDismissed] = useState(false);

    if (!plan || dismissed) return null;

    const isExpiring = plan.current === 'pro' && plan.expires_at !== null;
    const hasExcess = plan.current === 'base' && (plan.excess_accounts > 0 || plan.excess_households > 0);

    if (!isExpiring && !hasExcess) return null;

    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
            {isExpiring && (
                <div className="relative flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                    <span className="mt-0.5 shrink-0 text-amber-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><path d="M12 9v4" /><path d="M12 17h.01" />
                        </svg>
                    </span>
                    <span className="flex-1">
                        {plan.days_until_expiry === 0
                            ? 'Il tuo piano Pro scade oggi.'
                            : plan.days_until_expiry === 1
                                ? 'Il tuo piano Pro scade domani.'
                                : `Il tuo piano Pro scade tra ${plan.days_until_expiry} giorni.`
                        }
                        {' '}
                        <Link href={route('profile.subscription')} className="font-semibold underline hover:no-underline">
                            Rinnova ora
                        </Link>
                    </span>
                    <button
                        onClick={() => setDismissed(true)}
                        className="shrink-0 hover:opacity-70 transition-opacity"
                        aria-label="Chiudi avviso"
                    >
                        <Icons.X />
                    </button>
                </div>
            )}
            {hasExcess && (
                <div className={clsx('relative flex items-start gap-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-800 dark:text-rose-200', isExpiring && 'mt-2')}>
                    <span className="mt-0.5 shrink-0 text-rose-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10" /><path d="M12 8v4" /><path d="M12 16h.01" />
                        </svg>
                    </span>
                    <span className="flex-1">
                        Il tuo account è stato degradato al piano Base.
                        {plan.excess_accounts > 0 && ` Hai ${plan.excess_accounts} conto/i in eccesso rispetto al limite (5).`}
                        {plan.excess_households > 0 && ` Hai ${plan.excess_households} household in eccesso rispetto al limite (1).`}
                        {' '}
                        <Link href={route('profile.subscription')} className="font-semibold underline hover:no-underline">
                            Passa a Pro
                        </Link>
                        {' '}per riattivare l'accesso completo.
                    </span>
                    <button
                        onClick={() => setDismissed(true)}
                        className="shrink-0 hover:opacity-70 transition-opacity"
                        aria-label="Chiudi avviso"
                    >
                        <Icons.X />
                    </button>
                </div>
            )}
        </div>
    );
}

function FlashMessages() {
    const { flash } = usePage<PageProps>().props;
    const [visible, setVisible] = useState(false);
    const [message, setMessage] = useState<{ type: string; text: string } | null>(null);

    useEffect(() => {
        if (flash?.success) {
            setMessage({ type: 'success', text: flash.success });
            setVisible(true);
        } else if (flash?.error) {
            setMessage({ type: 'error', text: flash.error });
            setVisible(true);
        } else if (flash?.info) {
            setMessage({ type: 'info', text: flash.info });
            setVisible(true);
        }
    }, [flash]);

    useEffect(() => {
        if (visible) {
            const timer = setTimeout(() => setVisible(false), 5000);
            return () => clearTimeout(timer);
        }
    }, [visible]);

    if (!visible || !message) return null;

    const alertClasses = {
        success: 'alert-success',
        error: 'alert-error',
        info: 'alert-info',
    }[message.type];

    return (
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
            <div className={clsx('alert pr-8', alertClasses)} role="alert">
                <span className="block sm:inline">{message.text}</span>
                <button
                    className="absolute top-0 bottom-0 right-0 px-2 py-2 hover:opacity-70 transition-opacity"
                    onClick={() => setVisible(false)}
                >
                    <Icons.X />
                </button>
            </div>
        </div>
    );
}

// Componente NavItem per la sidebar
function SidebarNavItem({
    item,
    isActive,
    isPro,
    onClick
}: {
    item: NavigationItem;
    isActive: boolean;
    isPro: boolean;
    onClick?: () => void;
}) {
    const Icon = item.icon;
    const lockedByPlan = item.requiresPro && !isPro;
    const fromParam = lockedByPlan && item.moduleId ? `?from=${item.moduleId}` : '';
    const href = lockedByPlan
        ? route('profile.subscription') + fromParam
        : (item.hrefParams ? route(item.href, item.hrefParams) : route(item.href));

    return (
        <Link
            href={href}
            onClick={onClick}
            className={clsx(
                'flex items-center w-full px-4 py-3 text-sm font-medium rounded-xl',
                'transition-all duration-200 group',
                lockedByPlan
                    ? 'text-slate-500 hover:bg-slate-800/50 hover:text-slate-300'
                    : isActive
                        ? 'bg-emerald-500/10 text-emerald-400'
                        : 'text-slate-400 hover:bg-slate-800 hover:text-white'
            )}
        >
            <span className={clsx(
                lockedByPlan
                    ? 'text-slate-600 group-hover:text-slate-400'
                    : isActive ? 'text-emerald-400' : 'text-slate-500 group-hover:text-white',
                'transition-colors duration-200'
            )}>
                <Icon />
            </span>
            <span className="ml-3 flex-1">{item.name}</span>
            {lockedByPlan && <ProBadge className="ml-auto" />}
            {!lockedByPlan && isActive && (
                <div className="ml-auto indicator-dot" />
            )}
        </Link>
    );
}

// Componente per sezioni espandibili
function CollapsibleNavSection({
    section,
    isRouteActive,
    onClick,
    forceExpanded = false,
    isPro,
}: {
    section: NavigationSection;
    isRouteActive: (
        routeMatch: string,
        altRouteMatch?: string,
        excludeRouteMatch?: string,
        routeMatchPatterns?: string[],
    ) => boolean;
    onClick?: () => void;
    forceExpanded?: boolean;
    isPro: boolean;
}) {
    const [isExpanded, setIsExpanded] = useState(section.defaultExpanded ?? false);

    // Controlla se qualche elemento della sezione è attivo
    const hasActiveItem = section.items.some(item =>
        isRouteActive(item.routeMatch, item.altRouteMatch, item.excludeRouteMatch, item.routeMatchPatterns)
    );

    // Espande automaticamente se c'è un elemento attivo nella sezione
    useEffect(() => {
        if (hasActiveItem && !isExpanded) {
            setIsExpanded(true);
        }
    }, [hasActiveItem, isExpanded]);

    const effectivelyExpanded = forceExpanded || isExpanded;
    const isSingleItem = section.items.length === 1;

    if (isSingleItem) {
        const item = section.items[0];
        return (
            <div className="mb-2">
                <SidebarNavItem
                    item={item}
                    isActive={isRouteActive(item.routeMatch, item.altRouteMatch, item.excludeRouteMatch, item.routeMatchPatterns)}
                    isPro={isPro}
                    onClick={onClick}
                />
            </div>
        );
    }

    return (
        <div className="mb-2">
            {/* Header della sezione */}
            <button
                onClick={() => !forceExpanded && setIsExpanded(!isExpanded)}
                className={clsx(
                    'flex items-center justify-between w-full px-4 py-2 text-xs font-semibold uppercase tracking-wider text-left',
                    forceExpanded
                        ? 'text-slate-500 cursor-default'
                        : 'text-slate-400 hover:text-slate-300 transition-colors'
                )}
            >
                <span>{section.title}</span>
                {!forceExpanded && (
                    <span className={clsx(
                        'transition-transform duration-200',
                        effectivelyExpanded ? 'rotate-90' : ''
                    )}>
                        <Icons.ChevronRight />
                    </span>
                )}
            </button>

            {/* Elementi della sezione */}
            <div className={clsx(
                'overflow-hidden transition-all duration-300 ease-in-out space-y-1',
                effectivelyExpanded ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'
            )}>
                {section.items.map((item) => (
                    <SidebarNavItem
                        key={item.name}
                        item={item}
                        isActive={isRouteActive(item.routeMatch, item.altRouteMatch, item.excludeRouteMatch, item.routeMatchPatterns)}
                        isPro={isPro}
                        onClick={onClick}
                    />
                ))}
            </div>
        </div>
    );
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth, activeHousehold, notifications: sharedNotifications, plan: planData, isAdmin, privacy } = usePage<PageProps>().props;
    const { notifications } = useHeaderNotifications(sharedNotifications);
    const features = usePage<PageProps & { features?: Record<string, boolean> }>().props.features ?? {};
    const user = auth.user;
    const { isModuleEnabled, isPro } = useModules();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [navSearch, setNavSearch] = useState('');
    const notifRef = useRef<HTMLDivElement>(null);
    const analyticsSyncInFlight = useRef(false);

    const handleLogout = async (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = e.currentTarget as HTMLFormElement;
        try {
            await axios.post(form.action, {}, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                withCredentials: true,
            });
            window.location.href = '/';
        } catch (error) {
            // Gestione errore opzionale
        }
    };

    // Chiude la sidebar quando si ridimensiona la finestra a desktop
    useEffect(() => {
        const handleResize = () => {
            if (window.innerWidth >= 1024) {
                setSidebarOpen(false);
            }
        };
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    // Chiude il pannello notifiche quando si clicca fuori
    useEffect(() => {
        if (!notifOpen) return;
        const handleClickOutside = (e: MouseEvent) => {
            if (notifRef.current && !notifRef.current.contains(e.target as Node)) {
                setNotifOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [notifOpen]);

    useEffect(() => {
        if (analyticsSyncInFlight.current) return;

        const storedConsent = window.localStorage.getItem(BLADE_ANALYTICS_CONSENT_KEY);
        if (storedConsent !== 'accepted' && storedConsent !== 'rejected') return;

        const shouldEnableAnalytics = storedConsent === 'accepted';
        const currentAnalyticsEnabled = privacy?.analytics_enabled ?? false;

        if (shouldEnableAnalytics === currentAnalyticsEnabled) {
            return;
        }

        analyticsSyncInFlight.current = true;
        router.post(
            route('profile.consents.sync-analytics'),
            {
                analytics_tracking: shouldEnableAnalytics,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    analyticsSyncInFlight.current = false;
                },
                onError: () => {
                    analyticsSyncInFlight.current = false;
                },
            }
        );
    }, [privacy?.analytics_enabled]);

    const isRouteActive = (
        routeMatch: string,
        altRouteMatch?: string,
        excludeRouteMatch?: string,
        routeMatchPatterns?: string[],
    ): boolean => {
        if (excludeRouteMatch && route().current(excludeRouteMatch)) return false;
        if (route().current(routeMatch)) return true;
        if (altRouteMatch && route().current(altRouteMatch)) return true;
        if (routeMatchPatterns?.some((pattern) => route().current(pattern))) return true;
        return false;
    };

    // Filtra le sezioni in base ai moduli disponibili.
    // Le voci Pro sono sempre incluse (gestite da SidebarNavItem con ProBadge).
    // Le voci bloccate da impostazioni profilo (non pro) vengono nascoste.
    const filterSectionsByModules = (sections: NavigationSection[]): NavigationSection[] => {
        return sections
            .map(section => ({
                ...section,
                items: section.items.filter(item => {
                    if (item.href === 'transactions.quick-session' && features.quick_session_enabled === false) {
                        return false;
                    }
                    // Le voci Pro sono sempre visibili (badge + redirect)
                    if (item.requiresPro) return true;
                    // Le voci senza moduleId sono sempre visibili
                    if (!item.moduleId) return true;
                    // Le altre voci si basano sull'abilitazione del modulo (profilo)
                    return isModuleEnabled(item.moduleId);
                }),
            }))
            .filter(section => section.items.length > 0);
    };

    // Crea le sezioni dinamicamente includendo Household se presente
    const baseNavigationSections = filterSectionsByModules(navigationSections);

    const getFilteredSections = (sections: NavigationSection[]): NavigationSection[] => {
        if (!navSearch.trim()) return sections;
        const q = navSearch.toLowerCase();
        return sections
            .map(section => ({
                ...section,
                items: section.items.filter(item => item.name.toLowerCase().includes(q)),
            }))
            .filter(section => section.items.length > 0);
    };

    const allNavigationSections = [
        ...baseNavigationSections,
        ...(activeHousehold ? [{
            title: 'Household',
            defaultExpanded: false,
            items: [
                {
                    name: activeHousehold.name,
                    href: 'households.show',
                    routeMatch: 'households.show',
                    icon: Icons.Home,
                    hrefParams: { household: activeHousehold.id }
                },
                {
                    name: 'Cambia Household',
                    href: 'households.select',
                    routeMatch: 'households.select',
                    icon: Icons.Settings
                },
            ]
        }] : [])
    ];

    const filteredNavigationSections = getFilteredSections(allNavigationSections);

    return (
        <OfflineGate>
        <>
            <UmamiAnalytics enabled={privacy?.analytics_enabled ?? false} />
            <div className="flex h-screen bg-slate-50 dark:bg-slate-900 font-sans text-slate-800 dark:text-slate-100 overflow-hidden">
                {/* Overlay Mobile */}
                {sidebarOpen && (
                    <div
                        className="fixed inset-0 bg-slate-900/50 z-40 lg:hidden backdrop-blur-sm animate-fade-in"
                        onClick={() => setSidebarOpen(false)}
                    />
                )}

                {/* Sidebar */}
                <aside
                    className={clsx(
                        'fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white pr-1',
                        'flex flex-col',
                        'transition-transform duration-300 ease-in-out',
                        'lg:translate-x-0 lg:static lg:flex',
                        'shadow-sidebar lg:shadow-none',
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                    )}
                >
                    {/* Sidebar Header */}
                    <div className="flex items-center justify-between h-20 px-6 border-b border-slate-700 bg-slate-950 shrink-0">
                        <a href="/" className="flex items-center gap-3 font-bold text-xl tracking-tight">
                            <ApplicationLogo className="w-8 h-8" />
                            <span className="text-white">Finanzamente</span>
                        </a>
                        <button
                            onClick={() => setSidebarOpen(false)}
                            className="lg:hidden text-slate-400 hover:text-white transition-colors p-1"
                            aria-label="Chiudi menu"
                        >
                            <Icons.X />
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 mt-1 p-4 space-y-1 overflow-y-auto min-h-0" aria-label="Navigazione principale">
                        {/* Ricerca nel menu */}
                        <div className="relative mb-3">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none">
                                <Icons.Search />
                            </span>
                            <input
                                type="text"
                                placeholder="Cerca nel menu..."
                                value={navSearch}
                                onChange={e => setNavSearch(e.target.value)}
                                className="w-full pl-9 pr-3 py-2 bg-slate-800 text-slate-200 placeholder-slate-500 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40 transition"
                            />
                        </div>

                        {filteredNavigationSections.length === 0 ? (
                            <p className="px-4 py-6 text-center text-xs text-slate-500">Nessun risultato</p>
                        ) : (
                            filteredNavigationSections.map((section) => (
                                <CollapsibleNavSection
                                    key={section.title}
                                    section={section}
                                    isRouteActive={isRouteActive}
                                    onClick={() => setSidebarOpen(false)}
                                    forceExpanded={!!navSearch.trim()}
                                    isPro={isPro}
                                />
                            ))
                        )}
                    </nav>

                    {/* CTA Passa a Pro per utenti base */}
                    {planData?.current === 'base' && (
                        <div className="px-4 pb-3 shrink-0">
                            <Link
                                href={route('profile.subscription')}
                                className="flex items-center justify-center gap-2 w-full py-2 px-4 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-colors"
                            >
                                <span>⭐</span>
                                <span>{planData.waitlist_enabled ? 'Scopri Pro' : 'Passa a Pro'}</span>
                            </Link>
                        </div>
                    )}

                    {/* User Profile Bottom */}
                    <div className="shrink-0 w-full p-4 bg-slate-950 border-t border-slate-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 font-semibold">
                                {user.name.charAt(0).toUpperCase()}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-white truncate">{user.name}</p>
                                <p className="text-xs text-slate-400 truncate">{user.email}</p>
                            </div>
                        </div>
                    </div>
                </aside>

                {/* Main Content */}
                <div className="flex-1 flex flex-col overflow-hidden">
                    {/* Header */}
                    <header className="app-header dark:bg-slate-800/80 dark:border-slate-700">
                        <div className="flex min-w-0 flex-1 items-center gap-2 lg:gap-3">
                            {header && (
                                <div className="min-w-0 flex-1">
                                    {header}
                                </div>
                            )}
                        </div>

                        <div className="flex shrink-0 items-center gap-1.5 sm:gap-2 lg:gap-3">
                            {/* Search - Desktop only */}
                            {/*<div className="relative hidden md:block">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <Icons.Search />
                                </span>
                                <input
                                    type="text"
                                    placeholder="Cerca..."
                                    className="pl-10 pr-4 py-2 bg-slate-100 border-transparent rounded-xl text-sm w-56 
                                         focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 
                                         transition-all outline-none"
                                />
                            </div>*/}

                            {/* Notifications */}
                            <div className="relative" ref={notifRef}>
                                <button
                                    onClick={() => setNotifOpen((prev) => !prev)}
                                    className="relative rounded-xl p-1.5 text-slate-500 transition-colors hover:bg-slate-100 sm:p-2 dark:text-slate-400 dark:hover:bg-slate-700"
                                    aria-label="Notifiche"
                                >
                                    <Icons.Bell />
                                    {notifications.unread_count > 0 && (
                                        <span className="absolute top-1.5 right-1.5 min-w-4 h-4 px-0.5 flex items-center justify-center bg-rose-500 text-white text-[10px] font-bold rounded-full border-2 border-white dark:border-slate-800">
                                            {notifications.unread_count > 9 ? '9+' : notifications.unread_count}
                                        </span>
                                    )}
                                </button>

                                {notifOpen && (
                                    <div
                                        className={clsx(
                                            'z-50 rounded-xl shadow-lg border border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden',
                                            // Mobile: ancoraggio al viewport — evita che w-80 esca a sinistra rispetto al trigger
                                            'max-lg:fixed max-lg:left-3 max-lg:right-3 max-lg:top-22 max-lg:w-auto',
                                            // Desktop: sotto la campanella, larghezza fissa
                                            'lg:absolute lg:right-0 lg:left-auto lg:top-full lg:mt-2 lg:w-96',
                                        )}
                                    >
                                        {/* Intestazione */}
                                        <div className="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                                            <span className="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                                Notifiche
                                                {notifications.unread_count > 0 && (
                                                    <span className="ml-2 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400">
                                                        {notifications.unread_count}
                                                    </span>
                                                )}
                                            </span>
                                            {notifications.unread_count > 0 && (
                                                <button
                                                    onClick={() => {
                                                        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
                                                        setNotifOpen(false);
                                                    }}
                                                    className="text-xs text-emerald-600 hover:underline dark:text-emerald-400"
                                                >
                                                    Segna tutte come lette
                                                </button>
                                            )}
                                        </div>

                                        {/* Lista notifiche */}
                                        <ul className="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700">
                                            {notifications.items.length === 0 ? (
                                                <li className="px-4 py-6 text-center text-sm text-slate-400 dark:text-slate-500">
                                                    Nessuna notifica
                                                </li>
                                            ) : (
                                                notifications.items.map((notif: UINotification) => (
                                                    <li
                                                        key={notif.id}
                                                        className={clsx(
                                                            'px-4 py-3 flex items-start gap-3 transition-colors',
                                                            notif.severity === 'critical'
                                                                ? 'bg-rose-50 dark:bg-rose-900/20'
                                                                : notif.severity === 'warning'
                                                                  ? 'bg-amber-50 dark:bg-amber-900/10'
                                                                  : notif.read
                                                                    ? 'bg-white dark:bg-slate-800'
                                                                    : 'bg-emerald-50 dark:bg-emerald-900/10'
                                                        )}
                                                    >
                                                        <div className="flex-1 min-w-0">
                                                            <p className="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">
                                                                {notif.title}
                                                            </p>
                                                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2">
                                                                {notif.message}
                                                            </p>
                                                            {notif.action_url && (
                                                                <Link
                                                                    href={notif.action_url}
                                                                    className="mt-1 inline-flex text-[11px] font-medium text-emerald-700 hover:underline dark:text-emerald-400"
                                                                    onClick={() => {
                                                                        if (!notif.read) {
                                                                            router.post(
                                                                                route('notifications.read', { notification: notif.id }),
                                                                                {},
                                                                                { preserveScroll: true }
                                                                            );
                                                                        }
                                                                        setNotifOpen(false);
                                                                    }}
                                                                >
                                                                    Vai al dettaglio
                                                                </Link>
                                                            )}
                                                            <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                                                                {notif.created_at}
                                                            </p>
                                                        </div>
                                                        {!notif.read && (
                                                            <button
                                                                onClick={() => router.post(
                                                                    route('notifications.read', { notification: notif.id }),
                                                                    {},
                                                                    { preserveScroll: true }
                                                                )}
                                                                className="shrink-0 text-[10px] text-emerald-600 hover:underline dark:text-emerald-400 mt-0.5"
                                                                title="Segna come letta"
                                                            >
                                                                Letta
                                                            </button>
                                                        )}
                                                    </li>
                                                ))
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </div>

                            {/* Nascondi saldi */}
                            <BalancePrivacyToggle />

                            {/* Theme Toggle */}
                            <ThemeToggle />

                            {/* User Menu */}
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        className="flex items-center gap-1.5 rounded-xl p-1.5 transition-colors hover:bg-slate-100 sm:gap-2 sm:p-2"
                                        aria-haspopup="menu"
                                        aria-expanded={undefined}
                                    >
                                        <span className="sr-only">Menu utente</span>
                                        <div className="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-700 font-semibold text-sm dark:text-emerald-300">
                                            {user.name.charAt(0).toUpperCase()}
                                        </div>
                                        <span className="hidden sm:block text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-700" aria-hidden="true">
                                            {user.name}
                                        </span>
                                        <span className="hidden sm:inline">
                                            <Icons.ChevronDown />
                                        </span>
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content>
                                    <Dropdown.Link href={route('profile.edit')}>
                                        <span className="flex items-center gap-2">
                                            <Icons.User />
                                            Profilo
                                        </span>
                                    </Dropdown.Link>
                                    {isAdmin && (
                                        <a
                                            href={route('admin.magazine.index')}
                                            className="block w-full px-4 py-2.5 text-start text-sm leading-5 text-slate-700 hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:bg-slate-50"
                                        >
                                            <span className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                                                <Icons.Shield />
                                                Admin
                                            </span>
                                        </a>
                                    )}
                                    <form onSubmit={handleLogout} action={route('logout')} method="POST">
                                        <button
                                            type="submit"
                                            className="block w-full px-4 py-2.5 text-start text-sm leading-5 text-slate-700 hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:bg-slate-50"
                                        >
                                            <span className="flex items-center gap-2 text-rose-600">
                                                <Icons.LogOut />
                                                Esci
                                            </span>
                                        </button>
                                    </form>
                                </Dropdown.Content>
                            </Dropdown>
                        </div>
                    </header>

                    {/* Flash Messages */}
                    <FlashMessages />

                    {/* Alert piano: scadenza Pro o dati in eccesso dopo downgrade */}
                    <PlanAlertBanner />

                    {/* Scrollable Content */}
                    <main className="flex-1 overflow-x-hidden overflow-y-auto p-2 pb-[calc(4.5rem+env(safe-area-inset-bottom,0px))] sm:p-4 md:p-6 lg:p-8 lg:pb-8">
                        <div className="mx-auto min-w-0 max-w-7xl">
                            {children}
                        </div>
                    </main>
                </div>
            </div>

            {/* Bottom Navigation — mobile only */}
            <MobileBottomNav isRouteActive={isRouteActive} onMenuOpen={() => setSidebarOpen(true)} />
            <PwaInstallBanner />
        </>
        </OfflineGate>
    );
}

function MobileBottomNav({
    isRouteActive,
    onMenuOpen,
}: {
    isRouteActive: (
        routeMatch: string,
        altRouteMatch?: string,
        excludeRouteMatch?: string,
        routeMatchPatterns?: string[],
    ) => boolean;
    onMenuOpen: () => void;
}) {
    const isDashboard = isRouteActive('dashboard');
    const isCashflow = isRouteActive(
        'transactions.index',
        undefined,
        undefined,
        ['accounts.*', 'transactions.*', 'transfers.*', 'inter-household-transfers.*'],
    );
    const isAccounts = isRouteActive('accounts.*');
    const primaryFab = resolveMobilePrimaryFab();

    return (
        <nav
            className="lg:hidden fixed bottom-0 inset-x-0 z-40 overflow-visible bg-white/95 backdrop-blur-md dark:bg-slate-800/95 border-t border-slate-200 dark:border-slate-700"
            style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
            aria-label="Navigazione rapida"
        >
            <div className="flex h-16 items-center justify-around px-1">
                {/* Dashboard */}
                <Link
                    href={route('dashboard')}
                    onClick={() => nav.bottomBar('home')}
                    className={clsx(
                        'flex min-h-12 min-w-14 flex-col items-center justify-center gap-1 rounded-xl py-1 transition-colors',
                        isDashboard ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-200'
                    )}
                    aria-label="Home"
                >
                    <span aria-hidden="true"><Icons.Dashboard /></span>
                    <span className="text-xs font-medium leading-none" aria-hidden="true">Home</span>
                </Link>

                {/* Transazioni */}
                <Link
                    href={route('transactions.index')}
                    onClick={() => nav.bottomBar('movimenti')}
                    className={clsx(
                        'flex min-h-12 min-w-14 flex-col items-center justify-center gap-1 rounded-xl py-1 transition-colors',
                        isCashflow ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-200'
                    )}
                    aria-label="Transazioni"
                >
                    <span aria-hidden="true"><Icons.ArrowLeftRight /></span>
                    <span className="text-xs font-medium leading-none" aria-hidden="true">Transazioni</span>
                </Link>

                {/* FAB — nascosto dove non c'è un'azione primaria sensata (es. simulazioni) */}
                {primaryFab ? (
                    primaryFab.mode === 'submit' ? (
                        <button
                            type="submit"
                            form={primaryFab.formId ?? FM_MOBILE_PRIMARY_FORM_ID}
                            onClick={() => nav.mobileFab(primaryFab.analyticsSection)}
                            className="flex h-12 w-12 shrink-0 -mt-3 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/40 active:scale-95 transition-transform"
                            aria-label={primaryFab.ariaLabel}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                <polyline points="17 21 17 13 7 13 7 21" />
                                <polyline points="7 3 7 8 15 8" />
                            </svg>
                        </button>
                    ) : (
                        <Link
                            href={primaryFab.href}
                            onClick={() => nav.mobileFab(primaryFab.analyticsSection)}
                            className="flex h-12 w-12 shrink-0 -mt-3 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/40 active:scale-95 transition-transform"
                            aria-label={primaryFab.ariaLabel}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M5 12h14" /><path d="M12 5v14" />
                            </svg>
                        </Link>
                    )
                ) : (
                    <div
                        className="flex h-12 w-12 shrink-0 -mt-3 items-center justify-center"
                        aria-hidden="true"
                    />
                )}

                {/* Conti */}
                <Link
                    href={route('accounts.index')}
                    onClick={() => nav.bottomBar('conti')}
                    className={clsx(
                        'flex min-h-12 min-w-14 flex-col items-center justify-center gap-1 rounded-xl py-1 transition-colors',
                        isAccounts ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-200'
                    )}
                    aria-label="Conti"
                >
                    <span aria-hidden="true"><Icons.Wallet /></span>
                    <span className="text-xs font-medium leading-none" aria-hidden="true">Conti</span>
                </Link>

                {/* Altro / Menu */}
                <button
                    onClick={onMenuOpen}
                    className="flex min-h-12 min-w-14 flex-col items-center justify-center gap-1 rounded-xl py-1 text-slate-700 transition-colors dark:text-slate-200"
                    aria-label="Altro"
                >
                    <span aria-hidden="true"><Icons.Menu /></span>
                    <span className="text-xs font-medium leading-none" aria-hidden="true">Altro</span>
                </button>
            </div>
        </nav>
    );
}
