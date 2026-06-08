import IndexListRow from '@/Components/Index/IndexListRow';
import RecurringFrequencyBadge from '@/Components/RecurringTransactions/RecurringFrequencyBadge';
import { IndexRowActionButton, IndexRowActionLink, IndexRowActions } from '@/Components/Index/IndexRowActions';
import { formatRecurrenceScheduleRule } from '@/Components/RecurrenceScheduleFields';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { formatCurrency, formatDate } from '@/utils/format';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    type: 'income' | 'expense';
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

export interface RecurringListRowTransaction {
    id: number;
    amount: number;
    frequency: string;
    frequency_label: string;
    day_of_month_mode: 'start_date' | 'fixed' | 'last_day';
    day_of_month: number | null;
    non_working_day_policy: 'postpone' | 'anticipate' | 'keep';
    start_date: string;
    end_date: string | null;
    description: string | null;
    next_due_date: string | null;
    is_active: boolean;
    category: Category | null;
    account: Account;
}

interface RecurringListRowProps {
    rt: RecurringListRowTransaction;
    onDeleteClick: (id: number, description: string) => void;
}

export default function RecurringListRow({ rt, onDeleteClick }: RecurringListRowProps): ReactNode {
    const isIncome = rt.amount > 0;
    const label = rt.description || rt.category?.name || 'Ricorrenza';
    const scheduleRule = formatRecurrenceScheduleRule(
        rt.frequency,
        rt.day_of_month_mode,
        rt.day_of_month,
        rt.non_working_day_policy,
    );

    const title = (
        <span className="inline-flex min-w-0 items-center gap-1">
            <span className={clsx('truncate', !rt.is_active && 'line-through opacity-50')}>{label}</span>
            <RecurringFrequencyBadge frequency={rt.frequency} frequencyLabel={rt.frequency_label} />
            {!rt.is_active && (
                <span className="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-500 dark:bg-gray-700">
                    Terminata
                </span>
            )}
        </span>
    );

    const subtitle = (
        <>
            <span className="block truncate">
                {rt.account.name} · {rt.next_due_date ? `Prossima: ${formatDate(rt.next_due_date)}` : `Dal ${formatDate(rt.start_date)}`}
            </span>
            <span className="mt-0.5 hidden truncate sm:block">{scheduleRule}</span>
        </>
    );

    const amount = (
        <span className={clsx(isIncome ? 'text-green-500' : 'text-red-500', !rt.is_active && 'opacity-50')}>
            {isIncome ? '+' : ''}
            {formatCurrency(rt.amount, rt.account.currency_code)}
        </span>
    );

    const avatarStyle = rt.category?.color
        ? { backgroundColor: `${rt.category.color}20` }
        : undefined;

    return (
        <IndexListRow
            href={route('recurring-transactions.show', rt.id)}
            ariaLabel={label}
            avatar={rt.category?.icon || (isIncome ? '💰' : '💸')}
            avatarClassName={clsx(
                !rt.category?.color && (isIncome ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'),
                !rt.is_active && 'opacity-50',
            )}
            avatarStyle={avatarStyle}
            title={title}
            subtitle={subtitle}
            amount={amount}
            className={!rt.is_active ? 'opacity-90' : undefined}
            actions={
                <IndexRowActions>
                    <IndexRowActionLink href={route('recurring-transactions.show', rt.id)} title="Visualizza">
                        <EyeIcon size={16} />
                    </IndexRowActionLink>
                    <IndexRowActionLink
                        href={route('recurring-transactions.edit', rt.id)}
                        title="Modifica"
                        hoverClassName="hover:text-blue-600 dark:hover:text-blue-400"
                    >
                        <PencilIcon size={16} />
                    </IndexRowActionLink>
                    <IndexRowActionButton
                        onClick={() => onDeleteClick(rt.id, rt.description || rt.category?.name || 'questa ricorrenza')}
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </IndexRowActionButton>
                </IndexRowActions>
            }
        />
    );
}
