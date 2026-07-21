import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

type HubTab = 'patrimonio' | 'positions';

const BASE_TABS: SectionHubTab[] = [
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
        requiresPro: true,
        moduleId: 'investments',
    },
];

export default function PatrimonioHubNav({ active }: { active: HubTab }) {
    const { plan } = usePage<PageProps>().props;
    const isProPlan = plan?.current === 'pro';

    const tabs = useMemo(
        () => BASE_TABS.map((tab) => (
            tab.id === 'positions'
                ? { ...tab, hidden: !isProPlan }
                : tab
        )),
        [isProPlan],
    );

    return <SectionHubNav tabs={tabs} active={active} ariaLabel="Patrimonio" />;
}
