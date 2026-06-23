import { usePage } from '@inertiajs/react';
import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'transactions' | 'accounts' | 'transfers' | 'quick-session' | 'inter-household';

const BASE_TABS: SectionHubTab[] = [
    {
        id: 'transactions',
        label: 'Transazioni',
        mobileLabel: 'Movimenti',
        icon: 'ArrowLeftRight',
        routeName: 'transactions.index',
        moduleId: 'transactions',
    },
    {
        id: 'accounts',
        label: 'Conti',
        icon: 'Wallet',
        routeName: 'accounts.index',
        moduleId: 'accounts',
    },
    {
        id: 'transfers',
        label: 'Trasferimenti',
        icon: 'Transfer',
        routeName: 'transfers.index',
        moduleId: 'transfers',
    },
    {
        id: 'quick-session',
        label: 'Sessione rapida',
        icon: 'Zap',
        routeName: 'transactions.quick-session',
    },
    {
        id: 'inter-household',
        label: 'Tra nuclei',
        icon: 'Home',
        routeName: 'inter-household-transfers.index',
        requiresPro: true,
        moduleId: 'inter_household_transfers',
    },
];

export default function CashflowHubNav({ active }: { active: HubTab }) {
    const features = (usePage().props as { features?: Record<string, boolean> }).features ?? {};
    const tabs = BASE_TABS.map((tab) =>
        tab.id === 'quick-session'
            ? { ...tab, hidden: features.quick_session_enabled === false }
            : tab,
    );

    return <SectionHubNav tabs={tabs} active={active} ariaLabel="Conti e movimenti" />;
}
