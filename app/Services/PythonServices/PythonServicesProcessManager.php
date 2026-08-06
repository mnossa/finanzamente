<?php

namespace App\Services\PythonServices;

use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Avvia uvicorn solo quando un comando Artisan ne ha bisogno e lo spegne a fine job.
 * Se PYTHON_SERVICES_URL punta a un servizio già attivo (es. container dev), non fa nulla.
 */
class PythonServicesProcessManager
{
    private ?InvokedProcess $process = null;

    private bool $startedByManager = false;

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        if (! $this->ensureRunning()) {
            throw new RuntimeException(
                'Servizio Python non raggiungibile ('.$this->baseUrl().').',
            );
        }

        try {
            return $callback();
        } finally {
            $this->shutdownIfManaged();
        }
    }

    public function ensureRunning(): bool
    {
        if ($this->isHealthy()) {
            return true;
        }

        if (! (bool) config('services.python_services.manage_process', true)) {
            return false;
        }

        $this->startManagedProcess();

        return $this->waitUntilHealthy();
    }

    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->healthUrl());

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function shutdownIfManaged(): void
    {
        if (! $this->startedByManager || $this->process === null) {
            return;
        }

        if (! (bool) config('services.python_services.shutdown_after_use', true)) {
            return;
        }

        try {
            if ($this->process->running()) {
                $this->process->stop(15);
            }
        } catch (\Throwable $e) {
            Log::warning('python-services — errore arresto processo gestito', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->process = null;
            $this->startedByManager = false;
        }
    }

    private function startManagedProcess(): void
    {
        if ($this->process !== null && $this->process->running()) {
            return;
        }

        $port = $this->listenPort();

        Log::info('python-services — avvio uvicorn on-demand', [
            'port' => $port,
        ]);

        $this->process = Process::path('/python-services')
            ->env([
                'PYTHONPATH' => '/python-packages',
                'HF_HUB_OFFLINE' => '1',
            ])
            ->start([
                'python3',
                '-m',
                'uvicorn',
                'main:app',
                '--host',
                '0.0.0.0',
                '--port',
                (string) $port,
                '--workers',
                '1',
                '--no-access-log',
            ]);

        $this->startedByManager = true;
    }

    private function waitUntilHealthy(): bool
    {
        $timeout = max(30, (int) config('services.python_services.startup_timeout', 120));
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            if ($this->isHealthy()) {
                Log::info('python-services — uvicorn pronto');

                return true;
            }

            if ($this->process !== null && ! $this->process->running()) {
                Log::error('python-services — uvicorn terminato prima del health check', [
                    'output' => mb_substr($this->process->output().$this->process->errorOutput(), 0, 2000),
                ]);

                return false;
            }

            sleep(2);
        }

        Log::error('python-services — timeout avvio uvicorn', ['timeout' => $timeout]);

        return false;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.python_services.url'), '/');
    }

    private function healthUrl(): string
    {
        return $this->baseUrl().'/health';
    }

    private function listenPort(): int
    {
        $parts = parse_url($this->baseUrl());
        $port = $parts['port'] ?? null;

        if (is_int($port)) {
            return $port;
        }

        if (is_string($port) && ctype_digit($port)) {
            return (int) $port;
        }

        return 8000;
    }
}
