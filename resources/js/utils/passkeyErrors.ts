import { PasskeyExistsError, UserCancelledError } from '@laravel/passkeys';

/**
 * Map WebAuthn / Credential Manager errors to Italian user-facing copy.
 * Chrome Android often surfaces opaque English NotReadableError messages.
 */
export function passkeyErrorMessage(
    err: unknown,
    fallback: string,
): string | null {
    if (err instanceof UserCancelledError) {
        return 'Operazione annullata.';
    }

    if (err instanceof PasskeyExistsError) {
        return "Su questo dispositivo c'è già una chiave di accesso per il tuo account. Eliminala dalle impostazioni del telefono oppure usala per accedere.";
    }

    const message =
        err instanceof Error ? err.message : typeof err === 'string' ? err : '';
    const name = err instanceof Error ? err.name : '';
    const haystack = `${name} ${message}`.toLowerCase();

    // Non-secure context (HTTP on non-localhost) → WebAuthn unavailable
    if (
        typeof window !== 'undefined' &&
        window.location.protocol === 'http:' &&
        !['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname)
    ) {
        return "Le chiavi di accesso richiedono una connessione sicura (HTTPS). Apri l'app dall'indirizzo ufficiale con HTTPS e riprova.";
    }

    if (
        name === 'NotAllowedError' ||
        name === 'AbortError' ||
        haystack.includes('was cancelled') ||
        haystack.includes('was canceled') ||
        haystack.includes('operation was cancelled') ||
        haystack.includes('user cancelled') ||
        haystack.includes('user canceled')
    ) {
        return 'Operazione annullata.';
    }

    if (
        haystack.includes('credential manager') ||
        haystack.includes('notreadableerror') ||
        name === 'NotReadableError'
    ) {
        return 'Impossibile completare lo sblocco biometrico. Verifica blocco schermo (PIN/impronta/Face ID), un account Google o iCloud, e che il gestore password predefinito supporti le passkey (su Android preferisci Google Password Manager). Se sei nell\'app installata, prova «Apri in browser».';
    }

    if (haystack.includes('not supported') || name === 'NotSupportedError') {
        return 'Questo dispositivo o browser non supporta lo sblocco biometrico. Usa email e password.';
    }

    if (haystack.includes('invalid domain') || haystack.includes("can't be used on")) {
        return "Le chiavi di accesso non sono disponibili su questo indirizzo. Apri l'app dal dominio ufficiale.";
    }

    if (haystack.includes('timed out') || name === 'TimeoutError') {
        return "Tempo scaduto. Riprova e conferma rapidamente con l'impronta o Face ID.";
    }

    if (haystack.includes('securityerror') || name === 'SecurityError') {
        return "Lo sblocco biometrico non è disponibile in questa sessione. Riapri l'app installata (PWA) dal dominio ufficiale con HTTPS e riprova.";
    }

    if (message.trim() !== '') {
        // Prefer Italian fallback over opaque English OS strings.
        if (/^[A-Za-z0-9 ,.'’"()\-:/]+$/.test(message) && /[A-Za-z]{4,}/.test(message)) {
            const looksEnglish =
                /\b(the|an|unknown|error|occurred|while|talking|credential|operation|not allowed|failed|cancelled|canceled)\b/i.test(
                    message,
                );
            if (looksEnglish) {
                return fallback;
            }
        }

        return message;
    }

    return fallback;
}

/**
 * Stable label for a newly registered device passkey (no user prompt).
 */
export function defaultPasskeyDeviceName(): string {
    if (typeof navigator === 'undefined') {
        return 'Questo dispositivo';
    }

    const ua = navigator.userAgent;
    if (/iPhone/i.test(ua)) {
        return 'iPhone';
    }
    if (/iPad/i.test(ua)) {
        return 'iPad';
    }
    if (/Android/i.test(ua)) {
        return 'Android';
    }
    if (/Mac OS X|Macintosh/i.test(ua)) {
        return 'Mac';
    }
    if (/Windows/i.test(ua)) {
        return 'Windows';
    }

    return 'Questo dispositivo';
}

export async function isPlatformAuthenticatorReady(): Promise<boolean> {
    if (typeof window === 'undefined' || !window.PublicKeyCredential) {
        return false;
    }

    const checker = (
        PublicKeyCredential as typeof PublicKeyCredential & {
            isUserVerifyingPlatformAuthenticatorAvailable?: () => Promise<boolean>;
        }
    ).isUserVerifyingPlatformAuthenticatorAvailable;

    if (typeof checker !== 'function') {
        return true;
    }

    try {
        return await checker.call(PublicKeyCredential);
    } catch {
        return true;
    }
}
