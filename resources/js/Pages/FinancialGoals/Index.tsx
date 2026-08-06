import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PlanningHubNav from '@/Components/PlanningHubNav';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EmptyState from '@/Components/EmptyState';
import IndexCardGrid from '@/Components/Index/IndexCardGrid';
import IndexEntityCard from '@/Components/Index/IndexEntityCard';
import IndexKpiCell from '@/Components/Index/IndexKpiCell';
import IndexKpiStrip from '@/Components/Index/IndexKpiStrip';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { ProgressBar } from '@/Components/ProgressBar';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import { moneyTabular } from '@/utils/moneyGridClasses';

interface Currency {
    code: string;
    symbol: string;
}

interface Statuses {
    [key: string]: string;
}

interface FinancialGoal {
    id: number;
    name: string;
    description: string | null;
    target_amount: number;
    current_amount: number;
    remaining_amount: number;
    progress_percentage: number;
    currency: Currency;
    target_date: string | null;
    status: string;
    status_label: string;
    is_overdue: boolean;
    icon: string | null;
    color: string | null;
    user: {
        id: number;
        name: string;
    };
}

interface Stats {
    total_goals: number;
    in_progress: number;
    reached: number;
    total_target: number;
    total_current: number;
}

interface IndexProps {
    goals: FinancialGoal[];
    stats: Stats;
    statuses: Statuses;
}



// Removed the local ProgressBar function definition as it is now imported from '@/Components/ProgressBar'

import { StatusBadge } from '@/Components/StatusBadge';

function GoalCard({ goal }: { goal: FinancialGoal }) {
    return (
        <IndexEntityCard
            href={route('financial-goals.show', goal.id)}
            dimmed={goal.status === 'cancelled'}
            icon={<span>{goal.icon || '🎯'}</span>}
            iconClassName="flex h-10 w-10 items-center justify-center rounded-full text-xl sm:h-11 sm:w-11 sm:text-2xl"
            iconStyle={{
                backgroundColor: goal.color ? `${goal.color}20` : '#6366f120',
            }}
            title={
                <span className={clsx(goal.status === 'cancelled' && 'line-through opacity-50')}>
                    {goal.name}
                </span>
            }
            subtitle={
                <StatusBadge
                    status={goal.status}
                    statusLabel={goal.status_label}
                    isOverdue={goal.is_overdue}
                />
            }
            aside={
                goal.target_date ? (
                    <p className="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                        {formatDate(goal.target_date)}
                    </p>
                ) : undefined
            }
            extra={
                <>
                    <div className="flex items-end justify-between gap-2">
                        <div>
                            <p className={clsx('text-lg font-bold text-gray-900 sm:text-xl dark:text-white', moneyTabular)}>
                                {formatCurrency(goal.current_amount, goal.currency.code)}
                            </p>
                            <p className={clsx('text-xs text-gray-500 sm:text-sm dark:text-gray-400', moneyTabular)}>
                                di {formatCurrency(goal.target_amount, goal.currency.code)}
                            </p>
                        </div>
                        <p
                            className={clsx('text-2xl font-bold sm:text-3xl', moneyTabular)}
                            style={{ color: goal.color || '#6366f1' }}
                        >
                            {goal.progress_percentage}%
                        </p>
                    </div>
                    <div className="mt-2.5">
                        <ProgressBar percentage={goal.progress_percentage} color={goal.color} />
                    </div>
                    {goal.remaining_amount > 0 && goal.status === 'in_progress' && (
                        <p className={clsx('mt-2 text-xs text-gray-500 sm:text-sm dark:text-gray-400', moneyTabular)}>
                            Mancano ancora {formatCurrency(goal.remaining_amount, goal.currency.code)}
                        </p>
                    )}
                </>
            }
        />
    );
}

export default function Index({ goals, stats, statuses }: IndexProps) {
    const inProgressGoals = goals.filter((g) => g.status === 'in_progress');
    const reachedGoals = goals.filter((g) => g.status === 'reached');
    const cancelledGoals = goals.filter((g) => g.status === 'cancelled');

    const overallProgress = stats.total_target > 0
        ? Math.round((stats.total_current / stats.total_target) * 100)
        : 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Obiettivi Finanziari"
                    backLink={route('budgets.index')}
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('financial-goals.create')} icon={<PlusIcon />}>
                                Nuovo Obiettivo
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Obiettivi Finanziari" />

            <PageContent maxWidth="7xl">
                    <PlanningHubNav active="goals" />
                    <IndexKpiStrip>
                        <IndexKpiCell label="Obiettivi Attivi" value={stats.in_progress} />
                        <IndexKpiCell
                            label="Obiettivi Raggiunti"
                            value={stats.reached}
                            valueClassName="text-green-500"
                        />
                        <IndexKpiCell
                            label="Totale Risparmiato"
                            value={formatCurrency(stats.total_current)}
                        />
                        <IndexKpiCell
                            label="Progresso Complessivo"
                            value={`${overallProgress}%`}
                            valueClassName="text-emerald-600"
                            detail={
                                <div className="mt-2">
                                    <ProgressBar percentage={overallProgress} color="#6366f1" />
                                </div>
                            }
                        />
                    </IndexKpiStrip>

                    {/* Obiettivi in Corso */}
                    {inProgressGoals.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                🎯 In Corso ({inProgressGoals.length})
                            </h3>
                            <IndexCardGrid>
                                {inProgressGoals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </IndexCardGrid>
                        </div>
                    )}

                    {/* Obiettivi Raggiunti */}
                    {reachedGoals.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-500 dark:text-gray-400">
                                ✅ Raggiunti ({reachedGoals.length})
                            </h3>
                            <IndexCardGrid>
                                {reachedGoals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </IndexCardGrid>
                        </div>
                    )}

                    {/* Obiettivi Annullati */}
                    {cancelledGoals.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-400 dark:text-gray-500">
                                ❌ Annullati ({cancelledGoals.length})
                            </h3>
                            <IndexCardGrid>
                                {cancelledGoals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </IndexCardGrid>
                        </div>
                    )}

                    {/* Empty State */}
                    {goals.length === 0 && (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="🎯"
                                title="Nessun obiettivo finanziario"
                                description="Crea il tuo primo obiettivo di risparmio per iniziare a monitorare i tuoi progressi verso i traguardi finanziari."
                                createUrl={route('financial-goals.create')}
                                createLabel="Crea il Primo Obiettivo"
                            />
                        </CardBox>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
