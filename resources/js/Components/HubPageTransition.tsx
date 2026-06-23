import { consumeHubNavDirection, isHubIndexRoute, prefersReducedHubMotion } from '@/utils/sectionHubNav';
import clsx from 'clsx';
import { useEffect, useState, type ReactNode } from 'react';

interface HubPageTransitionProps {
    children: ReactNode;
}

export default function HubPageTransition({ children }: HubPageTransitionProps) {
    const currentRoute = typeof route === 'function' ? route().current() : null;
    const isHubPage = isHubIndexRoute(typeof currentRoute === 'string' ? currentRoute : null);
    const [isEntering, setIsEntering] = useState(false);

    useEffect(() => {
        if (!isHubPage || prefersReducedHubMotion()) {
            setIsEntering(false);

            return;
        }

        const direction = consumeHubNavDirection();

        if (!direction) {
            setIsEntering(false);

            return;
        }

        setIsEntering(true);
        const timer = window.setTimeout(() => setIsEntering(false), 200);

        return () => window.clearTimeout(timer);
    }, [currentRoute, isHubPage]);

    if (!isHubPage) {
        return <>{children}</>;
    }

    return (
        <div className={clsx('hub-page-transition', isEntering && 'hub-page-enter')}>
            {children}
        </div>
    );
}
