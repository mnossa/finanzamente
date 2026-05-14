import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { ProgressBar } from '@/Components/ProgressBar';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';

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
        <CardBox className="overflow-hidden p-6 shadow-sm transition-shadow hover:shadow-md">
            <Link
                href={route('financial-goals.show', goal.id)}
                className="block"
            >
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div
                        className="flex h-12 w-12 items-center justify-center rounded-full text-2xl"
                        style={{
                            backgroundColor: goal.color ? `${goal.color}20` : '#6366f120',
                        }}
                    >
                        {goal.icon || '🎯'}
                    </div>
                    <div>
                        <h3 className={clsx(
                            'font-semibold text-gray-900 dark:text-white',
                            goal.status === 'cancelled' && 'line-through opacity-50'
                        )}>
                            {goal.name}
                        </h3>
                        <StatusBadge
                            status={goal.status}
                            statusLabel={goal.status_label}
                            isOverdue={goal.is_overdue}
                        />
                    </div>
                </div>
                <div className="text-right">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {formatDate(goal.target_date)}
                    </p>
                </div>
            </div>

            <div className="mt-4">
                <div className="flex items-end justify-between">
                    <div>
                        <p className="text-2xl font-bold text-gray-900 dark:text-white">
                            {formatCurrency(goal.current_amount, goal.currency.code)}
                        </p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            di {formatCurrency(goal.target_amount, goal.currency.code)}
                        </p>
                    </div>
                    <div className="text-right">
                        <p
                            className="text-3xl font-bold"
                            style={{ color: goal.color || '#6366f1' }}
                        >
                            {goal.progress_percentage}%
                        </p>
                    </div>
                </div>
                <div className="mt-3">
                    <ProgressBar percentage={goal.progress_percentage} color={goal.color} />
                </div>
                {goal.remaining_amount > 0 && goal.status === 'in_progress' && (
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Mancano ancora {formatCurrency(goal.remaining_amount, goal.currency.code)}
                    </p>
                )}
            </div>
            </Link>
        </CardBox>
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
                    actions={
                        <LinkButton
                            href={route('financial-goals.create')}
                            icon={<PlusIcon />}
                            className="hidden lg:inline-flex"
                        >
                            Nuovo Obiettivo
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Obiettivi Finanziari" />

            <PageContent maxWidth="7xl">
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Obiettivi finanziari" icon={<span className="text-sm leading-none">🎯</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Pianifica i tuoi traguardi e segui avanzamento, importi e stato obiettivo.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Statistiche */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Obiettivi Attivi
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                                {stats.in_progress}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Obiettivi Raggiunti
                            </p>
                            <p className="mt-1 text-3xl font-bold text-green-500">
                                {stats.reached}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Totale Risparmiato
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {formatCurrency(stats.total_current)}
                            </p>
                        </CardBox>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Progresso Complessivo
                            </p>
                            <p className="mt-1 text-3xl font-bold text-emerald-600">
                                {overallProgress}%
                            </p>
                            <div className="mt-2">
                                <ProgressBar percentage={overallProgress} color="#6366f1" />
                            </div>
                        </CardBox>
                    </div>

                    {/* Obiettivi in Corso */}
                    {inProgressGoals.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-900 dark:text-white">
                                🎯 In Corso ({inProgressGoals.length})
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {inProgressGoals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Obiettivi Raggiunti */}
                    {reachedGoals.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-500 dark:text-gray-400">
                                ✅ Raggiunti ({reachedGoals.length})
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {reachedGoals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Obiettivi Annullati */}
                    {cancelledGoals.length > 0 && (
                        <div>
                            <h3 className="mb-4 font-medium text-gray-400 dark:text-gray-500">
                                ❌ Annullati ({cancelledGoals.length})
                            </h3>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {cancelledGoals.map((goal) => (
                                    <GoalCard key={goal.id} goal={goal} />
                                ))}
                            </div>
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
