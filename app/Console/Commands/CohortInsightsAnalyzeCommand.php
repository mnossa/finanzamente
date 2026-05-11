<?php

namespace App\Console\Commands;

use App\Services\CohortInsights\CohortInsightNotificationWriter;
use App\Services\CohortInsights\CohortInsightPythonClient;
use App\Services\CohortInsights\CohortInsightSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CohortInsightsAnalyzeCommand extends Command
{
    protected $signature = 'insights:cohort-analyze
        {--period= : Periodo YYYY-MM (default: mese solare precedente)}';

    protected $description = 'Genera insight di cohort anonimi via servizio Python e crea notifiche in-app';

    public function handle(
        CohortInsightSnapshotBuilder $snapshotBuilder,
        CohortInsightNotificationWriter $writer,
    ): int {
        if (! config('cohort_insights.enabled', true)) {
            $this->info('Cohort insights disabilitati (COHORT_INSIGHTS_ENABLED).');

            return self::SUCCESS;
        }

        $periodOption = $this->option('period');
        if (is_string($periodOption) && $periodOption !== '') {
            if (! preg_match('/^\d{4}-\d{2}$/', $periodOption)) {
                $this->error('Opzione --period deve essere nel formato YYYY-MM.');

                return self::FAILURE;
            }
            $periodStart = Carbon::createFromFormat('Y-m-d', $periodOption.'-01')->startOfMonth();
        } else {
            $periodStart = now()->subMonthNoOverflow()->startOfMonth();
        }

        $periodEnd = $periodStart->copy()->endOfMonth();
        $periodKey = $periodStart->format('Y-m');

        $this->info("Periodo analisi: {$periodKey}");

        $snapshot = $snapshotBuilder->buildForPeriod($periodStart, $periodEnd);
        $rows = $snapshot['rows'];
        $map = $snapshot['subject_to_user_id'];

        if ($rows === []) {
            $this->info('Nessuna riga snapshot (utenti con fascia reddito o dati insufficienti).');

            return self::SUCCESS;
        }

        $this->info('Righe snapshot: '.count($rows));

        $client = CohortInsightPythonClient::fromConfig();

        if (! $client->pingHealth()) {
            $this->error('Servizio Python non raggiungibile (/health).');

            return self::FAILURE;
        }

        $kMin = (int) config('cohort_insights.k_min', 50);
        $gap = (int) config('cohort_insights.median_gap_pct_points', 15);

        try {
            $insights = $client->analyze($periodKey, $kMin, $gap, $rows);
        } catch (Throwable $e) {
            Log::error('insights:cohort-analyze — fallita chiamata python', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $created = $writer->write($periodKey, $insights, $map);
        $this->info("Notifiche create: {$created}");

        return self::SUCCESS;
    }
}
