<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class ImageProcessingService
{
    /** Larghezza massima in pixel per le immagini degli articoli. */
    private const MAX_WIDTH = 1200;

    /** Qualità WebP (0–100). */
    private const WEBP_QUALITY = 82;

    /**
     * Converte un'immagine già salvata nel disco `public` in WebP ridimensionata.
     *
     * Legge il file a partire dal path relativo (es. `magazine/covers/uuid.jpg`),
     * lo converte in WebP con larghezza massima MAX_WIDTH, salva il nuovo file
     * nella stessa directory e — se il formato originale era diverso — elimina
     * il file originale.
     *
     * @param  string  $storagePath  Path relativo nel disco `public`.
     * @return string                Path relativo del nuovo file WebP, o quello originale in caso di errore.
     */
    public function convertToWebp(string $storagePath): string
    {
        try {
            $disk        = Storage::disk('public');
            $rawContents = $disk->get($storagePath);

            if ($rawContents === null) {
                return $storagePath;
            }

            $manager = ImageManager::gd();
            $image   = $manager->read($rawContents);

            // Ridimensiona mantenendo le proporzioni, solo se più larga del massimo.
            if ($image->width() > self::MAX_WIDTH) {
                $image = $image->scaleDown(width: self::MAX_WIDTH);
            }

            // Genera il nuovo path WebP nella stessa directory.
            $directory   = dirname($storagePath);
            $newFilename = Str::uuid() . '.webp';
            $newPath     = $directory . '/' . $newFilename;

            $disk->put($newPath, $image->toWebp(self::WEBP_QUALITY)->toString());

            // Elimina l'originale solo se il path è cambiato.
            if ($storagePath !== $newPath) {
                $disk->delete($storagePath);
            }

            return $newPath;
        } catch (\Throwable $e) {
            Log::warning('ImageProcessingService: conversione WebP fallita', [
                'path'  => $storagePath,
                'error' => $e->getMessage(),
            ]);

            return $storagePath;
        }
    }
}
