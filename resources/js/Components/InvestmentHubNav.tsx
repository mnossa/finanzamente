import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'positions' | 'pacs' | 'allocation' | 'assets' | 'analyses';

const TABS: SectionHubTab[] = [
    { id: 'positions', label: 'Posizioni', routeName: 'investments.index', requiresPro: true, moduleId: 'investments' },
    { id: 'pacs', label: 'PAC', routeName: 'investment-pacs.index', requiresPro: true, moduleId: 'investments' },
    { id: 'allocation', label: 'Allocazione', routeName: 'asset-allocation.index', requiresPro: true, moduleId: 'asset_allocation' },
    { id: 'assets', label: 'Asset', routeName: 'investment-assets.index', requiresPro: true, moduleId: 'investments' },
    { id: 'analyses', label: 'Analisi', routeName: 'investment-analyses.index', requiresPro: true, moduleId: 'investments' },
];

export default function InvestmentHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Sezione investimenti" />;
}
