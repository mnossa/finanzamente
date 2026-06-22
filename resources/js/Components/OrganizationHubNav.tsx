import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'categories' | 'inbox' | 'refunds' | 'recurring';

const TABS: SectionHubTab[] = [
    { id: 'categories', label: 'Categorie', routeName: 'categories.index', moduleId: 'categories' },
    { id: 'inbox', label: 'Inbox', routeName: 'inbox.index', requiresPro: true, moduleId: 'inbox' },
    { id: 'refunds', label: 'Rimborsi', routeName: 'refunds.index', moduleId: 'refunds' },
    { id: 'recurring', label: 'Ricorrenti', routeName: 'recurring-transactions.index', moduleId: 'recurring_transactions' },
];

export default function OrganizationHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Organizzazione" />;
}
