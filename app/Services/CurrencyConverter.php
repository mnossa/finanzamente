<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExchangeRate;
use App\Services\Fx\FrankfurterClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Converte importi tra valute con cache giornaliera in DB.
 *
 * Convenzione interna alle entità:
 *   transactions.exchange_rate_to_base = quanti EUR vale 1 unità di currency_code
 *   transactions.amount_base            = amount * exchange_rate_to_base
 *
 * La tabella `exchange_rates` invece memorizza la forma "1 base = rate quote",
 * coerente con l'output di Frankfurter (1 EUR = X valuta_target).
 */
class CurrencyConverter
{
    public const BASE_CURRENCY = 'EUR';

    public function __construct(private readonly FrankfurterClient $client) {}

    /**
     * Restituisce un payload pronto per essere salvato sulla transazione/inbox_item:
     *  - `exchange_rate_to_base`: quanti EUR vale 1 unità di $currencyCode
     *  - `amount_base`: l'importo in EUR
     *
     * Se non riusciamo a ottenere il tasso (API down e nessuna cache vicina),
     * facciamo fallback a rate=1 e logghiamo. Questo evita di bloccare l'utente
     * sulla creazione transazione/conferma inbox; l'utente può sempre correggere
     * il rate manualmente e l'amount_base si ricalcola.
     */
    public function snapshot(float $amount, string $currencyCode, ?Carbon $date = null): array
    {
        $currencyCode = strtoupper($currencyCode);

        if ($currencyCode === self::BASE_CURRENCY) {
            return [
                'exchange_rate_to_base' => 1.0,
                'amount_base' => round($amount, 2),
            ];
        }

        $rate = $this->getRateToBase($currencyCode, $date);

        return [
            'exchange_rate_to_base' => $rate,
            'amount_base' => round($amount * $rate, 2),
        ];
    }

    /**
     * Ritorna "1 unità di $currencyCode = X EUR" alla data indicata.
     */
    public function getRateToBase(string $currencyCode, ?Carbon $date = null): float
    {
        $currencyCode = strtoupper($currencyCode);
        if ($currencyCode === self::BASE_CURRENCY) {
            return 1.0;
        }

        $date = $date ? $date->copy()->startOfDay() : Carbon::today();

        // Cache hit: riga già nota per quella data
        $cached = ExchangeRate::query()
            ->where('base_code', self::BASE_CURRENCY)
            ->where('quote_code', $currencyCode)
            ->whereDate('date', $date)
            ->first();

        if ($cached !== null) {
            $eurToQuote = (float) $cached->rate;

            return $eurToQuote > 0 ? 1.0 / $eurToQuote : 1.0;
        }

        // Miss: chiediamo all'API
        $apiResult = $this->client->getRate(self::BASE_CURRENCY, $currencyCode, $date);

        if ($apiResult !== null && $apiResult['rate'] > 0) {
            $effectiveDate = Carbon::parse($apiResult['effective_date'])->toDateString();

            ExchangeRate::query()->updateOrCreate(
                [
                    'base_code' => self::BASE_CURRENCY,
                    'quote_code' => $currencyCode,
                    'date' => $effectiveDate,
                ],
                [
                    'rate' => $apiResult['rate'],
                    'source' => 'frankfurter',
                ]
            );

            // Se l'API ha restituito una data diversa (weekend → venerdì), salviamo
            // anche un alias per la data richiesta originariamente, così le richieste
            // future per quel giorno sono cache-hit immediato.
            if ($effectiveDate !== $date->toDateString()) {
                ExchangeRate::query()->updateOrCreate(
                    [
                        'base_code' => self::BASE_CURRENCY,
                        'quote_code' => $currencyCode,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'rate' => $apiResult['rate'],
                        'source' => 'fallback',
                    ]
                );
            }

            return 1.0 / $apiResult['rate'];
        }

        // API fail: cerchiamo l'ultima cache nota per la stessa coppia
        $lastKnown = ExchangeRate::query()
            ->where('base_code', self::BASE_CURRENCY)
            ->where('quote_code', $currencyCode)
            ->orderByDesc('date')
            ->first();

        if ($lastKnown !== null && (float) $lastKnown->rate > 0) {
            Log::info('CurrencyConverter usa cache vecchia per fallback', [
                'currency' => $currencyCode,
                'requested_date' => $date->toDateString(),
                'cached_date' => $lastKnown->date,
            ]);

            return 1.0 / (float) $lastKnown->rate;
        }

        Log::warning('CurrencyConverter fallback rate=1', [
            'currency' => $currencyCode,
            'date' => $date->toDateString(),
        ]);

        return 1.0;
    }

    /**
     * Costruisce il payload completo per una transazione tracciata in valuta del conto
     * a partire da un importo originale espresso in valuta diversa.
     *
     * Esempio: l'utente paga £30 ma il suo conto è in EUR e la banca gli addebita ~€35.40.
     * Salviamo `amount = 35.40` in EUR (allineato all'estratto banca), ma teniamo la
     * tracciatura "originale" come informazione contestuale.
     *
     * @return array{
     *     amount: float,
     *     currency_code: string,
     *     exchange_rate_to_base: float,
     *     amount_base: float,
     *     original_amount: float|null,
     *     original_currency_code: string|null
     * }
     */
    public function convertToAccountCurrency(
        float $originalAmount,
        string $originalCurrency,
        string $accountCurrency,
        ?Carbon $date = null,
        ?float $manualRate = null,
    ): array {
        $originalCurrency = strtoupper($originalCurrency);
        $accountCurrency = strtoupper($accountCurrency);

        if ($originalCurrency === $accountCurrency) {
            $snapshot = $this->snapshot($originalAmount, $accountCurrency, $date);

            return [
                'amount' => round($originalAmount, 2),
                'currency_code' => $accountCurrency,
                'exchange_rate_to_base' => $snapshot['exchange_rate_to_base'],
                'amount_base' => $snapshot['amount_base'],
                'original_amount' => null,
                'original_currency_code' => null,
            ];
        }

        // Override manuale: l'utente conosce il rate effettivamente applicato
        // (es. "ho cambiato sterline a 1.18 mesi fa, voglio usare quello").
        if ($manualRate !== null && $manualRate > 0) {
            // manualRate interpretato come "1 originalCurrency = manualRate EUR".
            $accountAmount = $originalCurrency === self::BASE_CURRENCY
                ? $originalAmount
                : $originalAmount * $manualRate;

            if ($accountCurrency !== self::BASE_CURRENCY) {
                $accountAmount = $accountAmount / $this->getRateToBase($accountCurrency, $date);
            }

            $snapshot = $this->snapshot($accountAmount, $accountCurrency, $date);

            return [
                'amount' => round($accountAmount, 2),
                'currency_code' => $accountCurrency,
                'exchange_rate_to_base' => $snapshot['exchange_rate_to_base'],
                'amount_base' => $snapshot['amount_base'],
                'original_amount' => round($originalAmount, 2),
                'original_currency_code' => $originalCurrency,
            ];
        }

        // Conversione automatica via cache+API
        $rateOriginalToBase = $this->getRateToBase($originalCurrency, $date);
        $rateAccountToBase = $this->getRateToBase($accountCurrency, $date);
        $accountAmount = $rateAccountToBase > 0
            ? ($originalAmount * $rateOriginalToBase) / $rateAccountToBase
            : $originalAmount;

        return [
            'amount' => round($accountAmount, 2),
            'currency_code' => $accountCurrency,
            'exchange_rate_to_base' => $rateAccountToBase,
            'amount_base' => round($accountAmount * $rateAccountToBase, 2),
            'original_amount' => round($originalAmount, 2),
            'original_currency_code' => $originalCurrency,
        ];
    }
}
