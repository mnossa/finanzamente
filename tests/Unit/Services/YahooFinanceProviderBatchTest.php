<?php

namespace Tests\Unit\Services;

use App\Services\AssetProviders\YahooFinanceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YahooFinanceProviderBatchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function get_current_prices_fetches_missing_symbols_in_one_request(): void
    {
        config(['services.yahoo_finance.key' => 'test-key']);
        Cache::flush();

        Http::fake([
            'apidojo-yahoo-finance-v1.p.rapidapi.com/*' => Http::response([
                'quoteResponse' => [
                    'result' => [
                        ['symbol' => 'AAPL', 'regularMarketPrice' => 190.5],
                        ['symbol' => 'MSFT', 'regularMarketPrice' => 410.25],
                    ],
                ],
            ], 200),
        ]);

        $provider = new YahooFinanceProvider;
        $prices = $provider->getCurrentPrices(['AAPL', 'MSFT']);

        $this->assertSame(190.5, $prices['AAPL']);
        $this->assertSame(410.25, $prices['MSFT']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'get-quotes')
                && str_contains($request->url(), 'AAPL')
                && str_contains($request->url(), 'MSFT');
        });
    }

    #[Test]
    public function get_current_price_does_not_cache_errors(): void
    {
        config(['services.yahoo_finance.key' => 'test-key']);
        Cache::flush();

        Http::fake([
            'apidojo-yahoo-finance-v1.p.rapidapi.com/*' => Http::sequence()
                ->push(['quoteResponse' => ['result' => []]], 200)
                ->push([
                    'quoteResponse' => [
                        'result' => [
                            ['symbol' => 'FAIL', 'regularMarketPrice' => 12.34],
                        ],
                    ],
                ], 200),
        ]);

        $provider = new YahooFinanceProvider;
        $first = $provider->getCurrentPrice('FAIL');
        $this->assertNotNull($first['error']);

        $second = $provider->getCurrentPrice('FAIL');
        $this->assertNull($second['error']);
        $this->assertSame(12.34, $second['price']);

        Http::assertSentCount(2);
    }
}
