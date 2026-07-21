import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'transactions' | 'transfers' | 'refunds' | 'recurring' | 'inbox';

const TABS: SectionHubTab[] = [
    {
        id: 'transactions',
        label: 'Transazioni',
        mobileLabel: 'Movimenti',
        icon: 'ArrowLeftRight',
        routeName: 'transactions.index',
        moduleId: 'transactions',
    },
    {
        id: 'transfers',
        label: 'Trasferimenti',
        mobileLabel: 'Trasferim.',
        icon: 'Transfer',
        routeName: 'transfers.index',
        moduleId: 'transfers',
    },
    {
        id: 'refunds',
        label: 'Rimborsi',
        icon: 'Undo',
        routeName: 'refunds.index',
        moduleId: 'refunds',
    },
    {
        id: 'recurring',
        label: 'Ricorrenti',
        mobileLabel: 'Ricorr.',
        icon: 'Repeat',
        routeName: 'recurring-transactions.index',
        moduleId: 'recurring_transactions',
    },
    {
        id: 'inbox',
        label: 'Inbox',
        icon: 'Inbox',
        routeName: 'inbox.index',
        requiresPro: true,
        moduleId: 'inbox',
    },
];

export default function MovementsHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Movimenti" />;
}
