import { useEffect, useState } from 'react';

const LG_UP_QUERY = '(min-width: 1024px)';

/**
 * True from Tailwind `lg` breakpoint up (desktop sidebar layout).
 */
export default function useLgUp(): boolean {
    const [matches, setMatches] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.matchMedia(LG_UP_QUERY).matches;
    });

    useEffect(() => {
        const media = window.matchMedia(LG_UP_QUERY);
        const onChange = () => setMatches(media.matches);
        onChange();
        media.addEventListener('change', onChange);
        return () => media.removeEventListener('change', onChange);
    }, []);

    return matches;
}
