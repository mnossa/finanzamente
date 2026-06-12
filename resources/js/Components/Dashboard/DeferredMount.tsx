import { ReactNode, useEffect, useRef, useState } from 'react';

interface DeferredMountProps {
    children: ReactNode;
    fallback: ReactNode;
    rootMargin?: string;
}

/**
 * Monta i figli solo quando il contenitore entra (o sta per entrare) nel viewport.
 * Riduce TBT/INP evitando Recharts e layout pesanti fuori schermo.
 */
export default function DeferredMount({ children, fallback, rootMargin = '180px' }: DeferredMountProps) {
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

        if (typeof IntersectionObserver === 'undefined') {
            setMounted(true);

            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    setMounted(true);
                    observer.disconnect();
                }
            },
            { rootMargin },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [mounted, rootMargin]);

    return <div ref={containerRef}>{mounted ? children : fallback}</div>;
}
