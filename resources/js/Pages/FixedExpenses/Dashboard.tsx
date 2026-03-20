import React, { useState, useEffect } from 'react';
import { Head, Link, usePage, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import {
    ChartBarIcon,
    UserGroupIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    ArrowPathIcon
} from '@heroicons/react/24/outline';
import clsx from 'clsx';
import PageHeader from '@/Components/PageHeader';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Household {
    id: number;
    name: string;
    users: User[];
    is_owner: boolean;
    enable_turn_suggestions: boolean;
}

interface CategoryContribution {
    category_id: number;
    category_name: string;
    user_contributed: number;
    category_total: number;
    contribution_percentage: number;
    expected_percentage: number;
    expected_contribution: number;
    category_balance: number;
}

interface UserContribution {
    user_id: number;
    user_name: string;
    user_email: string;
    total_contributed: number;
    expected_contribution: number;
    balance: number;
    categories: Record<number, CategoryContribution>;
}

interface DashboardStats {
    total_fixed_expenses: number;
    categories_count: number;
    members_count: number;
    balanced_members: number;
    members_summary: {
        name: string;
        balance: number;
        is_balanced: boolean;
        status: 'creditor' | 'debtor' | 'balanced';
    }[];
}

interface DashboardData {
    error?: string;
    message?: string;
    total_household_expenses: number;
    fixed_categories_count: number;
    contributions: Record<number, UserContribution>;
    stats: DashboardStats;
}

interface FixedCategory {
    id: number;
    name: string;
    color?: string;
    icon?: string;
}

interface Props {
    household: Household;
    dashboardData: DashboardData;
    fixedCategories: FixedCategory[];
    turnSuggestionsEnabled: boolean;
}

export default function Dashboard({
    household,
    dashboardData,
    fixedCategories,
    turnSuggestionsEnabled
}: Props) {
    const [refreshing, setRefreshing] = useState(false);

    // Form per gestire il suggeritore di turni
    const turnForm = useForm({
        enable_turn_suggestions: turnSuggestionsEnabled,
        turn_suggestion_settings: {}
    });

    const updateTurnSettings = () => {
        turnForm.patch(route('fixed-expenses.update-turn-settings', household.id), {
            preserveScroll: true,
            onSuccess: () => {
                // Ricarica la pagina per aggiornare lo stato
                window.location.reload();
            }
        });
    };

    const refreshData = () => {
        setRefreshing(true);
        // Ricarica la pagina per aggiornare i dati
        window.location.reload();
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'creditor':
                return 'text-green-600 bg-green-50 border-green-200 dark:text-green-400 dark:bg-green-900/20 dark:border-green-800';
            case 'debtor':
                return 'text-red-600 bg-red-50 border-red-200 dark:text-red-400 dark:bg-red-900/20 dark:border-red-800';
            case 'balanced':
                return 'text-blue-600 bg-blue-50 border-blue-200 dark:text-blue-400 dark:bg-blue-900/20 dark:border-blue-800';
            default:
                return 'text-gray-600 bg-gray-50 border-gray-200 dark:text-gray-400 dark:bg-gray-900/20 dark:border-gray-800';
        }
    };

    const getStatusLabel = (status: string) => {
        switch (status) {
            case 'creditor':
                return 'Ha pagato di più';
            case 'debtor':
                return 'Deve contribuire';
            case 'balanced':
                return 'In equilibrio';
            default:
                return 'Sconosciuto';
        }
    };

    if (dashboardData.error) {
        return (
            <AuthenticatedLayout
                header={
                    <PageHeader
                        title={`Contributi Spese Fisse - ${household.name}`}
                        backLink={route('households.show', household.id)}
                    />
                }
            >
                <Head title={`Contributi Spese Fisse - ${household.name}`} />

                <PageContent>
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6 text-center">
                                <ExclamationTriangleIcon className="mx-auto h-12 w-12 text-red-500 mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                                    Errore
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">{dashboardData.error}</p>
                                <Link
                                    href={route('households.show', household.id)}
                                    className="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900"
                                >
                                    Torna alla Household
                                </Link>
                            </div>
                        </div>
                </PageContent>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Contributi Spese Fisse`}
                    actions={<button
                        onClick={refreshData}
                        disabled={refreshing}
                        className={clsx(
                            "inline-flex items-center gap-2 px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition-colors",
                            refreshing
                                ? "opacity-50 cursor-not-allowed"
                                : "hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900"
                        )}
                    >
                        <ArrowPathIcon className={clsx("h-4 w-4", refreshing && "animate-spin")} />
                        {refreshing ? 'Aggiornamento...' : 'Aggiorna'}
                    </button>}
                    subtitle={`${household.name} • Bilanciamento Debiti`}
                />

            }
        >
            <Head title={`Contributi Spese Fisse - ${household.name}`} />

            <PageContent>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <BanknotesIcon className="h-8 w-8 text-green-600" />
                                    </div>
                                    <div className="ml-5">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Totale Spese Fisse
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                            {formatCurrency(dashboardData.stats.total_fixed_expenses)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <ChartBarIcon className="h-8 w-8 text-blue-600" />
                                    </div>
                                    <div className="ml-5">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Categorie Fisse
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                            {dashboardData.stats.categories_count}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <UserGroupIcon className="h-8 w-8 text-purple-600" />
                                    </div>
                                    <div className="ml-5">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Membri Household
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                            {dashboardData.stats.members_count}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6">
                                <div className="flex items-center">
                                    <div className="flex-shrink-0">
                                        <CheckCircleIcon className="h-8 w-8 text-emerald-600" />
                                    </div>
                                    <div className="ml-5">
                                        <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Membri Equilibrati
                                        </p>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                            {dashboardData.stats.balanced_members} / {dashboardData.stats.members_count}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Suggeritore di Turni Status */}
                    {household.is_owner && (
                        <div className={clsx(
                            "bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 dark:bg-gray-800",
                            turnForm.data.enable_turn_suggestions
                                ? "border-green-400"
                                : "border-orange-400"
                        )}>
                            <div className="p-6">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center">
                                        <ClockIcon className={clsx(
                                            "h-8 w-8",
                                            turnForm.data.enable_turn_suggestions ? "text-green-600" : "text-orange-500"
                                        )} />
                                        <div className="ml-4">
                                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                Suggeritore di Turni
                                            </h3>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {turnForm.data.enable_turn_suggestions
                                                    ? "Attivo - Il sistema suggerisce automaticamente i turni per le spese fisse"
                                                    : "Disattivato - Attiva per ricevere suggerimenti sui turni"
                                                }
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <label className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={turnForm.data.enable_turn_suggestions}
                                                onChange={(e) => turnForm.setData('enable_turn_suggestions', e.target.checked)}
                                                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <span className="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                                Abilita
                                            </span>
                                        </label>
                                        <button
                                            onClick={updateTurnSettings}
                                            disabled={turnForm.processing}
                                            className={clsx(
                                                "inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest transition-colors",
                                                turnForm.processing
                                                    ? "bg-gray-400 cursor-not-allowed text-gray-700"
                                                    : "bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 text-white"
                                            )}
                                        >
                                            {turnForm.processing ? 'Salvando...' : 'Salva'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Contributi per Membro */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                                Situazione Membri
                            </h3>
                            <div className="space-y-4">
                                {dashboardData.stats.members_summary.map((member) => (
                                    <div
                                        key={member.name}
                                        className={clsx(
                                            "p-4 rounded-lg border",
                                            getStatusColor(member.status)
                                        )}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <h4 className="font-medium">{member.name}</h4>
                                                <p className="text-sm opacity-75">
                                                    {getStatusLabel(member.status)}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-lg font-bold">
                                                    {member.balance >= 0 ? '+' : ''}{formatCurrency(member.balance)}
                                                </p>
                                                <p className="text-xs opacity-75">
                                                    Bilancio
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Dettagli Contributi per Categoria */}
                    {Object.keys(dashboardData.contributions).length > 0 && (
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <div className="p-6">
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                                    Dettaglio Contributi per Categoria
                                </h3>
                                {Object.values(dashboardData.contributions).map((userContrib) => (
                                    <div key={userContrib.user_id} className="mb-8 last:mb-0">
                                        <div className="flex items-center justify-between mb-4">
                                            <h4 className="text-md font-semibold text-gray-900 dark:text-gray-100">
                                                {userContrib.user_name}
                                            </h4>
                                            <div className="text-right">
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    Totale: {formatCurrency(userContrib.total_contributed)}
                                                </p>
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    Previsto: {formatCurrency(userContrib.expected_contribution)}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="space-y-3">
                                            {Object.values(userContrib.categories).map((catContrib) => (
                                                <div
                                                    key={catContrib.category_id}
                                                    className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
                                                >
                                                    <div className="flex items-center justify-between mb-2">
                                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                                            {catContrib.category_name}
                                                        </span>
                                                        <span className={clsx(
                                                            "text-sm font-medium",
                                                            catContrib.category_balance >= 0
                                                                ? "text-green-600 dark:text-green-400"
                                                                : "text-red-600 dark:text-red-400"
                                                        )}>
                                                            {catContrib.category_balance >= 0 ? '+' : ''}{formatCurrency(catContrib.category_balance)}
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                                                        <span>
                                                            Contribuito: {formatCurrency(catContrib.user_contributed)}
                                                            ({catContrib.contribution_percentage.toFixed(1)}%)
                                                        </span>
                                                        <span>
                                                            Previsto: {formatCurrency(catContrib.expected_contribution)}
                                                            ({catContrib.expected_percentage}%)
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Messaggio se nessuna categoria */}
                    {dashboardData.message && (
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6 dark:bg-yellow-900/20 dark:border-yellow-800">
                            <div className="flex">
                                <ExclamationTriangleIcon className="h-5 w-5 text-yellow-400 mt-1" />
                                <div className="ml-3">
                                    <h3 className="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Nessuna spesa fissa configurata
                                    </h3>
                                    <p className="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                        {dashboardData.message} Per iniziare a tracciare i contributi, marca alcune categorie come "spese fisse" nella gestione delle categorie.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Link per tornare alla household */}
                    <div className="text-center">
                        <Link
                            href={route('households.show', household.id)}
                            className="inline-flex items-center text-blue-600 hover:text-blue-500 dark:text-blue-400"
                        >
                            ← Torna alla gestione household
                        </Link>
                    </div>

            </PageContent>
        </AuthenticatedLayout>
    );
}