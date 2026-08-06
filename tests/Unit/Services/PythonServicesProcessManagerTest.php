<?php

namespace Tests\Unit\Services;

use App\Services\PythonServices\PythonServicesProcessManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PythonServicesProcessManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function run_executes_callback_when_health_is_already_ok(): void
    {
        Config::set('services.python_services.url', 'http://python-services.test:8000');
        Config::set('services.python_services.manage_process', true);

        Http::fake([
            'http://python-services.test:8000/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $manager = app(PythonServicesProcessManager::class);
        $executed = false;

        $manager->run(function () use (&$executed) {
            $executed = true;

            return 42;
        });

        $this->assertTrue($executed);
    }

    #[Test]
    public function run_fails_when_service_unreachable_and_process_management_disabled(): void
    {
        Config::set('services.python_services.url', 'http://python-services.test:8000');
        Config::set('services.python_services.manage_process', false);

        Http::fake([
            'http://python-services.test:8000/health' => Http::response('', 503),
        ]);

        $manager = app(PythonServicesProcessManager::class);

        $this->expectException(RuntimeException::class);

        $manager->run(fn () => true);
    }
}
