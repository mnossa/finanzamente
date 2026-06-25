import { usePage } from '@inertiajs/react';
import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'transactions' | 'accounts' | 'transfers' | 'inter-household';

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
        id: 'inter-household',
        label: 'Tra nuclei',
        icon: 'Home',
        routeName: 'inter-household-transfers.index',
        requiresPro: true,
        moduleId: 'inter_household_transfers',
    },
];

export default function CashflowHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={BASE_TABS} active={active} ariaLabel="Conti e movimenti" />;
}
