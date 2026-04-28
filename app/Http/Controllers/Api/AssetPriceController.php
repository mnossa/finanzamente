<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssetPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AssetPriceController
 *
 * Controller API per la ricerca di asset finanziari e recupero prezzi
 * da fonti esterne (Alpha Vantage, OpenFIGI).
 */
class AssetPriceController extends Controller
{
    public function __construct(
        private AssetPriceService $assetPriceService
    ) {}

    /**
     * Verifica se il servizio è configurato.
     */
    public function status(): JsonResponse
    {
        $providersStatus = $this->assetPriceService->getProvidersStatus();
        $activeProvider = $this->assetPriceService->getActiveProvider();
        $isConfigured = $this->assetPriceService->isConfigured();

        return response()->json([
            'configured' => $isConfigured,
            'active_provider' => $activeProvider,
            'providers' => $providersStatus,
            'message' => $isConfigured
                ? "Provider attivo: {$activeProvider}"
                : 'Nessun provider configurato. Aggiungi YAHOO_FINANCE_API_KEY o ALPHA_VANTAGE_API_KEY al file .env',
        ]);
    }

    /**
     * Cerca asset per nome o simbolo.
     *
     * GET /api/assets/search?q=apple
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $query = $request->input('q');
        $result = $this->assetPriceService->searchAssets($query);

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'results' => [],
            ], 200); // 200 anche in caso di errore API per gestione frontend
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $result['results'],
            'count' => count($result['results']),
        ]);
    }

    /**
     * Ottiene il prezzo corrente di un asset.
     *
     * GET /api/assets/price/{symbol}
     */
    public function currentPrice(string $symbol): JsonResponse
    {
        $result = $this->assetPriceService->getCurrentPrice($symbol);

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'price' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Ottiene il prezzo storico di un asset per una data specifica.
     *
     * GET /api/assets/price/{symbol}/history?date=2024-01-15
     */
    public function historicalPrice(Request $request, string $symbol): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $date = $request->input('date');
        $result = $this->assetPriceService->getHistoricalPrice($symbol, $date);

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'price' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Converte un ticker in ISIN.
     *
     * GET /api/assets/ticker-to-isin/{ticker}?exchange=US
     */
    public function tickerToIsin(Request $request, string $ticker): JsonResponse
    {
        $exchange = $request->input('exchange');
        $result = $this->assetPriceService->tickerToIsin($ticker, $exchange);

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'isin' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Converte un ISIN in ticker.
     *
     * GET /api/assets/isin-to-ticker/{isin}
     */
    public function isinToTicker(string $isin): JsonResponse
    {
        // Valida formato ISIN (2 lettere + 9 caratteri alfanumerici + 1 cifra di controllo)
        if (! preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', strtoupper($isin))) {
            return response()->json([
                'success' => false,
                'error' => 'Formato ISIN non valido',
                'ticker' => null,
            ], 200);
        }

        $result = $this->assetPriceService->isinToTicker($isin);

        if ($result['error']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'ticker' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
