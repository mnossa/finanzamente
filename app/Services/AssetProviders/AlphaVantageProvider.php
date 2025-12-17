<?php

namespace App\Services\AssetProviders;

use App\Contracts\AssetPriceProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider Alpha Vantage.
 * 
 * Limiti piano gratuito: 25 richieste/giorno (molto restrittivo!)
 * Registrazione: https://www.alphavantage.co/support/#api-key
 */
class AlphaVantageProvider implements AssetPriceProviderInterface
{
    private const BASE_URL = 'https://www.alphavantage.co/query';
    
    private const CACHE_TTL_REALTIME = 900; // 15 minuti
    private const CACHE_TTL_HISTORICAL = 86400; // 24 ore
    private const CACHE_TTL_SEARCH = 3600; // 1 ora

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.alpha_vantage.key');
    }

    public function getName(): string
    {
        return 'alpha_vantage';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Cerca asset per nome o simbolo.
     */
    public function searchAssets(string $query): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'API Alpha Vantage non configurata', 'results' => []];
        }

        $cacheKey = 'av_search_' . md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL_SEARCH, function () use ($query) {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL, [
                    'function' => 'SYMBOL_SEARCH',
                    'keywords' => $query,
                    'apikey' => $this->apiKey,
                ]);

                if (!$response->successful()) {
                    Log::warning('Alpha Vantage search failed', [
                        'query' => $query,
                        'status' => $response->status(),
                    ]);
                    return ['error' => 'Errore nella ricerca', 'results' => []];
                }

                $data = $response->json();

                // Gestione limite API
                if (isset($data['Note']) || isset($data['Information'])) {
                    return ['error' => 'Limite API raggiunto, riprova più tardi', 'results' => []];
                }

                $matches = $data['bestMatches'] ?? [];

                return [
                    'error' => null,
                    'results' => array_map(function ($match) {
                        return [
                            'symbol' => $match['1. symbol'] ?? null,
                            'name' => $match['2. name'] ?? null,
                            'type' => $this->mapAssetType($match['3. type'] ?? null),
                            'region' => $match['4. region'] ?? null,
                            'currency' => $match['8. currency'] ?? 'USD',
                            'match_score' => (float) ($match['9. matchScore'] ?? 0),
                        ];
                    }, $matches),
                ];
            } catch (\Exception $e) {
                Log::error('Alpha Vantage search error', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
                return ['error' => 'Errore durante la ricerca: ' . $e->getMessage(), 'results' => []];
            }
        });
    }

    /**
     * Ottiene il prezzo corrente di un asset.
     */
    public function getCurrentPrice(string $symbol): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'API Alpha Vantage non configurata', 'price' => null];
        }

        $cacheKey = 'av_price_' . strtoupper($symbol);

        return Cache::remember($cacheKey, self::CACHE_TTL_REALTIME, function () use ($symbol) {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL, [
                    'function' => 'GLOBAL_QUOTE',
                    'symbol' => $symbol,
                    'apikey' => $this->apiKey,
                ]);

                if (!$response->successful()) {
                    return ['error' => 'Errore nel recupero del prezzo', 'price' => null];
                }

                $data = $response->json();

                // Gestione limite API
                if (isset($data['Note']) || isset($data['Information'])) {
                    return ['error' => 'Limite API raggiunto, riprova più tardi', 'price' => null];
                }

                $quote = $data['Global Quote'] ?? [];

                if (empty($quote)) {
                    return ['error' => 'Simbolo non trovato', 'price' => null];
                }

                return [
                    'error' => null,
                    'symbol' => $quote['01. symbol'] ?? $symbol,
                    'price' => (float) ($quote['05. price'] ?? 0),
                    'open' => (float) ($quote['02. open'] ?? 0),
                    'high' => (float) ($quote['03. high'] ?? 0),
                    'low' => (float) ($quote['04. low'] ?? 0),
                    'volume' => (int) ($quote['06. volume'] ?? 0),
                    'previous_close' => (float) ($quote['08. previous close'] ?? 0),
                    'change' => (float) ($quote['09. change'] ?? 0),
                    'change_percent' => str_replace('%', '', $quote['10. change percent'] ?? '0'),
                    'last_trading_day' => $quote['07. latest trading day'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('Alpha Vantage get price error', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage(),
                ]);
                return ['error' => 'Errore: ' . $e->getMessage(), 'price' => null];
            }
        });
    }

    /**
     * Ottiene il prezzo storico di un asset per una data specifica.
     */
    public function getHistoricalPrice(string $symbol, string $date): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'API Alpha Vantage non configurata', 'price' => null];
        }

        $cacheKey = 'av_historical_' . strtoupper($symbol) . '_' . $date;

        return Cache::remember($cacheKey, self::CACHE_TTL_HISTORICAL, function () use ($symbol, $date) {
            try {
                $response = Http::timeout(15)->get(self::BASE_URL, [
                    'function' => 'TIME_SERIES_DAILY',
                    'symbol' => $symbol,
                    'outputsize' => 'full',
                    'apikey' => $this->apiKey,
                ]);

                if (!$response->successful()) {
                    return ['error' => 'Errore nel recupero dei dati storici', 'price' => null];
                }

                $data = $response->json();

                // Gestione limite API
                if (isset($data['Note']) || isset($data['Information'])) {
                    return ['error' => 'Limite API raggiunto, riprova più tardi', 'price' => null];
                }

                $timeSeries = $data['Time Series (Daily)'] ?? [];

                if (empty($timeSeries)) {
                    return ['error' => 'Nessun dato storico disponibile', 'price' => null];
                }

                // Cerca la data esatta o la più vicina precedente
                $targetDate = $date;
                $priceData = $timeSeries[$targetDate] ?? null;

                if (!$priceData) {
                    $dates = array_keys($timeSeries);
                    sort($dates);
                    $dates = array_reverse($dates);

                    foreach ($dates as $d) {
                        if ($d <= $targetDate) {
                            $priceData = $timeSeries[$d];
                            $targetDate = $d;
                            break;
                        }
                    }
                }

                if (!$priceData) {
                    return ['error' => 'Nessun prezzo disponibile per la data richiesta', 'price' => null];
                }

                return [
                    'error' => null,
                    'symbol' => $symbol,
                    'date' => $targetDate,
                    'requested_date' => $date,
                    'price' => (float) ($priceData['4. close'] ?? 0),
                    'open' => (float) ($priceData['1. open'] ?? 0),
                    'high' => (float) ($priceData['2. high'] ?? 0),
                    'low' => (float) ($priceData['3. low'] ?? 0),
                    'close' => (float) ($priceData['4. close'] ?? 0),
                    'volume' => (int) ($priceData['5. volume'] ?? 0),
                ];
            } catch (\Exception $e) {
                Log::error('Alpha Vantage historical price error', [
                    'symbol' => $symbol,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
                return ['error' => 'Errore: ' . $e->getMessage(), 'price' => null];
            }
        });
    }

    /**
     * Mappa il tipo di asset di Alpha Vantage al tipo interno.
     */
    private function mapAssetType(?string $alphaType): string
    {
        return match (strtolower($alphaType ?? '')) {
            'equity' => 'stock',
            'etf' => 'etf',
            'mutual fund' => 'etf',
            'index' => 'index',
            'cryptocurrency' => 'crypto',
            default => 'other',
        };
    }
}
