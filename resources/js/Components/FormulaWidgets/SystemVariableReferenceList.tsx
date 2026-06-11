import type { SystemVariableCategory, SystemVariableMeta } from '@/types/formulaWidget';
import clsx from 'clsx';

interface SystemVariableReferenceListProps {
    variables: SystemVariableMeta[];
    category?: SystemVariableCategory;
    className?: string;
}

export default function SystemVariableReferenceList({
    variables,
    category,
    className,
}: SystemVariableReferenceListProps) {
    const filtered = variables.filter((variable) =>
        category === undefined
            ? true
            : category === 'context'
              ? variable.category === 'context'
              : variable.category !== 'context',
    );

    if (filtered.length === 0) {
        return null;
    }

    return (
        <ul className={clsx('space-y-2', className)}>
            {filtered.map((variable) => (
                <li key={variable.code} className="text-xs text-gray-600 dark:text-gray-400">
                    <p>
                        <span className="font-mono font-medium text-gray-800 dark:text-gray-200">[{variable.code}]</span>
                        {' — '}
                        {variable.label}
                        {variable.requires_period ? ' · richiede periodo' : ''}
                    </p>
                    {variable.example && (
                        <p className="mt-0.5 font-mono text-primary-800 dark:text-primary-200">
                            Es. {variable.example}
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}
