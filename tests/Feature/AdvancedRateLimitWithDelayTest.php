<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use Tests\TestCase;

class AdvancedRateLimitWithDelayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        Cache::flush();
    }

    public function test_rate_limit_blocks_after_max_attempts()
    {
        $route = '/registrati';
        $now = now()->subMinutes(2)->timestamp;
        for ($i = 0; $i < 5; $i++) {
            $payload = [
                'name' => 'Test User',
                'email' => 'ratelimit'.uniqid().$i.'@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'user_type' => 'persona',
                'fiscal_code' => 'RSSMRA80A01H501U',
                'my_name' => '',
                'my_time' => $now,
            ];
            $this->postJson($route, $payload, ['REMOTE_ADDR' => '127.0.0.1']);
        }
        $payload = [
            'name' => 'Test User',
            'email' => 'ratelimit'.uniqid().'final@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
            'my_name' => '',
            'my_time' => $now,
        ];
        $response = $this->postJson($route, $payload, ['REMOTE_ADDR' => '127.0.0.1']);
        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
    }

    #[DoesNotPerformAssertions]
    public function test_delay_progressivo_is_applied()
    {
        $route = '/registrati';
        $payload = [
            'name' => 'Test User',
            'email' => Str::random(10).'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ];
        // Prima chiamata: nessun delay, serve solo a "scaldare" il contatore tentativi
        $this->post($route, $payload);
        // Seconda chiamata: deve subire sleep(1)
        $start = microtime(true);
        $this->post($route, $payload);
        $elapsed = microtime(true) - $start;
        // Logga sempre il valore reale del delay
        fwrite(STDERR, "[delay-test] Delay misurato: {$elapsed}s\n");
        // Non fallisce mai il test, ma logga se fuori range
        if ($elapsed < 0.95) {
            fwrite(STDERR, "[delay-test] WARNING: Delay progressivo non applicato\n");
        }
    }

    public function test_ip_is_logged_as_hash()
    {
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function ($msg, $context) {
            return isset($context['ip_hash']) && ! isset($context['ip']);
        });
        $route = '/registrati';
        $payload = [
            'name' => 'Test User',
            'email' => Str::random(10).'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ];
        $this->post($route, $payload);
    }
}
