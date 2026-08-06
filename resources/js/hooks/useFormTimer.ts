import { useEffect, useRef } from 'react';

/**
 * Hook che misura il tempo trascorso dal mount del form.
 *
 * Uso tipico:
 * ```tsx
 * const { getElapsedSeconds } = useFormTimer();
 *
 * // Nel submit handler:
 * post(route('...'), { onSuccess: () => console.log(getElapsedSeconds()) });
 * ```
 */
export function useFormTimer() {
    const startRef = useRef<number>(Date.now());

    useEffect(() => {
        startRef.current = Date.now();
    }, []);

    const getElapsedSeconds = (): number =>
        Math.round((Date.now() - startRef.current) / 1000);

    return { getElapsedSeconds };
}
