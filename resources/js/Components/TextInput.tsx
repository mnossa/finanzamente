import clsx from 'clsx';
import {
    forwardRef,
    InputHTMLAttributes,
    useEffect,
    useImperativeHandle,
    useRef,
} from 'react';

export default forwardRef(function TextInput(
    {
        type = 'text',
        className = '',
        isFocused = false,
        ...props
    }: InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean },
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={clsx(
                'w-full px-4 py-2.5 rounded-xl border border-slate-200',
                'bg-slate-50 text-slate-800',
                'placeholder:text-slate-400',
                'focus:bg-white focus:border-emerald-500',
                'focus:ring-2 focus:ring-emerald-200',
                'transition-all duration-200 outline-none',
                className
            )}
            ref={localRef}
            onWheel={e => {
                if (type === 'number') {
                    (e.target as HTMLInputElement).blur();
                }
                if (props.onWheel) props.onWheel(e);
            }}
        />
    );
});
