<?php

namespace App\Services\CohortInsights;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Invia lo snapshot al servizio Python (nessun accesso al DB da parte di FastAPI).
 */
class CohortInsightPythonClient
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        $url = rtrim((string) config('services.python_services.url'), '/');

        return new self($url);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{subject_ref: string, insight_code: string, params: array<string, mixed>}>
     */
    public function analyze(string $period, int $kMin, int $medianGapPctPoints, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $response = Http::timeout(120)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/cohort-insights/analyze", [
                'snapshot_version' => 1,
                'k_min' => $kMin,
                'median_gap_pct_points' => $medianGapPctPoints,
                'period' => $period,
                'rows' => $rows,
            ]);

        if (! $response->successful()) {
            Log::error('cohort-insights — risposta python non valida', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 2000),
            ]);

            throw new RuntimeException('Servizio analisi cohort non disponibile (HTTP '.$response->status().').');
        }

        $data = $response->json();
        $insights = $data['insights'] ?? null;
        if (! is_array($insights)) {
            throw new RuntimeException('Formato risposta cohort non valido.');
        }

        return $insights;
    }

    public function pingHealth(int $timeoutSeconds = 5): bool
    {
        try {
            $r = Http::timeout($timeoutSeconds)->get("{$this->baseUrl}/health");

            return $r->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
