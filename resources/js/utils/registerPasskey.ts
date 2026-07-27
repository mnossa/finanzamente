import { startRegistration, browserSupportsWebAuthn } from '@simplewebauthn/browser';

type RegisterOptionsJson = Parameters<typeof startRegistration>[0]['optionsJSON'];

function csrfHeader(): Record<string, string> {
    if (typeof document === 'undefined') {
        return {};
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    const metaToken = meta?.getAttribute('content');
    if (metaToken) {
        return { 'X-CSRF-TOKEN': metaToken };
    }

    const raw = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    if (!raw) {
        return {};
    }

    return { 'X-XSRF-TOKEN': decodeURIComponent(raw) };
}

async function throwIfNotOk(response: Response): Promise<void> {
    if (response.ok) {
        return;
    }

    let message = `Request failed with status ${response.status}`;
    try {
        const body = await response.json();
        if (body && typeof body === 'object' && 'message' in body && typeof body.message === 'string') {
            message = body.message;
        }
    } catch {
        // keep status message
    }

    throw new Error(message);
}

async function fetchRegistrationOptions(compatibility: boolean): Promise<RegisterOptionsJson> {
    const url = compatibility
        ? '/user/passkeys/options?compatibility=1'
        : '/user/passkeys/options';

    const response = await fetch(url, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    await throwIfNotOk(response);
    const payload = (await response.json()) as { options?: RegisterOptionsJson };

    if (!payload.options) {
        throw new Error('Opzioni di registrazione non disponibili.');
    }

    return payload.options;
}

async function storePasskey(name: string, credential: unknown): Promise<void> {
    const response = await fetch('/user/passkeys', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...csrfHeader(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name, credential }),
    });

    await throwIfNotOk(response);
}

export function isCredentialManagerFailure(err: unknown): boolean {
    const name = err instanceof Error ? err.name : '';
    const message = err instanceof Error ? err.message : typeof err === 'string' ? err : '';
    const haystack = `${name} ${message}`.toLowerCase();

    return (
        name === 'NotReadableError' ||
        haystack.includes('notreadableerror') ||
        haystack.includes('credential manager')
    );
}

async function createAndStore(name: string, compatibility: boolean): Promise<void> {
    const options = await fetchRegistrationOptions(compatibility);
    const credential = await startRegistration({ optionsJSON: options });
    await storePasskey(name, credential);
}

/**
 * Register a device passkey. On Android Credential Manager failures, retries once
 * with looser authenticatorSelection (?compatibility=1).
 */
export async function registerDevicePasskey(name: string): Promise<void> {
    if (!browserSupportsWebAuthn()) {
        throw new Error('Questo browser non supporta le chiavi di accesso.');
    }

    try {
        await createAndStore(name, false);
    } catch (err) {
        if (!isCredentialManagerFailure(err)) {
            throw err;
        }

        await createAndStore(name, true);
    }
}

/**
 * From an installed PWA, open the given path in the system browser (Chrome),
 * where WebAuthn/Credential Manager is more reliable than standalone mode.
 */
export function openPasskeySetupInBrowser(path = '/profilo/sicurezza/passkey'): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(path, window.location.origin).toString();
    const opened = window.open(url, '_blank', 'noopener,noreferrer');

    if (!opened) {
        window.location.assign(url);
    }
}
