<?php

use App\Models\InvestmentPac;
use App\Services\InvestmentPacService;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot: riallinea buy_date dei movimenti PAC alla data schedulata (start_date.day)
 * e aggiorna last_executed_at. Vedi WFI-96.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(InvestmentPacService::class);

        InvestmentPac::query()
            ->whereHas('investments')
            ->orderBy('id')
            ->chunkById(50, function ($pacs) use ($service) {
                foreach ($pacs as $pac) {
                    $service->realignPacMovements($pac);
                }
            });
    }

    public function down(): void
    {
        // Backfill dati non reversibile.
    }
};
