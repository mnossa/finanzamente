<?php

namespace Tests\Unit;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\User;
use App\Services\FormulaWidgetDuplicateService;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetDuplicateServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormulaWidgetDuplicateService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FormulaWidgetDuplicateService::class);
        $this->user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $household->id]);
    }

    #[Test]
    public function detects_duplicate_with_same_formula_and_chart_even_if_name_differs(): void
    {
        $variableA = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $variableB = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $existing = FormulaWidget::factory()
            ->for($this->user)
            ->for($variableA, 'financialVariable')
            ->create([
                'name' => 'Saldo A',
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
            ]);

        $duplicate = $this->service->findDuplicateByVariableId(
            $this->user,
            $variableB->id,
            'kpi',
            null,
            ['format' => 'currency'],
        );

        $this->assertNotNull($duplicate);
        $this->assertSame($existing->id, $duplicate->id);
    }

    #[Test]
    public function does_not_flag_duplicate_when_display_type_differs(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'display_type' => 'kpi',
                'chart_config' => ['format' => 'currency'],
            ]);

        $duplicate = $this->service->findDuplicateByVariableId(
            $this->user,
            $variable->id,
            'line',
            'rolling_30',
            null,
        );

        $this->assertNull($duplicate);
    }

    #[Test]
    public function detects_duplicate_for_series_chart_with_same_codes_ignoring_labels(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $existing = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'display_type' => 'bar',
                'period_preset' => 'rolling_30',
                'chart_config' => [
                    'series' => [
                        ['code' => 'household_balance', 'label' => 'Liquidità'],
                        ['code' => 'total_investments', 'label' => 'Investimenti'],
                    ],
                ],
            ]);

        $duplicate = $this->service->findDuplicateByVariableId(
            $this->user,
            $variable->id,
            'bar',
            'rolling_30',
            [
                'series' => [
                    ['code' => 'household_balance', 'label' => 'Altro nome'],
                    ['code' => 'total_investments', 'label' => 'Altro investimenti'],
                ],
            ],
        );

        $this->assertNotNull($duplicate);
        $this->assertSame($existing->id, $duplicate->id);
    }

    #[Test]
    public function detects_marketplace_equivalent_official_template(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $template = FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->firstOrFail();

        $equivalent = $this->service->findMarketplaceEquivalentByVariableId(
            $this->user,
            $variable->id,
            $template->display_type,
            $template->period_preset,
            $template->chart_config,
        );

        $this->assertNotNull($equivalent);
        $this->assertSame('official.saldo_liquidita', $equivalent->template_slug);
    }

    #[Test]
    public function detects_marketplace_equivalent_community_widget(): void
    {
        $author = User::factory()->create();
        $authorVariable = FinancialVariable::factory()->for($author)->formula('[household_balance]')->create();
        $publicWidget = FormulaWidget::factory()
            ->for($author)
            ->for($authorVariable, 'financialVariable')
            ->create([
                'name' => 'Widget community',
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
                'is_public' => true,
                'share_token' => 'w_community1',
            ]);

        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $equivalent = $this->service->findMarketplaceEquivalentByVariableId(
            $this->user,
            $variable->id,
            'kpi',
            null,
            ['format' => 'currency'],
        );

        $this->assertNotNull($equivalent);
        $this->assertSame($publicWidget->id, $equivalent->id);
    }
}
