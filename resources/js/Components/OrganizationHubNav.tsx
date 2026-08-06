import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'accounts' | 'categories' | 'tags';

const TABS: SectionHubTab[] = [
    { id: 'accounts', label: 'Conti', icon: 'Wallet', routeName: 'accounts.index', moduleId: 'accounts' },
    { id: 'categories', label: 'Categorie', icon: 'Receipt', routeName: 'categories.index', moduleId: 'categories' },
    { id: 'tags', label: 'Etichette', icon: 'Tags', routeName: 'tags.index', moduleId: 'tags' },
];

export default function OrganizationHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Organizzazione" />;
}
