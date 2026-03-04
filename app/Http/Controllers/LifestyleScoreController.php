<?php

namespace App\Http\Controllers;

use App\Services\FinancialMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;

class LifestyleScoreController extends Controller
{
    public function __construct(
        private readonly FinancialMetricsService $metricsService
    ) {}

    /**
     * Pagina di dettaglio del Lifestyle Inflation Score — storico completo + trend.
     */
    public function index(Request $request): \Inertia\Response
    {
        $user = Auth::user();

        [$startDate, $endDate, $dateRangeLabel] = $this->getFullRange($user);
        $data  = $this->metricsService->calculate($user, $startDate, $endDate);
        $trend = $this->calculateTrend($user);

        return Inertia::render('LifestyleScore/Index', [
            'metrics'        => $data,
            'trend'          => $trend,
            'dateRangeLabel' => $dateRangeLabel,
        ]);
    }

    /**
     * Esporta i dati del Lifestyle Score in formato XLSX.
     */
    public function exportXls(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();

        [$startDate, $endDate, $dateRangeLabel] = $this->getFullRange($user);
        $data = $this->metricsService->calculate($user, $startDate, $endDate);

        $filename = 'lifestyle_score_' . now()->format('Y-m-d') . '.xlsx';
        $tmpPath  = sys_get_temp_dir() . '/' . uniqid('ls_') . '.xlsx';

        $writer = new XlsxWriter();
        $writer->openToFile($tmpPath);

        // Intestazione
        $writer->addRow(Row::fromValues(['Lifestyle Inflation Score — ' . $dateRangeLabel]));
        $writer->addRow(Row::fromValues([]));

        // Riepilogo
        $writer->addRow(Row::fromValues(['RIEPILOGO']));
        $writer->addRow(Row::fromValues(['Reddito Lordo', number_format($data['gross_income'], 2, ',', '.') . ' €']));

        if ($data['is_partita_iva']) {
            $writer->addRow(Row::fromValues([
                'Contributi INPS (' . $data['inps_rate'] . '%)',
                number_format($data['inps_amount'], 2, ',', '.') . ' €',
            ]));
            $writer->addRow(Row::fromValues([
                'Flat Tax (' . $data['tax_rate'] . '% su lordo−INPS)',
                number_format($data['flat_tax_amount'], 2, ',', '.') . ' €',
            ]));
            $writer->addRow(Row::fromValues([
                'Totale Tasse Stimate',
                number_format($data['estimated_taxes'], 2, ',', '.') . ' €',
            ]));
        }

        $writer->addRow(Row::fromValues(['Reddito Netto', number_format($data['net_income'], 2, ',', '.') . ' €']));
        $writer->addRow(Row::fromValues(['Spese Totali', number_format($data['total_expenses'], 2, ',', '.') . ' €']));
        $writer->addRow(Row::fromValues(['Investimenti / Esclusi', number_format($data['excluded_expenses'], 2, ',', '.') . ' €']));
        $writer->addRow(Row::fromValues(['Spese Effettive', number_format($data['effective_expenses'], 2, ',', '.') . ' €']));
        $writer->addRow(Row::fromValues([
            'Lifestyle Score',
            $data['lifestyle_score'] !== null ? number_format($data['lifestyle_score'], 1, ',', '.') . '%' : 'N/D',
        ]));
        $writer->addRow(Row::fromValues([]));

        // Dettaglio per categoria
        $writer->addRow(Row::fromValues(['DETTAGLIO PER CATEGORIA']));
        $writer->addRow(Row::fromValues(['Categoria', 'Importo (€)', '% sul totale', 'Esclusa dal Score']));

        foreach ($data['category_breakdown'] as $row) {
            $writer->addRow(Row::fromValues([
                $row['name'],
                number_format($row['amount'], 2, ',', '.'),
                number_format($row['percentage'], 1, ',', '.') . '%',
                $row['excluded'] ? 'Sì' : 'No',
            ]));
        }

        $writer->close();

        return response()->streamDownload(function () use ($tmpPath) {
            readfile($tmpPath);
            @unlink($tmpPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Esporta un report sintetico in PDF (HTML→PDF).
     */
    public function exportPdf(Request $request): Response
    {
        $user = Auth::user();

        [$startDate, $endDate, $dateRangeLabel] = $this->getFullRange($user);
        $data = $this->metricsService->calculate($user, $startDate, $endDate);

        $html = view('pdf.lifestyle-score', [
            'metrics'     => $data,
            'periodLabel' => $dateRangeLabel,
            'user'        => $user,
            'generatedAt' => now(),
        ])->render();

        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="lifestyle_score_' . now()->format('Y-m-d') . '.html"');
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce il range completo: dalla prima transazione della household a oggi.
     *
     * @return array{Carbon, Carbon, string}
     */
    private function getFullRange(\App\Models\User $user): array
    {
        $firstTx = \App\Models\Transaction::whereHas(
            'account',
            fn ($q) => $q->where('household_id', $user->active_household_id)
        )->whereNull('transfer_id')->oldest('date')->first();

        $start = $firstTx
            ? Carbon::parse($firstTx->date)->startOfDay()
            : Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfDay();

        $label = $firstTx
            ? Carbon::parse($firstTx->date)->translatedFormat('M Y') . ' – Oggi'
            : 'Questo mese';

        return [$start, $end, $label];
    }

    /**
     * Calcola il trend: confronta gli ultimi 30 giorni con i 30 precedenti.
     */
    private function calculateTrend(\App\Models\User $user): array
    {
        $last30Start = Carbon::now()->subDays(29)->startOfDay();
        $last30End   = Carbon::now()->endOfDay();
        $prev30Start = Carbon::now()->subDays(59)->startOfDay();
        $prev30End   = Carbon::now()->subDays(30)->endOfDay();

        $last30 = $this->metricsService->calculate($user, $last30Start, $last30End);
        $prev30 = $this->metricsService->calculate($user, $prev30Start, $prev30End);

        $last30Score = $last30['lifestyle_score'];
        $prev30Score = $prev30['lifestyle_score'];

        $delta     = null;
        $direction = 'unknown';

        if ($last30Score !== null && $prev30Score !== null) {
            $delta     = round($last30Score - $prev30Score, 1);
            $direction = $delta > 1.0 ? 'up' : ($delta < -1.0 ? 'down' : 'stable');
        } elseif ($last30Score !== null) {
            $direction = 'new';
        }

        return [
            'last30_score' => $last30Score !== null ? round($last30Score, 1) : null,
            'prev30_score' => $prev30Score !== null ? round($prev30Score, 1) : null,
            'delta'        => $delta,
            'direction'    => $direction,
        ];
    }
}
