import type { ExpenseCategory } from '@/Components/Charts/ExpenseTreemap';
import type { ExpenseDistributionData } from '@/Components/ExpenseDistributionWidget';
import type { LifestyleWidgetData } from '@/Components/LifestyleWidget';

export interface DashboardDeferredWidgetsData {
    lifestyleWidgetData: LifestyleWidgetData;
    assetAllocationData: {
        total_value: number;
        risk_index: number;
        risk_label: string;
        allocation: Array<{
            asset_class: string;
            label: string;
            color: string;
            value: number;
            percentage: number;
        }>;
    };
    expenseCategories: ExpenseCategory[];
    expenseDistributionData: ExpenseDistributionData;
}

export const emptyExpenseDistributionData: ExpenseDistributionData = {
    needs: { amount: 0, percentage: 0, threshold: 50, exceeded: false, categories: [] },
    wants: { amount: 0, percentage: 0, threshold: 30, exceeded: false, categories: [] },
    investments: { amount: 0, percentage: 0, threshold: 20, exceeded: false, categories: [] },
    unclassified: { amount: 0, percentage: 0, categories: [] },
    total_expenses: 0,
    thresholds: { needs: 50, wants: 30, investments: 20 },
    has_custom_thresholds: false,
    current_month: new Date().toISOString().slice(0, 7),
};

export const emptyDeferredWidgetsData: DashboardDeferredWidgetsData = {
    lifestyleWidgetData: {
        unlocked: false,
        months_with_data: 0,
        months_needed: 2,
        lifestyle_score: null,
        net_income: 0,
        effective_expenses: 0,
        is_partita_iva: false,
        top_categories: [],
        trend: {
            last30_score: null,
            prev30_score: null,
            delta: null,
            direction: 'unknown',
        },
    },
    assetAllocationData: {
        total_value: 0,
        risk_index: 0,
        risk_label: '—',
        allocation: [],
    },
    expenseCategories: [],
    expenseDistributionData: emptyExpenseDistributionData,
};
