import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'positions' | 'pacs' | 'allocation' | 'assets' | 'analyses';

const TABS: SectionHubTab[] = [
    {
        id: 'positions',
        label: 'Posizioni',
        icon: 'TrendingUp',
        routeName: 'investments.index',
        moduleId: 'investments',
    },
    {
        id: 'pacs',
        label: 'PAC',
        icon: 'Repeat',
        routeName: 'investment-pacs.index',
        moduleId: 'investments',
    },
    {
        id: 'allocation',
        label: 'Allocazione',
        icon: 'PieChart',
        routeName: 'asset-allocation.index',
        moduleId: 'asset_allocation',
    },
    {
        id: 'assets',
        label: 'Asset',
        icon: 'BarChart',
        routeName: 'investment-assets.index',
        moduleId: 'investments',
    },
    {
        id: 'analyses',
        label: 'Analisi',
        icon: 'BarChart',
        routeName: 'investment-analyses.index',
        moduleId: 'investments',
    },
];

export default function InvestmentHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Sezione investimenti" />;
}
