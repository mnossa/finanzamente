<?php

declare(strict_types=1);

namespace App\Services\Fx;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter HTTP per https://frankfurter.dev (BCE-based, gratis, no API key).
 *
 * L'API restituisce automaticamente il tasso del giorno feriale precedente
 * quando si chiede un weekend o un festivo, quindi non dobbiamo gestire
 * il rollback noi stessi: chiediamo la data desiderata e accettiamo il
 * `date` effettivo restituito dalla risposta.
 */
class FrankfurterClient
{
    /**
     * URL base configurabile via .env per facilitare i test (e per eventuale mirror).
     */
    public function baseUrl(): string
    {
        return rtrim((string) config('services.frankfurter.base_url', 'https://api.frankfurter.dev/v1'), '/');
    }

    /**
     * Recupera il tasso "1 base = rate quote" alla data indicata.
     *
     * @return array{rate: float, effective_date: string}|null null se l'API fallisce o restituisce dati invalidi.
     */
    public function getRate(string $base, string $quote, ?Carbon $date = null): ?array
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            return [
                'rate' => 1.0,
                'effective_date' => ($date ?? Carbon::today())->toDateString(),
            ];
        }

        $datePath = $date ? $date->toDateString() : 'latest';
        $url = "{$this->baseUrl()}/{$datePath}";

        try {
            $response = Http::timeout(5)->retry(1, 200)->get($url, [
                'base' => $base,
                'symbols' => $quote,
            ]);

            if (! $response->successful()) {
                Log::warning('Frankfurter HTTP non OK', ['status' => $response->status(), 'url' => $url]);

                return null;
            }

            $payload = $response->json();
            $rate = $payload['rates'][$quote] ?? null;
            $effectiveDate = $payload['date'] ?? null;

            if ($rate === null || ! is_numeric($rate) || $rate <= 0 || ! is_string($effectiveDate)) {
                Log::warning('Frankfurter risposta inattesa', ['payload' => $payload]);

                return null;
            }

            return [
                'rate' => (float) $rate,
                'effective_date' => $effectiveDate,
            ];
        } catch (\Throwable $e) {
            Log::warning('Frankfurter eccezione', ['error' => $e->getMessage(), 'url' => $url]);

            return null;
        }
    }
}
