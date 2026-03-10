<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * VisionService
 *
 * Integrazione con Mistral Pixtral (La Plateforme) per l'estrazione OCR
 * di dati da scontrini/immagini.
 *
 * Ottimizzazioni costo/token:
 * - Prompt "sottile" con chiavi brevi (amt, shop, dt)
 * - Ridimensionamento immagini a max 1000px prima dell'invio
 */
class VisionService
{
    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';
    private const MODEL = 'pixtral-12b-2409';
    private const MAX_HEIGHT = 1000;

    /**
     * Estrae dati da un'immagine di scontrino.
     *
     * @param  string  $imagePath  Percorso relativo nel disco 'private'
     * @return array{amt: float|null, shop: string|null, dt: string|null}|null
     */
    public function extractFromReceipt(string $imagePath): ?array
    {
        $apiKey = config('services.mistral.api_key');
        if (! $apiKey) {
            Log::warning('VisionService: MISTRAL_API_KEY non configurata');

            return null;
        }

        $imageContent = Storage::disk('private')->get($imagePath);
        if (! $imageContent) {
            Log::warning('VisionService: immagine non trovata', ['path' => $imagePath]);

            return null;
        }

        // Ridimensiona l'immagine per ridurre i token Vision
        $resizedContent = $this->resizeImage($imageContent);
        $base64 = base64_encode($resizedContent ?? $imageContent);
        $mimeType = $this->detectMimeType($imagePath);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64}",
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $this->buildPrompt(),
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => 150,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('VisionService: risposta non valida da Mistral', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $content = $response->json('choices.0.message.content');
            if (! $content) {
                return null;
            }

            return $this->parseResponse($content);
        } catch (\Throwable $e) {
            Log::error('VisionService: errore durante l\'estrazione', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Prompt optimized to minimize tokens ("thin prompt").
     * Uses short keys: amt, shop, dt.
     * Note: English is intentional here — the AI model (Pixtral) performs
     * better with English instructions regardless of the UI language.
     */
    private function buildPrompt(): string
    {
        return 'Extract from this receipt. Respond ONLY with JSON: {"amt": <total as number or null>, "shop": "<merchant name or null>", "dt": "<date as YYYY-MM-DD or null>"}';
    }

    /**
     * Ridimensiona l'immagine a max MAX_HEIGHT px mantenendo l'aspect ratio.
     * Usa GD (built-in PHP) per evitare dipendenze esterne in fase di boot.
     */
    private function resizeImage(string $imageContent): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring($imageContent);
            if ($src === false) {
                return null;
            }

            $origWidth = imagesx($src);
            $origHeight = imagesy($src);

            if ($origHeight <= self::MAX_HEIGHT) {
                imagedestroy($src);

                return null; // Già abbastanza piccola, non ridimensionare
            }

            $ratio = self::MAX_HEIGHT / $origHeight;
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = self::MAX_HEIGHT;

            $dst = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            ob_start();
            imagejpeg($dst, null, 85);
            $resized = ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return $resized ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parsa la risposta JSON dall'AI e normalizza i dati.
     *
     * @return array{amt: float|null, shop: string|null, dt: string|null}|null
     */
    private function parseResponse(string $content): ?array
    {
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        return [
            'amt' => isset($data['amt']) && is_numeric($data['amt']) ? (float) $data['amt'] : null,
            'shop' => isset($data['shop']) && is_string($data['shop']) ? trim($data['shop']) : null,
            'dt' => isset($data['dt']) && is_string($data['dt']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['dt']) ? $data['dt'] : null,
        ];
    }

    private function detectMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
