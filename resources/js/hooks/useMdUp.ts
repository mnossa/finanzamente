import { useEffect, useState } from 'react';

const MD_UP_QUERY = '(min-width: 768px)';

/**
 * True from Tailwind `md` breakpoint up (desktop, tablet, mobile landscape ≥768px).
 */
export default function useMdUp(): boolean {
    const [matches, setMatches] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.matchMedia(MD_UP_QUERY).matches;
    });

    useEffect(() => {
        const media = window.matchMedia(MD_UP_QUERY);
        const onChange = () => setMatches(media.matches);
        onChange();
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, []);

    return matches;
}
