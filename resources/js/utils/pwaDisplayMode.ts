/**
 * Helpers for PWA display mode and coarse mobile detection.
 * Used to surface biometric login primarily on installed app / phone.
 */

export function isStandaloneDisplayMode(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    const standaloneMedia = window.matchMedia('(display-mode: standalone)').matches;
    const iosStandalone =
        'standalone' in navigator &&
        Boolean((navigator as Navigator & { standalone?: boolean }).standalone);

    return standaloneMedia || iosStandalone;
}

export function isCoarseMobileViewport(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(max-width: 1023px)').matches;
}

/**
 * Prefer biometric CTA on installed PWA or mobile viewport.
 * Desktop keeps password form as primary path.
 */
export function shouldOfferBiometricLoginUi(): boolean {
    return isStandaloneDisplayMode() || isCoarseMobileViewport();
}
