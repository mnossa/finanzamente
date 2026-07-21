import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'transactions' | 'accounts';

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
        id: 'accounts',
        label: 'Conti',
        icon: 'Wallet',
        routeName: 'accounts.index',
        moduleId: 'accounts',
    },
];

export default function CashflowHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Hub conti e movimenti" />;
}
