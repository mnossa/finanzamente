<?php

use App\Services\FormulaWidgetRemovalService;
use Illuminate\Database\Migrations\Migration;

/**
 * Ritira il template formula "Fatturato annuo" (funzionalità Partita IVA rimossa).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(FormulaWidgetRemovalService::class)->purgeRetiredOfficialTemplates(
            ['official.fatturato_annuo'],
        );
    }

    public function down(): void
    {
        // Template ritirato: non ripristinato automaticamente.
    }
};
