import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { Head, Link, router, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import { FormEventHandler, useState } from 'react';
import CardBox from '@/Components/CardBox';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';

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
    created_at: string;
    updated_at: string;
    user: {
        id: number;
        name: string;
    };
}

interface ShowProps {
    goal: FinancialGoal;
    statuses: Statuses;
}



function ProgressRing({ percentage, color, size = 160 }: { percentage: number; color: string; size?: number }) {
    const strokeWidth = 12;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const progress = (percentage / 100) * circumference;

    return (
        <div className="relative inline-flex items-center justify-center">
            <svg
                width={size}
                height={size}
                className="-rotate-90 transform"
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    stroke="currentColor"
                    strokeWidth={strokeWidth}
                    fill="none"
                    className="text-gray-200 dark:text-gray-700"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    stroke={color}
                    strokeWidth={strokeWidth}
                    fill="none"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference - progress}
                    className="transition-all duration-1000"
                />
            </svg>
            <div className="absolute flex flex-col items-center">
                <span className="text-4xl font-bold" style={{ color }}>
                    {percentage}%
                </span>
            </div>
        </div>
    );
}

import { StatusBadge } from '@/Components/StatusBadge';

function ContributeModal({
    goal,
    onClose,
}: {
    goal: FinancialGoal;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('financial-goals.contribute', goal.id), {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const quickAmounts = [10, 50, 100, 200, 500];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                    💰 Aggiungi Risparmio
                </h3>
                <form onSubmit={submit}>
                    <div className="mb-4">
                        <InputLabel htmlFor="amount" value="Importo *" />
                        <div className="relative mt-2">
                            <TextInput
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                className="w-full pr-12"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                placeholder="0,00"
                                autoFocus
                                required
                            />
                            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                                {goal.currency.symbol}
                            </span>
                        </div>
                        <InputError message={errors.amount} className="mt-2" />
                    </div>

                    <div className="mb-4 flex flex-wrap gap-2">
                        {quickAmounts.map((amount) => (
                            <button
                                key={amount}
                                type="button"
                                onClick={() => setData('amount', amount.toString())}
                                className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                +{amount} {goal.currency.symbol}
                            </button>
                        ))}
                    </div>

                    <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Mancano ancora{' '}
                        <strong>{formatCurrency(goal.remaining_amount, goal.currency.code)}</strong>{' '}
                        per raggiungere l'obiettivo
                    </p>

                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            Annulla
                        </button>
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Salvataggio...' : '💰 Aggiungi'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    );
}

export default function Show({ goal, statuses }: ShowProps) {
    const [showContributeModal, setShowContributeModal] = useState(false);
    const [showStatusDropdown, setShowStatusDropdown] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const handleChangeStatus = (newStatus: string) => {
        router.put(
            route('financial-goals.change-status', goal.id),
            { status: newStatus },
            {
                onSuccess: () => setShowStatusDropdown(false),
            }
        );
    };

    const handleDelete = () => {
        router.delete(route('financial-goals.destroy', goal.id));
    };

    const goalColor = goal.color || '#6366f1';

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Obiettivo: ${goal.name}`}
                    backLink={route('financial-goals.index')}
                />
            }
        >
            <Head title={goal.name} />

            <PageContent maxWidth="4xl">
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Dettaglio obiettivo" icon={<span className="text-sm leading-none">🎯</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Segui progresso, contributi e stato del tuo traguardo finanziario.
                            </p>
                        </div>
                    </SectionCard>
                    {/* Progress Card */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="p-6 sm:p-8">
                            <div className="flex flex-col items-center text-center sm:flex-row sm:text-left">
                                <div className="mb-6 sm:mb-0 sm:mr-8">
                                    <ProgressRing
                                        percentage={goal.progress_percentage}
                                        color={goalColor}
                                    />
                                </div>
                                <div className="flex-1">
                                    <div className="mb-2 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                                        <StatusBadge
                                            status={goal.status}
                                            statusLabel={goal.status_label}
                                            isOverdue={goal.is_overdue}
                                        />
                                    </div>
                                    <h1 className="mb-2 text-2xl font-bold text-gray-900 dark:text-white">
                                        {goal.icon} {goal.name}
                                    </h1>
                                    {goal.description && (
                                        <p className="mb-4 text-gray-600 dark:text-gray-400">
                                            {goal.description}
                                        </p>
                                    )}
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Risparmiato
                                            </p>
                                            <p className="text-xl font-bold text-gray-900 dark:text-white">
                                                {formatCurrency(goal.current_amount, goal.currency.code)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Obiettivo
                                            </p>
                                            <p className="text-xl font-bold" style={{ color: goalColor }}>
                                                {formatCurrency(goal.target_amount, goal.currency.code)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                Mancante
                                            </p>
                                            <p className={clsx(
                                                'text-xl font-bold',
                                                goal.remaining_amount > 0
                                                    ? 'text-gray-900 dark:text-white'
                                                    : 'text-green-500'
                                            )}>
                                                {goal.remaining_amount > 0
                                                    ? formatCurrency(goal.remaining_amount, goal.currency.code)
                                                    : '🎉 Completato!'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Actions */}
                        {goal.status === 'in_progress' && (
                            <div className="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
                                <button
                                    onClick={() => setShowContributeModal(true)}
                                    className="w-full rounded-lg bg-emerald-500 px-4 py-3 font-medium text-white shadow-accent transition-all hover:bg-emerald-600 active:scale-95 sm:w-auto"
                                >
                                    💰 Aggiungi Risparmio
                                </button>
                            </div>
                        )}
                    </CardBox>

                    {/* Details */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Dettagli
                            </h3>
                        </div>
                        <div className="divide-y divide-gray-200 dark:divide-gray-700">
                            <div className="flex items-center justify-between px-6 py-4">
                                <span className="text-gray-600 dark:text-gray-400">
                                    Data Obiettivo
                                </span>
                                <span className={clsx(
                                    'font-medium',
                                    goal.is_overdue ? 'text-red-600' : 'text-gray-900 dark:text-white'
                                )}>
                                    {formatDate(goal.target_date)}
                                    {goal.is_overdue && ' (Scaduto)'}
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-6 py-4">
                                <span className="text-gray-600 dark:text-gray-400">
                                    Valuta
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {goal.currency.code} ({goal.currency.symbol})
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-6 py-4">
                                <span className="text-gray-600 dark:text-gray-400">
                                    Creato da
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {goal.user.name}
                                </span>
                            </div>
                            <div className="flex items-center justify-between px-6 py-4">
                                <span className="text-gray-600 dark:text-gray-400">
                                    Data Creazione
                                </span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {formatDate(goal.created_at)}
                                </span>
                            </div>
                        </div>
                    </CardBox>

                    {/* Actions */}
                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Azioni
                            </h3>
                        </div>
                        <div className="divide-y divide-gray-200 dark:divide-gray-700">
                            {/* Cambia Stato */}
                            <div className="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-white">
                                        Cambia Stato
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Segna l'obiettivo come raggiunto o annullato
                                    </p>
                                </div>
                                <div className="relative">
                                    <button
                                        onClick={() => setShowStatusDropdown(!showStatusDropdown)}
                                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        {goal.status_label} ▾
                                    </button>
                                    {showStatusDropdown && (
                                        <div className="absolute right-0 top-12 z-10 w-48 rounded-lg bg-white py-1 shadow-lg dark:bg-gray-700">
                                            {Object.entries(statuses).map(([value, label]) => (
                                                <button
                                                    key={value}
                                                    onClick={() => handleChangeStatus(value)}
                                                    className={clsx(
                                                        'block w-full px-4 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-600',
                                                        goal.status === value
                                                            ? 'bg-gray-100 font-medium dark:bg-gray-600'
                                                            : ''
                                                    )}
                                                >
                                                    {label}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Modifica */}
                            <Link
                                href={route('financial-goals.edit', goal.id)}
                                className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-white">
                                        Modifica Obiettivo
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Cambia nome, importo o altri dettagli
                                    </p>
                                </div>
                                <span className="text-gray-400">→</span>
                            </Link>

                            {/* Elimina */}
                            <div className="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p className="font-medium text-red-600">
                                        Elimina Obiettivo
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Questa azione non può essere annullata
                                    </p>
                                </div>
                                {showDeleteConfirm ? (
                                    <div className="flex gap-2">
                                        <button
                                            onClick={() => setShowDeleteConfirm(false)}
                                            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                                        >
                                            Annulla
                                        </button>
                                        <button
                                            onClick={handleDelete}
                                            className="rounded-lg bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700"
                                        >
                                            Conferma
                                        </button>
                                    </div>
                                ) : (
                                    <button
                                        onClick={() => setShowDeleteConfirm(true)}
                                        className="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20"
                                    >
                                        <TrashIcon size={18} /> Elimina
                                    </button>
                                )}
                            </div>
                        </div>
                    </CardBox>
            </PageContent>

            {/* Contribute Modal */}
            {showContributeModal && (
                <ContributeModal
                    goal={goal}
                    onClose={() => setShowContributeModal(false)}
                />
            )}
        </AuthenticatedLayout>
    );
}
