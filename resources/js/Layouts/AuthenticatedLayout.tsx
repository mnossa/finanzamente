import clsx from 'clsx';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { ActiveHousehold, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState, useEffect } from 'react';
import { useModules } from '@/hooks/useModules';

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
    Home: () => (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" />
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
};

// Tipo per gli elementi di navigazione
interface NavigationItem {
    name: string;
    href: string;
    routeMatch: string;
    icon: () => JSX.Element;
    altRouteMatch?: string;
    hrefParams?: any;
    moduleId?: string; // ID del modulo associato per controllo accesso
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
        title: 'Dashboard',
        defaultExpanded: true,
        items: [
            { name: 'Dashboard', href: 'dashboard', routeMatch: 'dashboard', icon: Icons.Dashboard, moduleId: 'dashboard' },
        ]
    },
    {
        title: 'Gestione Base',
        defaultExpanded: true,
        items: [
            { name: 'Conti', href: 'accounts.index', routeMatch: 'accounts.*', icon: Icons.Wallet, moduleId: 'accounts' },
            { name: 'Transazioni', href: 'transactions.index', routeMatch: 'transactions.*', icon: Icons.ArrowLeftRight, moduleId: 'transactions' },
            { name: 'Categorie', href: 'categories.index', routeMatch: 'categories.*', icon: Icons.Tags, moduleId: 'categories' },
            { name: 'Trasferimenti', href: 'transfers.index', routeMatch: 'transfers.*', icon: Icons.Transfer, moduleId: 'transfers' },
        ]
    },
    {
        title: 'Transazioni Speciali',
        defaultExpanded: false,
        items: [
            { name: 'Trasf. Households', href: 'inter-household-transfers.index', routeMatch: 'inter-household-transfers.*', icon: Icons.ArrowLeftRight, moduleId: 'inter_household_transfers' },
            { name: 'Rimborsi', href: 'refunds.index', routeMatch: 'refunds.*', icon: Icons.Undo, moduleId: 'refunds' },
            { name: 'Ricorrenti', href: 'recurring-transactions.index', routeMatch: 'recurring-transactions.*', icon: Icons.Repeat, moduleId: 'recurring_transactions' },
        ]
    },
    {
        title: 'Pianificazione',
        defaultExpanded: false,
        items: [
            { name: 'Budget', href: 'budgets.index', routeMatch: 'budgets.*', icon: Icons.PiggyBank, moduleId: 'budgets' },
            { name: 'Debiti/Crediti', href: 'debts-credits.index', routeMatch: 'debts-credits.*', icon: Icons.HandCoins, moduleId: 'debts_credits' },
            { name: 'Obiettivi', href: 'financial-goals.index', routeMatch: 'financial-goals.*', icon: Icons.Target, moduleId: 'financial_goals' },
        ]
    },
    {
        title: 'Fiscale',
        defaultExpanded: false,
        items: [
            { name: 'Rimborso 730', href: 'tax-deductions.index', routeMatch: 'tax-deductions.*', icon: Icons.Briefcase, moduleId: 'tax_refund_730' },
            // { name: 'Gestione IVA', href: 'vat-management.index', routeMatch: 'vat-management.*', icon: Icons.Briefcase, moduleId: 'vat_management' }, // Implementazione futura
        ]
    },
    {
        title: 'Investimenti',
        defaultExpanded: false,
        items: [
            { name: 'Investimenti', href: 'investments.index', routeMatch: 'investments.*', icon: Icons.TrendingUp, moduleId: 'investments' },
            { name: 'Gestisci Asset', href: 'investment-assets.index', routeMatch: 'investment-assets.*', icon: Icons.Briefcase, moduleId: 'investment_assets' },
        ]
    }
];

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
    onClick 
}: { 
    item: NavigationItem; 
    isActive: boolean;
    onClick?: () => void;
}) {
    const Icon = item.icon;
    
    return (
        <Link
            href={item.hrefParams ? route(item.href, item.hrefParams) : route(item.href)}
            onClick={onClick}
            className={clsx(
                'flex items-center w-full px-4 py-3 text-sm font-medium rounded-xl',
                'transition-all duration-200 group',
                isActive
                    ? 'bg-emerald-500/10 text-emerald-400'
                    : 'text-slate-400 hover:bg-slate-800 hover:text-white'
            )}
        >
            <span className={clsx(
                isActive ? 'text-emerald-400' : 'text-slate-500 group-hover:text-white',
                'transition-colors duration-200'
            )}>
                <Icon />
            </span>
            <span className="ml-3">{item.name}</span>
            {isActive && (
                <div className="ml-auto indicator-dot" />
            )}
        </Link>
    );
}

// Componente per sezioni espandibili
function CollapsibleNavSection({
    section,
    isRouteActive,
    onClick
}: {
    section: NavigationSection;
    isRouteActive: (routeMatch: string, altRouteMatch?: string) => boolean;
    onClick?: () => void;
}) {
    const [isExpanded, setIsExpanded] = useState(section.defaultExpanded ?? false);
    
    // Controlla se qualche elemento della sezione è attivo
    const hasActiveItem = section.items.some(item => 
        isRouteActive(item.routeMatch, item.altRouteMatch)
    );

    // Espande automaticamente se c'è un elemento attivo nella sezione
    useEffect(() => {
        if (hasActiveItem && !isExpanded) {
            setIsExpanded(true);
        }
    }, [hasActiveItem, isExpanded]);

    return (
        <div className="mb-2">
            {/* Header della sezione */}
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="flex items-center justify-between w-full px-4 py-2 text-xs font-semibold text-slate-400 hover:text-slate-300 transition-colors uppercase tracking-wider"
            >
                <span>{section.title}</span>
                <span className={clsx(
                    'transition-transform duration-200',
                    isExpanded ? 'rotate-90' : ''
                )}>
                    <Icons.ChevronRight />
                </span>
            </button>

            {/* Elementi della sezione */}
            <div className={clsx(
                'overflow-hidden transition-all duration-300 ease-in-out space-y-1',
                isExpanded ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'
            )}>
                {section.items.map((item) => (
                    <SidebarNavItem
                        key={item.name}
                        item={item}
                        isActive={isRouteActive(item.routeMatch, item.altRouteMatch)}
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
    const { auth, activeHousehold } = usePage<PageProps>().props;
    const user = auth.user;
    const { isModuleEnabled } = useModules();

    const [sidebarOpen, setSidebarOpen] = useState(false);

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

    const isRouteActive = (routeMatch: string, altRouteMatch?: string): boolean => {
        return !!(route().current(routeMatch) || (altRouteMatch && route().current(altRouteMatch)));
    };

    // Filtra le sezioni in base ai moduli disponibili
    const filterSectionsByModules = (sections: NavigationSection[]): NavigationSection[] => {
        return sections
            .map(section => ({
                ...section,
                items: section.items.filter(item => 
                    !item.moduleId || isModuleEnabled(item.moduleId)
                ),
            }))
            .filter(section => section.items.length > 0); // Rimuovi sezioni vuote
    };

    // Crea le sezioni dinamicamente includendo Household se presente
    const baseNavigationSections = filterSectionsByModules(navigationSections);
    
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

    return (
        <div className="flex h-screen bg-slate-50 font-sans text-slate-800 overflow-hidden">
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
                    'transition-transform duration-300 ease-in-out',
                    'lg:translate-x-0 lg:static lg:block',
                    'shadow-sidebar lg:shadow-none',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                )}
            >
                {/* Sidebar Header */}
                <div className="flex items-center justify-between h-20 px-6 border-b border-slate-700 bg-slate-950">
                    <Link href="/" className="flex items-center gap-3 font-bold text-xl tracking-tight">
                        <ApplicationLogo className="w-8 h-8" />
                        <span className="text-white">Finanzamente</span>
                    </Link>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="lg:hidden text-slate-400 hover:text-white transition-colors p-1"
                    >
                        <Icons.X />
                    </button>
                </div>

                {/* Navigation */}
                <nav className="mt-1 p-4 pb-8 space-y-1 overflow-y-auto h-[calc(100vh-162px)]">
                    {allNavigationSections.map((section) => (
                        <CollapsibleNavSection
                            key={section.title}
                            section={section}
                            isRouteActive={isRouteActive}
                            onClick={() => setSidebarOpen(false)}
                        />
                    ))}
                </nav>

                {/* User Profile Bottom */}
                <div className="absolute bottom-0 w-full p-4 bg-slate-950 border-t border-slate-700">
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
                <header className="app-header">
                    <div className="flex items-center gap-4">
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="lg:hidden p-2 -ml-2 text-slate-600 hover:bg-slate-100 rounded-xl transition-colors"
                        >
                            <Icons.Menu />
                        </button>
                        
                        {header && (
                            <div className="hidden sm:block">
                                {header}
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Search - Desktop only */}
                        <div className="relative hidden md:block">
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
                        </div>

                        {/* Notifications */}
                        <button className="relative p-2 text-slate-500 hover:bg-slate-100 rounded-xl transition-colors">
                            <Icons.Bell />
                            <span className="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full border-2 border-white" />
                        </button>

                        {/* User Menu */}
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button className="flex items-center gap-2 p-2 hover:bg-slate-100 rounded-xl transition-colors">
                                    <div className="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-600 font-semibold text-sm">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <span className="hidden sm:block text-sm font-medium text-slate-700">
                                        {user.name}
                                    </span>
                                    <Icons.ChevronDown />
                                </button>
                            </Dropdown.Trigger>

                            <Dropdown.Content>
                                <Dropdown.Link href={route('profile.edit')}>
                                    <span className="flex items-center gap-2">
                                        <Icons.User />
                                        Profilo
                                    </span>
                                </Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    <span className="flex items-center gap-2 text-rose-600">
                                        <Icons.LogOut />
                                        Esci
                                    </span>
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                {/* Mobile Header with title */}
                {header && (
                    <div className="sm:hidden px-4 py-3 bg-white border-b border-slate-200">
                        <div className="text-lg font-bold text-slate-800">{header}</div>
                    </div>
                )}

                {/* Flash Messages */}
                <FlashMessages />

                {/* Scrollable Content */}
                <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
                    <div className="max-w-7xl mx-auto">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
