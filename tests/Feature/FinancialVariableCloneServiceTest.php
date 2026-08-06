<?php

namespace Tests\Feature;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FinancialVariableCloneService;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialVariableCloneServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function installing_template_clones_rows_and_increments_downloads(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $installer = User::factory()->create();
        $service = app(FinancialVariableCloneService::class);

        $cloned = $service->installTemplate($installer, 'official.saldo_liquidita');

        $this->assertSame($installer->id, $cloned->user_id);
        $this->assertNotNull($cloned->source_id);
        $this->assertFalse($cloned->is_official_template);

        $official = FormulaWidget::query()->where('template_slug', 'official.saldo_liquidita')->first();
        $this->assertSame(1, $official->downloads_count);
    }

    #[Test]
    public function deleting_creator_preserves_installed_clone(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $installer = User::factory()->create();
        $service = app(FinancialVariableCloneService::class);
        $cloned = $service->installTemplate($installer, 'official.patrimonio_netto');

        $clonedVariable = $cloned->financialVariable;
        $officialVariableId = $clonedVariable->source_id;
        $clonedVariableId = $clonedVariable->id;

        FinancialVariable::query()->whereKey($officialVariableId)->delete();

        $this->assertNotNull(FinancialVariable::query()->find($clonedVariableId));
        $this->assertNull(FinancialVariable::query()->find($officialVariableId));
        $this->assertNull($clonedVariable->fresh()->source_id);
    }

    #[Test]
    public function install_reuses_existing_user_formula_with_only_system_tokens(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $installer = User::factory()->create();
        $existing = FinancialVariable::factory()->for($installer)->formula('[household_balance]')->create([
            'name' => 'Liquidità attuale',
            'code' => 'liquidita',
        ]);

        $cloned = app(FinancialVariableCloneService::class)
            ->installTemplate($installer, 'official.saldo_liquidita');

        $this->assertSame($existing->id, $cloned->financial_variable_id);
        $this->assertSame(1, FinancialVariable::query()->where('user_id', $installer->id)->count());
    }
}
