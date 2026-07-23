<?php

namespace App\Jobs;

use App\Models\FormulaWidget;
use App\Services\FormulaWidgetRemovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeSoftDeletedFormulaWidgetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $formulaWidgetId,
    ) {}

    public function handle(FormulaWidgetRemovalService $removalService): void
    {
        $widget = FormulaWidget::withTrashed()->find($this->formulaWidgetId);

        if ($widget === null || ! $widget->trashed()) {
            return;
        }

        $removalService->purge($widget);
    }
}
