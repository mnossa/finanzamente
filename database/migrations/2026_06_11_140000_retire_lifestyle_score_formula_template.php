<?php

use App\Services\FormulaWidgetRemovalService;
use Illuminate\Database\Migrations\Migration;

/**
 * Ritira il template formula "Lifestyle Inflation Score": duplicato del widget built-in lifestyle_widget.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(FormulaWidgetRemovalService::class)->purgeRetiredOfficialTemplates(
            ['official.lifestyle_score'],
        );
    }

    public function down(): void
    {
        // Template ritirato: non ripristinato automaticamente.
    }
};
