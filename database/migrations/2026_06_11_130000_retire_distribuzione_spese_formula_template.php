<?php

use App\Services\FormulaWidgetRemovalService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(FormulaWidgetRemovalService::class)->purgeRetiredOfficialTemplates(
            config('financial_variables.retired_official_template_slugs', []),
        );
    }

    public function down(): void
    {
        // Template ritirato: non ripristinato automaticamente.
    }
};
