<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdvancedRateLimitWithDelayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_rate_limit_blocks_after_max_attempts()
    {
        $route = '/register';
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
            $this->post($route, $payload, ['REMOTE_ADDR' => '127.0.0.1']);
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
        $response = $this->post($route, $payload, ['REMOTE_ADDR' => '127.0.0.1']);
        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
    }

    public function test_delay_progressivo_is_applied()
    {
        $route = '/register';
        $payload = [
            'name' => 'Test User',
            'email' => Str::random(10).'@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ];
        $start = microtime(true);
        $this->post($route, $payload);
        $this->post($route, $payload);
        $elapsed = microtime(true) - $start;
        $this->assertTrue($elapsed >= 1, 'Delay progressivo non applicato');
    }

    public function test_ip_is_logged_as_hash()
    {
        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(function ($msg, $context) {
            return isset($context['ip_hash']) && !isset($context['ip']);
        });
        $route = '/register';
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
