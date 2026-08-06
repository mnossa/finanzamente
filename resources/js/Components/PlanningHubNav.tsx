import SectionHubNav, { type SectionHubTab } from '@/Components/SectionHubNav';

type HubTab = 'budgets' | 'debts' | 'goals' | 'tax-deductions';

const TABS: SectionHubTab[] = [
    { id: 'budgets', label: 'Budget', icon: 'PiggyBank', routeName: 'budgets.index', moduleId: 'budgets' },
    {
        id: 'debts',
        label: 'Debiti/Crediti',
        mobileLabel: 'Debiti',
        icon: 'HandCoins',
        routeName: 'debts-credits.index',
        moduleId: 'debts_credits',
    },
    { id: 'goals', label: 'Obiettivi', icon: 'Target', routeName: 'financial-goals.index', moduleId: 'financial_goals' },
    {
        id: 'tax-deductions',
        label: 'Spese detraibili',
        mobileLabel: 'Detraibili',
        icon: 'Receipt',
        routeName: 'tax-deductions.index',
        moduleId: 'tax_refund_730',
    },
];

export default function PlanningHubNav({ active }: { active: HubTab }) {
    return <SectionHubNav tabs={TABS} active={active} ariaLabel="Pianificazione e risparmio" />;
}
