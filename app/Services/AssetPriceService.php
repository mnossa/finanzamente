<?php

namespace App\Services;

use App\Contracts\AssetPriceProviderInterface;
use App\Services\AssetProviders\AlphaVantageProvider;
use App\Services\AssetProviders\YahooFinanceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AssetPriceService
 *
 * Servizio principale per recuperare prezzi e informazioni degli asset finanziari.
 * Supporta provider multipli (Yahoo Finance, Alpha Vantage) con fallback automatico.
 *
 * Configurazione in .env:
 * - ASSET_PRICE_PROVIDER=yahoo_finance|alpha_vantage (default: yahoo_finance)
 * - YAHOO_FINANCE_API_KEY=xxx (chiave RapidAPI)
 * - ALPHA_VANTAGE_API_KEY=xxx
 */
class AssetPriceService
{
    /**
     * Provider attivo.
     */
    private AssetPriceProviderInterface $provider;

    /**
     * Provider di fallback.
     */
    private ?AssetPriceProviderInterface $fallbackProvider = null;

    /**
     * Tutti i provider disponibili.
     */
    private array $providers = [];

    /**
     * Durata cache per mapping ISIN (24 ore).
     */
    private const CACHE_TTL_ISIN = 86400;

    /**
     * URL base OpenFIGI.
     */
    private const OPENFIGI_URL = 'https://api.openfigi.com/v3/mapping';

    public function __construct()
    {
        $this->initializeProviders();
    }

    /**
     * Inizializza i provider disponibili.
     */
    private function initializeProviders(): void
    {
        // Registra tutti i provider disponibili
        $this->providers = [
            'yahoo_finance' => new YahooFinanceProvider,
            'alpha_vantage' => new AlphaVantageProvider,
        ];

        // Determina il provider principale dalla configurazione
        $preferredProvider = config('services.asset_price.provider', 'yahoo_finance');

        // Imposta il provider principale
        if (isset($this->providers[$preferredProvider]) && $this->providers[$preferredProvider]->isConfigured()) {
            $this->provider = $this->providers[$preferredProvider];
        } else {
            // Fallback al primo provider configurato
            foreach ($this->providers as $provider) {
                if ($provider->isConfigured()) {
                    $this->provider = $provider;
                    break;
                }
            }
        }

        // Imposta provider di fallback
        foreach ($this->providers as $name => $provider) {
            if ($provider->isConfigured() && $provider !== $this->provider) {
                $this->fallbackProvider = $provider;
                break;
            }
        }

        // Se nessun provider è configurato, usa YahooFinance come default (mostrerà errore di config)
        if (! isset($this->provider)) {
            $this->provider = $this->providers['yahoo_finance'];
        }
    }

    /**
     * Verifica se almeno un provider è configurato.
     */
    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    /**
     * Ottiene il nome del provider attivo.
     */
    public function getActiveProvider(): string
    {
        return $this->provider->getName();
    }

    /**
     * Ottiene lo stato di tutti i provider.
     */
    public function getProvidersStatus(): array
    {
        return array_map(function ($provider) {
            return [
                'name' => $provider->getName(),
                'configured' => $provider->isConfigured(),
                'active' => $provider === $this->provider,
            ];
        }, $this->providers);
    }

    /**
     * Cerca asset per nome o simbolo.
     * Usa il provider principale con fallback automatico.
     */
    public function searchAssets(string $query): array
    {
        $result = $this->provider->searchAssets($query);

        // Se fallisce e abbiamo un fallback, prova con quello
        if ($result['error'] && $this->fallbackProvider) {
            Log::info('Asset search fallback', [
                'from' => $this->provider->getName(),
                'to' => $this->fallbackProvider->getName(),
            ]);
            $result = $this->fallbackProvider->searchAssets($query);
        }

        return $result;
    }

    /**
     * Ottiene il prezzo corrente di un asset.
     */
    public function getCurrentPrice(string $symbol): array
    {
        $result = $this->provider->getCurrentPrice($symbol);

        if ($result['error'] && $this->fallbackProvider) {
            Log::info('Get current price fallback', [
                'symbol' => $symbol,
                'from' => $this->provider->getName(),
                'to' => $this->fallbackProvider->getName(),
            ]);
            $result = $this->fallbackProvider->getCurrentPrice($symbol);
        }

        return $result;
    }

    /**
     * Prezzi correnti per più simboli (batch Yahoo quando disponibile).
     * Fail-soft: se il batch fallisce/timeout, ritorna i prezzi già in cache senza bloccare 20s+.
     *
     * @param  list<string>  $symbols
     * @return array<string, float> chiave = simbolo (case originale della prima occorrenza)
     */
    public function getCurrentPrices(array $symbols): array
    {
        $originalByUpper = [];
        foreach ($symbols as $symbol) {
            $symbol = trim((string) $symbol);
            if ($symbol === '') {
                continue;
            }
            $upper = strtoupper($symbol);
            $originalByUpper[$upper] ??= $symbol;
        }

        if ($originalByUpper === []) {
            return [];
        }

        $uppers = array_keys($originalByUpper);
        $pricesByUpper = [];

        if ($this->provider instanceof YahooFinanceProvider) {
            $pricesByUpper = $this->provider->getCurrentPrices($uppers);
        } else {
            foreach ($uppers as $upper) {
                $result = $this->provider->getCurrentPrice($upper);
                if (! $result['error'] && isset($result['price'])) {
                    $pricesByUpper[$upper] = (float) $result['price'];
                }
            }
        }

        $missing = array_values(array_diff($uppers, array_keys($pricesByUpper)));
        if ($missing !== [] && $this->fallbackProvider) {
            foreach (array_slice($missing, 0, 5) as $upper) {
                $result = $this->fallbackProvider->getCurrentPrice($upper);
                if (! $result['error'] && isset($result['price'])) {
                    $pricesByUpper[$upper] = (float) $result['price'];
                }
            }
        }

        $out = [];
        foreach ($pricesByUpper as $upper => $price) {
            $out[$originalByUpper[$upper] ?? $upper] = $price;
        }

        return $out;
    }

    /**
     * Ottiene il prezzo storico di un asset per una data specifica.
     */
    public function getHistoricalPrice(string $symbol, string $date): array
    {
        $result = $this->provider->getHistoricalPrice($symbol, $date);

        if ($result['error'] && $this->fallbackProvider) {
            Log::info('Get historical price fallback', [
                'symbol' => $symbol,
                'date' => $date,
                'from' => $this->provider->getName(),
                'to' => $this->fallbackProvider->getName(),
            ]);
            $result = $this->fallbackProvider->getHistoricalPrice($symbol, $date);
        }

        return $result;
    }

    /**
     * Converte un ticker in ISIN usando OpenFIGI.
     * OpenFIGI è gratuito e non richiede API key (25 req/sec).
     */
    public function tickerToIsin(string $ticker, ?string $exchange = null): array
    {
        $cacheKey = 'ticker_isin_'.strtoupper($ticker).'_'.($exchange ?? 'ANY');

        return Cache::remember($cacheKey, self::CACHE_TTL_ISIN, function () use ($ticker, $exchange) {
            try {
                $payload = [
                    [
                        'idType' => 'TICKER',
                        'idValue' => strtoupper($ticker),
                    ],
                ];

                if ($exchange) {
                    $payload[0]['exchCode'] = $exchange;
                }

                $response = Http::timeout(10)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post(self::OPENFIGI_URL, $payload);

                if (! $response->successful()) {
                    return ['error' => 'Errore OpenFIGI', 'isin' => null];
                }

                $data = $response->json();

                if (empty($data) || empty($data[0]['data'])) {
                    return ['error' => 'ISIN non trovato', 'isin' => null];
                }

                $firstMatch = $data[0]['data'][0];

                return [
                    'error' => null,
                    'ticker' => $ticker,
                    'isin' => $firstMatch['shareClassFIGI'] ?? null,
                    'name' => $firstMatch['name'] ?? null,
                    'exchange' => $firstMatch['exchCode'] ?? null,
                    'security_type' => $firstMatch['securityType'] ?? null,
                    'figi' => $firstMatch['figi'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('Ticker to ISIN error', [
                    'ticker' => $ticker,
                    'error' => $e->getMessage(),
                ]);

                return ['error' => 'Errore: '.$e->getMessage(), 'isin' => null];
            }
        });
    }

    /**
     * Converte un ISIN in ticker usando OpenFIGI.
     */
    public function isinToTicker(string $isin): array
    {
        $cacheKey = 'isin_ticker_'.strtoupper($isin);

        return Cache::remember($cacheKey, self::CACHE_TTL_ISIN, function () use ($isin) {
            try {
                $payload = [
                    [
                        'idType' => 'ID_ISIN',
                        'idValue' => strtoupper($isin),
                    ],
                ];

                $response = Http::timeout(10)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post(self::OPENFIGI_URL, $payload);

                if (! $response->successful()) {
                    return ['error' => 'Errore OpenFIGI', 'ticker' => null];
                }

                $data = $response->json();

                if (empty($data) || empty($data[0]['data'])) {
                    return ['error' => 'Ticker non trovato', 'ticker' => null];
                }

                $firstMatch = $data[0]['data'][0];

                return [
                    'error' => null,
                    'isin' => $isin,
                    'ticker' => $firstMatch['ticker'] ?? null,
                    'name' => $firstMatch['name'] ?? null,
                    'exchange' => $firstMatch['exchCode'] ?? null,
                    'security_type' => $firstMatch['securityType'] ?? null,
                    'figi' => $firstMatch['figi'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('ISIN to ticker error', [
                    'isin' => $isin,
                    'error' => $e->getMessage(),
                ]);

                return ['error' => 'Errore: '.$e->getMessage(), 'ticker' => null];
            }
        });
    }

    /**
     * Svuota la cache per un simbolo specifico.
     */
    public function clearCache(string $symbol): void
    {
        $symbol = strtoupper($symbol);

        // Pulisci cache di entrambi i provider
        $prefixes = ['yf_price_', 'yf_historical_', 'av_price_', 'av_historical_', 'ticker_isin_'];

        foreach ($prefixes as $prefix) {
            Cache::forget($prefix.$symbol);
        }
    }
}
