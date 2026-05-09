<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\CurrencyConverter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€']);
        Currency::firstOrCreate(['code' => 'GBP'], ['name' => 'Sterlina Britannica', 'symbol' => '£']);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'Dollaro USA', 'symbol' => '$']);
    }

    private function converter(): CurrencyConverter
    {
        return $this->app->make(CurrencyConverter::class);
    }

    public function test_snapshot_returns_identity_for_base_currency(): void
    {
        Http::fake();

        $snapshot = $this->converter()->snapshot(35.40, 'EUR');

        $this->assertEquals(1.0, $snapshot['exchange_rate_to_base']);
        $this->assertEquals(35.40, $snapshot['amount_base']);
        Http::assertNothingSent();
    }

    public function test_snapshot_uses_api_when_cache_miss(): void
    {
        Http::fake([
            'api.frankfurter.dev/v1/*' => Http::response([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => Carbon::today()->toDateString(),
                'rates' => ['GBP' => 0.85],
            ], 200),
        ]);

        $snapshot = $this->converter()->snapshot(30.0, 'GBP');

        // 1 GBP = 1/0.85 ≈ 1.176 EUR → 30 GBP ≈ 35.29 EUR
        $this->assertEqualsWithDelta(1.1764, $snapshot['exchange_rate_to_base'], 0.001);
        $this->assertEqualsWithDelta(35.29, $snapshot['amount_base'], 0.05);
        $this->assertDatabaseHas('exchange_rates', [
            'base_code' => 'EUR',
            'quote_code' => 'GBP',
            'source' => 'frankfurter',
        ]);
    }

    public function test_snapshot_uses_cache_when_available(): void
    {
        ExchangeRate::create([
            'base_code' => 'EUR',
            'quote_code' => 'GBP',
            'date' => Carbon::today(),
            'rate' => 0.80,
            'source' => 'frankfurter',
        ]);
        Http::fake();

        $snapshot = $this->converter()->snapshot(40.0, 'GBP');

        $this->assertEqualsWithDelta(1.25, $snapshot['exchange_rate_to_base'], 0.001);
        $this->assertEqualsWithDelta(50.0, $snapshot['amount_base'], 0.01);
        Http::assertNothingSent();
    }

    public function test_weekend_response_creates_alias_for_requested_date(): void
    {
        // Sabato richiesto, API risponde con il rate del venerdì.
        Http::fake([
            'api.frankfurter.dev/v1/2026-01-17*' => Http::response([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => '2026-01-16',
                'rates' => ['USD' => 1.10],
            ], 200),
        ]);

        $this->converter()->snapshot(100.0, 'USD', Carbon::parse('2026-01-17'));

        $this->assertDatabaseHas('exchange_rates', [
            'base_code' => 'EUR',
            'quote_code' => 'USD',
            'date' => '2026-01-16 00:00:00',
            'source' => 'frankfurter',
        ]);
        $this->assertDatabaseHas('exchange_rates', [
            'base_code' => 'EUR',
            'quote_code' => 'USD',
            'date' => '2026-01-17 00:00:00',
            'source' => 'fallback',
        ]);
    }

    public function test_falls_back_to_last_known_rate_when_api_fails(): void
    {
        ExchangeRate::create([
            'base_code' => 'EUR',
            'quote_code' => 'GBP',
            'date' => Carbon::today()->subDays(7),
            'rate' => 0.86,
            'source' => 'frankfurter',
        ]);
        Http::fake([
            'api.frankfurter.dev/*' => Http::response('boom', 500),
        ]);

        $snapshot = $this->converter()->snapshot(10.0, 'GBP');

        // Usa la cache vecchia: 1 GBP = 1/0.86 ≈ 1.163 EUR
        $this->assertEqualsWithDelta(1.1628, $snapshot['exchange_rate_to_base'], 0.001);
    }

    public function test_falls_back_to_rate_one_when_no_cache_and_api_fails(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response('boom', 500),
        ]);

        $snapshot = $this->converter()->snapshot(10.0, 'GBP');

        $this->assertEquals(1.0, $snapshot['exchange_rate_to_base']);
        $this->assertEquals(10.0, $snapshot['amount_base']);
    }

    public function test_convert_to_account_currency_when_currencies_match(): void
    {
        Http::fake();

        $result = $this->converter()->convertToAccountCurrency(
            originalAmount: 50.0,
            originalCurrency: 'EUR',
            accountCurrency: 'EUR',
        );

        $this->assertEquals(50.0, $result['amount']);
        $this->assertEquals('EUR', $result['currency_code']);
        $this->assertNull($result['original_amount']);
        $this->assertNull($result['original_currency_code']);
    }

    public function test_convert_to_account_currency_with_manual_rate(): void
    {
        Http::fake();

        // Pago £30 ma il conto è EUR. Override manuale: 1 GBP = 1.20 EUR.
        $result = $this->converter()->convertToAccountCurrency(
            originalAmount: 30.0,
            originalCurrency: 'GBP',
            accountCurrency: 'EUR',
            manualRate: 1.20,
        );

        $this->assertEquals(36.0, $result['amount']);
        $this->assertEquals('EUR', $result['currency_code']);
        $this->assertEquals(30.0, $result['original_amount']);
        $this->assertEquals('GBP', $result['original_currency_code']);
        $this->assertEquals(36.0, $result['amount_base']);
    }

    public function test_convert_to_account_currency_uses_api_when_no_manual_rate(): void
    {
        Http::fake([
            'api.frankfurter.dev/v1/*' => Http::sequence()
                ->push([
                    'amount' => 1.0,
                    'base' => 'EUR',
                    'date' => Carbon::today()->toDateString(),
                    'rates' => ['GBP' => 0.85],
                ], 200)
                ->push([
                    'amount' => 1.0,
                    'base' => 'EUR',
                    'date' => Carbon::today()->toDateString(),
                    'rates' => ['EUR' => 1.0],
                ], 200),
        ]);

        $result = $this->converter()->convertToAccountCurrency(
            originalAmount: 30.0,
            originalCurrency: 'GBP',
            accountCurrency: 'EUR',
        );

        $this->assertEqualsWithDelta(35.29, $result['amount'], 0.05);
        $this->assertEquals('EUR', $result['currency_code']);
        $this->assertEquals(30.0, $result['original_amount']);
        $this->assertEquals('GBP', $result['original_currency_code']);
    }
}
