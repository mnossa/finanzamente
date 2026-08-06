import { ReactNode, useEffect, useRef, useState } from 'react';

interface DeferredMountProps {
    children: ReactNode;
    fallback: ReactNode;
    rootMargin?: string;
    /** After viewport intersection, wait for an idle slice before mounting (defers Recharts boot). */
    scheduleIdle?: boolean;
}

function scheduleWhenIdle(callback: () => void): () => void {
    if (typeof window.requestIdleCallback === 'function') {
        const id = window.requestIdleCallback(callback, { timeout: 2000 });

        return () => window.cancelIdleCallback(id);
    }

    const id = window.setTimeout(callback, 1);

    return () => window.clearTimeout(id);
}

/**
 * Monta i figli solo quando il contenitore entra (o sta per entrare) nel viewport.
 * Riduce TBT/INP evitando Recharts e layout pesanti fuori schermo.
 */
export default function DeferredMount({
    children,
    fallback,
    rootMargin = '180px',
    scheduleIdle = false,
}: DeferredMountProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        if (mounted) {
            return;
        }

        const element = containerRef.current;
        if (!element) {
            return;
        }

        let cancelIdle: (() => void) | undefined;

        const reveal = () => {
            if (scheduleIdle) {
                cancelIdle = scheduleWhenIdle(() => setMounted(true));
            } else {
                setMounted(true);
            }
        };

        if (typeof IntersectionObserver === 'undefined') {
            reveal();

            return () => cancelIdle?.();
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    reveal();
                    observer.disconnect();
                }
            },
            { rootMargin },
        );

        observer.observe(element);

        return () => {
            observer.disconnect();
            cancelIdle?.();
        };
    }, [mounted, rootMargin, scheduleIdle]);

    return <div ref={containerRef}>{mounted ? children : fallback}</div>;
}
