<?php

namespace App\Services\AssetProviders;

use App\Contracts\AssetPriceProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider Yahoo Finance via RapidAPI.
 *
 * Limiti piano gratuito: ~500 richieste/mese
 * Registrazione: https://rapidapi.com/apidojo/api/yfinance/
 */
class YahooFinanceProvider implements AssetPriceProviderInterface
{
    private const API_HOST = 'apidojo-yahoo-finance-v1.p.rapidapi.com';

    private const BASE_URL = 'https://apidojo-yahoo-finance-v1.p.rapidapi.com';

    private const CACHE_TTL_REALTIME = 900; // 15 minuti

    private const CACHE_TTL_HISTORICAL = 86400; // 24 ore

    private const CACHE_TTL_SEARCH = 3600; // 1 ora

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.yahoo_finance.key');
    }

    public function getName(): string
    {
        return 'yahoo_finance';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Cerca asset per nome o simbolo.
     */
    public function searchAssets(string $query): array
    {
        if (! $this->isConfigured()) {
            return ['error' => 'API Yahoo Finance non configurata', 'results' => []];
        }

        $cacheKey = 'yf_search_'.md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL_SEARCH, function () use ($query) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'x-rapidapi-key' => $this->apiKey,
                        'x-rapidapi-host' => self::API_HOST,
                    ])
                    ->get(self::BASE_URL.'/auto-complete', [
                        'q' => $query,
                        'region' => 'IT',
                    ]);

                if (! $response->successful()) {
                    Log::warning('Yahoo Finance search failed', [
                        'query' => $query,
                        'status' => $response->status(),
                    ]);

                    return ['error' => 'Errore nella ricerca', 'results' => []];
                }

                $data = $response->json();
                $quotes = $data['quotes'] ?? [];

                return [
                    'error' => null,
                    'results' => array_map(function ($quote) {
                        return [
                            'symbol' => $quote['symbol'] ?? null,
                            'name' => $quote['shortname'] ?? $quote['longname'] ?? null,
                            'type' => $this->mapAssetType($quote['quoteType'] ?? null, $quote['typeDisp'] ?? null),
                            'region' => $quote['exchDisp'] ?? null,
                            'currency' => null, // Non sempre disponibile nella ricerca
                            'match_score' => (float) ($quote['score'] ?? 0),
                        ];
                    }, array_slice($quotes, 0, 10)), // Limita a 10 risultati
                ];
            } catch (\Exception $e) {
                Log::error('Yahoo Finance search error', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                return ['error' => 'Errore durante la ricerca: '.$e->getMessage(), 'results' => []];
            }
        });
    }

    /**
     * Ottiene il prezzo corrente di un asset.
     * Non mette in cache le risposte di errore (evita fallback ripetuti su miss stale).
     */
    public function getCurrentPrice(string $symbol): array
    {
        if (! $this->isConfigured()) {
            return ['error' => 'API Yahoo Finance non configurata', 'price' => null];
        }

        $cacheKey = 'yf_price_'.strtoupper($symbol);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && empty($cached['error']) && isset($cached['price'])) {
            return $cached;
        }

        $result = $this->fetchCurrentPriceFromApi($symbol);
        if (empty($result['error']) && isset($result['price'])) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_REALTIME);
        }

        return $result;
    }

    /**
     * Prezzi correnti in batch (una HTTP per chunk). Chiavi = simbolo uppercase.
     *
     * @param  list<string>  $symbols
     * @return array<string, float>
     */
    public function getCurrentPrices(array $symbols): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $normalized = [];
        foreach ($symbols as $symbol) {
            $symbol = strtoupper(trim((string) $symbol));
            if ($symbol !== '') {
                $normalized[$symbol] = $symbol;
            }
        }
        $normalized = array_values($normalized);
        if ($normalized === []) {
            return [];
        }

        $prices = [];
        $misses = [];

        foreach ($normalized as $symbol) {
            $cached = Cache::get('yf_price_'.$symbol);
            if (is_array($cached) && empty($cached['error']) && isset($cached['price'])) {
                $prices[$symbol] = (float) $cached['price'];
            } else {
                $misses[] = $symbol;
            }
        }

        foreach (array_chunk($misses, 25) as $chunk) {
            try {
                $response = Http::timeout(3)
                    ->withHeaders([
                        'x-rapidapi-key' => $this->apiKey,
                        'x-rapidapi-host' => self::API_HOST,
                    ])
                    ->get(self::BASE_URL.'/market/v2/get-quotes', [
                        'region' => 'US',
                        'symbols' => implode(',', $chunk),
                    ]);

                if (! $response->successful()) {
                    Log::warning('Yahoo Finance batch quotes failed', [
                        'symbols' => $chunk,
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                $quotes = $response->json('quoteResponse.result') ?? [];
                foreach ($quotes as $quote) {
                    $symbol = strtoupper((string) ($quote['symbol'] ?? ''));
                    if ($symbol === '' || ! isset($quote['regularMarketPrice'])) {
                        continue;
                    }

                    $payload = [
                        'error' => null,
                        'symbol' => $symbol,
                        'price' => round((float) $quote['regularMarketPrice'], 2),
                        'open' => round((float) ($quote['regularMarketOpen'] ?? 0), 2),
                        'high' => round((float) ($quote['regularMarketDayHigh'] ?? 0), 2),
                        'low' => round((float) ($quote['regularMarketDayLow'] ?? 0), 2),
                        'volume' => (int) ($quote['regularMarketVolume'] ?? 0),
                        'previous_close' => round((float) ($quote['regularMarketPreviousClose'] ?? 0), 2),
                        'change' => round((float) ($quote['regularMarketChange'] ?? 0), 2),
                        'change_percent' => round((float) ($quote['regularMarketChangePercent'] ?? 0), 2),
                        'currency' => $quote['currency'] ?? null,
                        'name' => $quote['shortName'] ?? $quote['longName'] ?? null,
                    ];

                    Cache::put('yf_price_'.$symbol, $payload, self::CACHE_TTL_REALTIME);
                    $prices[$symbol] = $payload['price'];
                }
            } catch (\Exception $e) {
                Log::error('Yahoo Finance batch quotes error', [
                    'symbols' => $chunk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $prices;
    }

    /**
     * @return array{error: string|null, price: float|null, symbol?: string, ...}
     */
    private function fetchCurrentPriceFromApi(string $symbol): array
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => self::API_HOST,
                ])
                ->get(self::BASE_URL.'/market/v2/get-quotes', [
                    'region' => 'US',
                    'symbols' => $symbol,
                ]);

            if (! $response->successful()) {
                return ['error' => 'Errore nel recupero del prezzo', 'price' => null];
            }

            $quotes = $response->json('quoteResponse.result') ?? [];
            if ($quotes === []) {
                return ['error' => 'Simbolo non trovato', 'price' => null];
            }

            $quote = $quotes[0];
            $marketPrice = $quote['regularMarketPrice'] ?? null;
            if (! is_numeric($marketPrice)) {
                return ['error' => 'Prezzo di mercato non disponibile', 'price' => null];
            }

            $price = (float) $marketPrice;
            if ($price <= 0) {
                return ['error' => 'Prezzo di mercato non disponibile', 'price' => null];
            }

            return [
                'error' => null,
                'symbol' => is_string($quote['symbol'] ?? null) ? $quote['symbol'] : $symbol,
                'price' => round($price, 2),
                'open' => $this->safeFloat($quote['regularMarketOpen'] ?? null),
                'high' => $this->safeFloat($quote['regularMarketDayHigh'] ?? null),
                'low' => $this->safeFloat($quote['regularMarketDayLow'] ?? null),
                'volume' => is_numeric($quote['regularMarketVolume'] ?? null) ? (int) $quote['regularMarketVolume'] : 0,
                'previous_close' => $this->safeFloat($quote['regularMarketPreviousClose'] ?? null),
                'change' => $this->safeFloat($quote['regularMarketChange'] ?? null),
                'change_percent' => $this->safeFloat($quote['regularMarketChangePercent'] ?? null),
                'currency' => is_string($quote['currency'] ?? null) ? $quote['currency'] : null,
                'name' => is_string($quote['shortName'] ?? null)
                    ? $quote['shortName']
                    : (is_string($quote['longName'] ?? null) ? $quote['longName'] : null),
            ];
        } catch (\Throwable $e) {
            Log::error('Yahoo Finance get price error', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Errore nel recupero del prezzo', 'price' => null];
        }
    }

    private function safeFloat(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    /**
     * Ottiene il prezzo storico di un asset per una data specifica.
     * Usa l'endpoint get-chart con range appropriato.
     */
    public function getHistoricalPrice(string $symbol, string $date): array
    {
        if (! $this->isConfigured()) {
            return ['error' => 'API Yahoo Finance non configurata', 'price' => null];
        }

        $cacheKey = 'yf_historical_'.strtoupper($symbol).'_'.$date;

        return Cache::remember($cacheKey, self::CACHE_TTL_HISTORICAL, function () use ($symbol, $date) {
            try {
                $targetTimestamp = strtotime($date);
                $now = time();
                $daysDiff = ceil(($now - $targetTimestamp) / 86400);

                // Determina il range appropriato basato sulla differenza di giorni
                $range = match (true) {
                    $daysDiff <= 5 => '5d',
                    $daysDiff <= 30 => '1mo',
                    $daysDiff <= 90 => '3mo',
                    $daysDiff <= 180 => '6mo',
                    $daysDiff <= 365 => '1y',
                    $daysDiff <= 730 => '2y',
                    $daysDiff <= 1825 => '5y',
                    default => '10y',
                };

                $response = Http::timeout(15)
                    ->withHeaders([
                        'x-rapidapi-key' => $this->apiKey,
                        'x-rapidapi-host' => self::API_HOST,
                    ])
                    ->get(self::BASE_URL.'/stock/v3/get-chart', [
                        'symbol' => $symbol,
                        'interval' => '1d',
                        'range' => $range,
                        'region' => 'US',
                    ]);

                if (! $response->successful()) {
                    return ['error' => 'Errore nel recupero dei dati storici', 'price' => null];
                }

                $data = $response->json();
                $result = $data['chart']['result'][0] ?? null;

                if (! $result) {
                    return ['error' => 'Nessun dato storico disponibile', 'price' => null];
                }

                $timestamps = $result['timestamp'] ?? [];
                $quotes = $result['indicators']['quote'][0] ?? [];

                if (empty($timestamps)) {
                    return ['error' => 'Nessun dato storico disponibile', 'price' => null];
                }

                // Cerca la data esatta o la più vicina
                $closestIndex = null;
                $closestDate = null;
                $minDiff = PHP_INT_MAX;

                foreach ($timestamps as $index => $timestamp) {
                    $priceDate = date('Y-m-d', $timestamp);
                    $diff = abs($timestamp - $targetTimestamp);

                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $closestIndex = $index;
                        $closestDate = $priceDate;
                    }

                    // Se è la data esatta, interrompi
                    if ($priceDate === $date) {
                        break;
                    }
                }

                if ($closestIndex === null) {
                    return ['error' => 'Nessun prezzo disponibile per la data richiesta', 'price' => null];
                }

                $close = $quotes['close'][$closestIndex] ?? null;
                $open = $quotes['open'][$closestIndex] ?? null;
                $high = $quotes['high'][$closestIndex] ?? null;
                $low = $quotes['low'][$closestIndex] ?? null;
                $volume = $quotes['volume'][$closestIndex] ?? null;

                return [
                    'error' => null,
                    'symbol' => $symbol,
                    'date' => $closestDate,
                    'requested_date' => $date,
                    'price' => round((float) ($close ?? 0), 2),
                    'open' => round((float) ($open ?? 0), 2),
                    'high' => round((float) ($high ?? 0), 2),
                    'low' => round((float) ($low ?? 0), 2),
                    'close' => round((float) ($close ?? 0), 2),
                    'volume' => (int) ($volume ?? 0),
                    'currency' => $result['meta']['currency'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('Yahoo Finance historical price error', [
                    'symbol' => $symbol,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);

                return ['error' => 'Errore: '.$e->getMessage(), 'price' => null];
            }
        });
    }

    /**
     * Mappa il tipo di asset di Yahoo Finance al tipo interno.
     */
    private function mapAssetType(?string $quoteType, ?string $typeDisp): string
    {
        $type = strtolower($quoteType ?? '');
        $display = strtolower($typeDisp ?? '');

        return match (true) {
            $type === 'equity' => 'stock',
            $type === 'etf' => 'etf',
            $type === 'mutualfund' => 'etf',
            $type === 'index' => 'index',
            $type === 'cryptocurrency' => 'crypto',
            $type === 'currency' => 'other',
            $type === 'bond' || str_contains($display, 'bond') || str_contains($display, 'fixed income') => 'bond',
            str_contains($display, 'commodity') => 'commodity',
            default => 'other',
        };
    }
}
