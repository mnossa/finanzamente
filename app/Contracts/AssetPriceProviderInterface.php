<?php

namespace App\Contracts;

/**
 * Interface per i provider di prezzi e informazioni asset.
 * Permette di implementare diversi provider (Yahoo Finance, Alpha Vantage, ecc.)
 * con la stessa interfaccia.
 */
interface AssetPriceProviderInterface
{
    /**
     * Nome identificativo del provider.
     */
    public function getName(): string;

    /**
     * Verifica se il provider è configurato correttamente.
     */
    public function isConfigured(): bool;

    /**
     * Cerca asset per nome o simbolo.
     *
     * @param string $query Testo di ricerca
     * @return array{error: string|null, results: array}
     */
    public function searchAssets(string $query): array;

    /**
     * Ottiene il prezzo corrente di un asset.
     *
     * @param string $symbol Ticker/simbolo dell'asset
     * @return array{error: string|null, price: float|null, ...}
     */
    public function getCurrentPrice(string $symbol): array;

    /**
     * Ottiene il prezzo storico di un asset per una data specifica.
     *
     * @param string $symbol Ticker/simbolo dell'asset
     * @param string $date Data nel formato Y-m-d
     * @return array{error: string|null, price: float|null, date: string|null, ...}
     */
    public function getHistoricalPrice(string $symbol, string $date): array;
}
