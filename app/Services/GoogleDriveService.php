<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use InvalidArgumentException;

/**
 * Servizio per il download di file da Google Drive tramite access token OAuth2.
 * Il token viene ottenuto lato frontend tramite Google Identity Services (GIS)
 * e inviato al backend insieme al fileId.
 */
class GoogleDriveService
{
    /**
     * MIME type di Google Sheets (deve essere esportato come XLSX).
     */
    private const GOOGLE_SHEETS_MIME = 'application/vnd.google-apps.spreadsheet';

    /**
     * Scarica un file da Google Drive utilizzando l'access token OAuth2.
     * Se il file è un Google Sheets, lo esporta come XLSX.
     *
     * @param  string $accessToken Token di accesso OAuth2 ottenuto lato frontend
     * @param  string $fileId      ID del file su Google Drive
     * @param  string $mimeType    MIME type originale del file
     * @return string Percorso assoluto al file temporaneo scaricato
     *
     * @throws InvalidArgumentException Se il tipo di file non è supportato
     * @throws RuntimeException         Se il download fallisce
     */
    public function downloadFile(string $accessToken, string $fileId, string $mimeType): string
    {
        if (str_starts_with($mimeType, 'application/vnd.google-apps.') && $mimeType !== self::GOOGLE_SHEETS_MIME) {
            throw new InvalidArgumentException(
                'Tipo di file Google non supportato per l\'importazione. Usa Google Sheets, CSV o XLSX.'
            );
        }

        if ($mimeType === self::GOOGLE_SHEETS_MIME) {
            // Esporta Google Sheets come XLSX
            $exportMime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $url        = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=" . urlencode($exportMime);
            $extension  = 'xlsx';
        } else {
            // Scarica il file direttamente
            $url       = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
            $extension = $this->extensionFromMimeType($mimeType);
        }

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get($url);

        if ($response->status() === 401) {
            throw new RuntimeException('Accesso a Google Drive non autorizzato. Rieffettua il login con Google.');
        }

        if ($response->status() === 403) {
            throw new RuntimeException('Permesso negato per questo file. Verifica di avere accesso al file su Google Drive.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('File non trovato su Google Drive. Verifica che il file esista e sia accessibile.');
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'Impossibile scaricare il file da Google Drive (errore ' . $response->status() . ').'
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'gdrive_') . '.' . $extension;
        file_put_contents($tempPath, $response->body());

        return $tempPath;
    }

    /**
     * Determina l'estensione del file in base al MIME type.
     */
    private function extensionFromMimeType(string $mimeType): string
    {
        return match (true) {
            in_array($mimeType, ['text/csv', 'text/plain'], true)                                                               => 'csv',
            $mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'                                   => 'xlsx',
            $mimeType === 'application/vnd.ms-excel'                                                                            => 'xlsx',
            default                                                                                                             => 'csv',
        };
    }
}
