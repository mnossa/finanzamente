interface InlineSuccessBadgeProps {
    label?: string;
}

export default function InlineSuccessBadge({ label = 'Salvato' }: InlineSuccessBadgeProps) {
    return (
        <p className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
            {label}
        </p>
    );
}
