import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

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

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return 'Nessuna scadenza';
    return new Date(dateStr).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function ProgressBar({ percentage, color }: { percentage: number; color: string | null }) {
    const bgColor = color || '#6366f1';
    
    return (
        <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
                className="h-full rounded-full transition-all duration-500"
                style={{
                    width: `${percentage}%`,
                    backgroundColor: bgColor,
                }}
            />
        </div>
    );
}

function StatusBadge({ status, statusLabel, isOverdue }: { status: string; statusLabel: string; isOverdue: boolean }) {
    if (isOverdue) {
        return (
            <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                ⚠️ Scaduto
            </span>
        );
    }

    const classes: Record<string, string> = {
        in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        reached: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        cancelled: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };

    const icons: Record<string, string> = {
        in_progress: '🎯',
        reached: '✅',
        cancelled: '❌',
    };

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                classes[status] || classes.in_progress
            )}
        >
            {icons[status]} {statusLabel}
        </span>
    );
}

function GoalCard({ goal }: { goal: FinancialGoal }) {
    return (
        <Link
            href={route('financial-goals.show', goal.id)}
            className="block overflow-hidden rounded-xl bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800"
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
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold leading-tight text-slate-800">
                        Obiettivi Finanziari
                    </h1>
                    <LinkButton
                        href={route('financial-goals.create')}
                        icon={<PlusIcon />}
                    >
                        Nuovo Obiettivo
                    </LinkButton>
                </div>
            }
        >
            <Head title="Obiettivi Finanziari" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Statistiche */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Obiettivi Attivi
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                                {stats.in_progress}
                            </p>
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Obiettivi Raggiunti
                            </p>
                            <p className="mt-1 text-3xl font-bold text-green-500">
                                {stats.reached}
                            </p>
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Totale Risparmiato
                            </p>
                            <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                {formatCurrency(stats.total_current)}
                            </p>
                        </div>
                        <div className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Progresso Complessivo
                            </p>
                            <p className="mt-1 text-3xl font-bold text-emerald-600">
                                {overallProgress}%
                            </p>
                            <div className="mt-2">
                                <ProgressBar percentage={overallProgress} color="#6366f1" />
                            </div>
                        </div>
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
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <div className="flex flex-col items-center justify-center py-16 text-center">
                                <div className="mb-4 text-6xl">🎯</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessun obiettivo finanziario
                                </h3>
                                <p className="mb-6 max-w-md text-slate-500">
                                    Crea il tuo primo obiettivo di risparmio per iniziare a monitorare
                                    i tuoi progressi verso i traguardi finanziari.
                                </p>
                                <LinkButton
                                    href={route('financial-goals.create')}
                                    icon={<PlusIcon />}
                                >
                                    Crea il Primo Obiettivo
                                </LinkButton>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
