<?php

namespace Tests\Unit\Services;

use App\Services\AssetProviders\YahooFinanceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YahooFinanceMissingPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_regular_market_price_returns_null_not_zero(): void
    {
        config(['services.yahoo_finance.key' => 'test-key']);
        Cache::flush();

        Http::fake([
            '*market/v2/get-quotes*' => Http::response([
                'quoteResponse' => [
                    'result' => [
                        [
                            'symbol' => 'IT0005672024',
                            'shortName' => 'BTP',
                            // no regularMarketPrice
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new YahooFinanceProvider)->getCurrentPrice('IT0005672024');

        $this->assertNull($result['price']);
        $this->assertNotNull($result['error']);
    }
}
