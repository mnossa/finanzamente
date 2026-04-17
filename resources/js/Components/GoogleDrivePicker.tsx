import { usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { PageProps } from '@/types';

export interface DriveFile {
    id: string;
    name: string;
    mimeType: string;
    iconUrl?: string;
    url?: string;
}

interface GoogleDrivePickerProps {
    /** Callback chiamata quando l'utente seleziona un file */
    onFileSelected: (file: DriveFile, accessToken: string) => void;
    /** Callback in caso di errore */
    onError?: (message: string) => void;
    /** Disabilita il pulsante */
    disabled?: boolean;
    className?: string;
}

interface PickerData {
    action: string;
    docs?: Array<Record<string, string>>;
}

interface GoogleDocsViewInstance {
    setMimeTypes: (types: string) => GoogleDocsViewInstance;
    setIncludeFolders: (v: boolean) => GoogleDocsViewInstance;
}

interface GooglePickerBuilderInstance {
    addView: (view: GoogleDocsViewInstance) => GooglePickerBuilderInstance;
    setOAuthToken: (token: string) => GooglePickerBuilderInstance;
    setDeveloperKey: (key: string) => GooglePickerBuilderInstance;
    setCallback: (cb: (data: PickerData) => void) => GooglePickerBuilderInstance;
    build: () => { setVisible: (v: boolean) => void };
}

// Tipi minimi per le Google API (evitano dipendenze npm aggiuntive)
declare global {
    interface Window {
        gapi?: {
            load: (lib: string, callback: () => void) => void;
            client?: unknown;
        };
        google?: {
            accounts: {
                oauth2: {
                    initTokenClient: (config: {
                        client_id: string;
                        scope: string;
                        callback: (tokenResponse: { access_token?: string; error?: string }) => void;
                    }) => { requestAccessToken: () => void };
                };
            };
            picker?: {
                PickerBuilder: new () => GooglePickerBuilderInstance;
                Action: { PICKED: string; CANCEL: string };
                ViewId: { SPREADSHEETS: string; DOCS_NOT_FOLDERS: string };
                DocsView: new (viewId?: string) => GoogleDocsViewInstance;
                Response: { DOCUMENTS: string };
                Document: { ID: string; NAME: string; MIME_TYPE: string; ICON_URL: string; URL: string };
            };
        };
    }
}

const DRIVE_SCOPE = 'https://www.googleapis.com/auth/drive.readonly';
const GAPI_SCRIPT_ID = 'gapi-script';
const GIS_SCRIPT_ID  = 'gis-script';

/** Carica uno script esterno una sola volta */
function loadScript(id: string, src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        if (document.getElementById(id)) {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.id    = id;
        script.src   = src;
        script.async = true;
        script.defer = true;
        script.onload  = () => resolve();
        script.onerror = () => reject(new Error(`Impossibile caricare ${src}`));
        document.head.appendChild(script);
    });
}

/**
 * Pulsante per aprire il Google Drive Picker.
 * Richiede che `GOOGLE_DRIVE_CLIENT_ID` e `GOOGLE_DRIVE_API_KEY` siano
 * configurati nelle variabili d'ambiente e passati via Inertia shared props.
 */
export default function GoogleDrivePicker({
    onFileSelected,
    onError,
    disabled = false,
    className,
}: GoogleDrivePickerProps) {
    const { googleDrive } = usePage<PageProps>().props;
    const clientId = googleDrive?.clientId ?? '';
    const apiKey   = googleDrive?.apiKey   ?? '';

    const [loading, setLoading]       = useState(false);
    const [scriptsReady, setScriptsReady] = useState(false);
    const tokenClientRef = useRef<{ requestAccessToken: () => void } | null>(null);
    const accessTokenRef = useRef<string | null>(null);

    const isConfigured = Boolean(clientId);

    useEffect(() => {
        if (!isConfigured) return;

        Promise.all([
            loadScript(GAPI_SCRIPT_ID, 'https://apis.google.com/js/api.js'),
            loadScript(GIS_SCRIPT_ID,  'https://accounts.google.com/gsi/client'),
        ])
            .then(() => {
                window.gapi?.load('picker', () => {
                    setScriptsReady(true);
                });
            })
            .catch((err: unknown) => {
                const message = err instanceof Error ? err.message : 'Impossibile caricare le API di Google.';
                onError?.(message);
            });
    }, [isConfigured]); // eslint-disable-line react-hooks/exhaustive-deps

    const openPicker = useCallback((token: string) => {
        const picker = window.google?.picker;
        if (!picker) {
            onError?.('Google Picker non disponibile. Riprova.');
            return;
        }

        const supportedMimes = [
            'application/vnd.google-apps.spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
        ].join(',');

        const view = new picker.DocsView()
            .setMimeTypes(supportedMimes)
            .setIncludeFolders(false);

        const pickerInstance = new picker.PickerBuilder()
            .addView(view)
            .setOAuthToken(token)
            .setCallback((data: PickerData) => {
                if (data.action === picker.Action.PICKED && data.docs?.length) {
                    const doc = data.docs[0];
                    onFileSelected(
                        {
                            id:       doc[picker.Document.ID]       ?? '',
                            name:     doc[picker.Document.NAME]      ?? '',
                            mimeType: doc[picker.Document.MIME_TYPE] ?? '',
                            iconUrl:  doc[picker.Document.ICON_URL]  ?? '',
                            url:      doc[picker.Document.URL]       ?? '',
                        },
                        token,
                    );
                }
                setLoading(false);
            })
            .build();

        pickerInstance.setVisible(true);
    }, [apiKey, onFileSelected, onError]);

    const handleClick = useCallback(() => {
        if (!isConfigured) {
            onError?.('Google Drive non configurato. Aggiungi GOOGLE_DRIVE_CLIENT_ID e GOOGLE_DRIVE_API_KEY nel file .env.');
            return;
        }
        if (!scriptsReady) {
            onError?.('Le API di Google sono ancora in caricamento. Riprova tra un momento.');
            return;
        }

        setLoading(true);

        // Riusa il token se ancora valido
        if (accessTokenRef.current) {
            openPicker(accessTokenRef.current);
            return;
        }

        // Ottieni un nuovo access token
        if (!tokenClientRef.current) {
            tokenClientRef.current = window.google!.accounts.oauth2.initTokenClient({
                client_id: clientId,
                scope:     DRIVE_SCOPE,
                callback:  (response) => {
                    if (response.error || !response.access_token) {
                        setLoading(false);
                        onError?.('Autenticazione Google Drive non riuscita. Riprova.');
                        return;
                    }
                    accessTokenRef.current = response.access_token;
                    openPicker(response.access_token);
                },
            });
        }

        tokenClientRef.current.requestAccessToken();
    }, [isConfigured, scriptsReady, clientId, openPicker, onError]);

    if (!isConfigured) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={handleClick}
            disabled={disabled || loading}
            className={clsx(
                'inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 text-sm font-medium transition-all',
                'focus:outline-none focus:ring-2 focus:ring-blue-500',
                disabled || loading
                    ? 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed'
                    : 'border-gray-200 bg-white text-gray-700 hover:border-blue-400 hover:bg-blue-50',
                className,
            )}
            aria-label="Seleziona file da Google Drive"
        >
            <svg className="w-4 h-4 flex-shrink-0" viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
                <path d="M43.65 25L29.9 1.2C28.55 2 27.4 3.1 26.6 4.5L1.2 48.5c-.8 1.4-1.2 2.95-1.2 4.5h27.5z" fill="#00ac47"/>
                <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H60.1l5.65 10.85z" fill="#ea4335"/>
                <path d="M43.65 25L57.4 1.2C56.05.4 54.5 0 52.9 0H34.4c-1.6 0-3.15.45-4.5 1.2z" fill="#00832d"/>
                <path d="M60.1 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.5 1.2h50.8c1.6 0 3.15-.4 4.5-1.2z" fill="#2684fc"/>
                <path d="M73.4 26.5l-12.6-21.8C59.2 3.1 58.05 2 56.7 1.2L43 25l17.1 28H87.3c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
            </svg>
            {loading ? 'Apertura…' : 'Google Drive'}
        </button>
    );
}
