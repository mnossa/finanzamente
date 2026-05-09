<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Fx\FrankfurterClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrankfurterClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_rate_for_specific_date(): void
    {
        Http::fake([
            'api.frankfurter.dev/v1/2026-01-15*' => Http::response([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => '2026-01-15',
                'rates' => ['GBP' => 0.847],
            ], 200),
        ]);

        $client = new FrankfurterClient;
        $result = $client->getRate('EUR', 'GBP', Carbon::parse('2026-01-15'));

        $this->assertNotNull($result);
        $this->assertEquals(0.847, $result['rate']);
        $this->assertEquals('2026-01-15', $result['effective_date']);
    }

    public function test_handles_weekend_rollback_via_effective_date(): void
    {
        // Frankfurter su un sabato restituisce automaticamente il rate di venerdì.
        Http::fake([
            'api.frankfurter.dev/v1/2026-01-17*' => Http::response([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => '2026-01-16',
                'rates' => ['USD' => 1.085],
            ], 200),
        ]);

        $client = new FrankfurterClient;
        $result = $client->getRate('EUR', 'USD', Carbon::parse('2026-01-17'));

        $this->assertNotNull($result);
        $this->assertEquals(1.085, $result['rate']);
        $this->assertEquals('2026-01-16', $result['effective_date']);
    }

    public function test_short_circuit_when_base_equals_quote(): void
    {
        Http::fake();

        $client = new FrankfurterClient;
        $result = $client->getRate('EUR', 'EUR');

        $this->assertNotNull($result);
        $this->assertEquals(1.0, $result['rate']);
        Http::assertNothingSent();
    }

    public function test_returns_null_on_http_error(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response('Server error', 500),
        ]);

        $client = new FrankfurterClient;
        $result = $client->getRate('EUR', 'GBP');

        $this->assertNull($result);
    }

    public function test_returns_null_on_unexpected_payload(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response(['unexpected' => 'shape'], 200),
        ]);

        $client = new FrankfurterClient;
        $result = $client->getRate('EUR', 'GBP');

        $this->assertNull($result);
    }

    public function test_returns_null_on_negative_or_zero_rate(): void
    {
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => '2026-01-15',
                'rates' => ['GBP' => 0],
            ], 200),
        ]);

        $client = new FrankfurterClient;
        $result = $client->getRate('EUR', 'GBP');

        $this->assertNull($result);
    }
}
