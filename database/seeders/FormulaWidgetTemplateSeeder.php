<?php

namespace Database\Seeders;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Services\FormulaSystemUserService;
use App\Services\FormulaWidgetRemovalService;
use Illuminate\Database\Seeder;

class FormulaWidgetTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $systemUser = app(FormulaSystemUserService::class)->getOrCreate();
        $templates = config('formula_widget_templates', []);

        foreach ($templates as $template) {
            $slug = $template['template_slug'];
            $variableConfig = $template['variable'];

            $variable = FinancialVariable::query()->updateOrCreate(
                [
                    'user_id' => $systemUser->id,
                    'code' => $variableConfig['code'],
                ],
                [
                    'name' => $variableConfig['name'],
                    'type' => $variableConfig['type'],
                    'static_value' => $variableConfig['static_value'] ?? null,
                    'formula_string' => $variableConfig['formula_string'] ?? null,
                    'is_public' => true,
                    'is_official_template' => true,
                    'template_slug' => $slug.'_var',
                ],
            );

            FormulaWidget::query()->updateOrCreate(
                ['template_slug' => $slug],
                [
                    'user_id' => $systemUser->id,
                    'financial_variable_id' => $variable->id,
                    'name' => $template['name'],
                    'display_type' => $template['display_type'],
                    'period_preset' => $template['period_preset'],
                    'chart_config' => $template['chart_config'] ?? null,
                    'default_size' => $template['default_size'] ?? 'md',
                    'is_public' => true,
                    'is_official_template' => true,
                ],
            );
        }

        app(FormulaWidgetRemovalService::class)->purgeRetiredOfficialTemplates(
            config('financial_variables.retired_official_template_slugs', []),
        );
    }
}
