import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'patrimonio' | 'positions';

const TABS: SectionHubTab[] = [
    {
        id: 'patrimonio',
        label: 'Panoramica',
        mobileLabel: 'Patrimonio',
        icon: 'Wallet',
        routeName: 'patrimonio.index',
    },
    {
        id: 'positions',
        label: 'Investimenti',
        icon: 'TrendingUp',
        routeName: 'investments.index',
        moduleId: 'investments',
    },
];

export default function PatrimonioHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Patrimonio" />;
}
