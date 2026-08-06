<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Support\FormulaTokenParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialVariableCloneService
{
    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
        private readonly SystemVariableResolver $systemVariableResolver,
    ) {}

    public function installTemplate(User $installer, string $templateSlug): FormulaWidget
    {
        $sourceWidget = FormulaWidget::query()
            ->where('template_slug', $templateSlug)
            ->where('is_official_template', true)
            ->with('financialVariable')
            ->first();

        if ($sourceWidget === null) {
            throw ValidationException::withMessages([
                'template_slug' => 'Template non trovato nella galleria.',
            ]);
        }

        return DB::transaction(function () use ($installer, $sourceWidget) {
            $variableMap = [];
            $clonedVariable = $this->cloneVariableTree(
                $installer,
                $sourceWidget->financialVariable,
                $variableMap,
            );

            $clonedWidget = FormulaWidget::create([
                'user_id' => $installer->id,
                'financial_variable_id' => $clonedVariable->id,
                'name' => $sourceWidget->name,
                'display_type' => $sourceWidget->display_type,
                'period_preset' => $sourceWidget->period_preset,
                'chart_config' => $sourceWidget->chart_config,
                'default_size' => $sourceWidget->default_size,
                'is_public' => false,
                'downloads_count' => 0,
                'source_id' => $sourceWidget->id,
                'is_official_template' => false,
                'template_slug' => null,
            ]);

            $sourceWidget->increment('downloads_count');
            $sourceWidget->financialVariable?->increment('downloads_count');

            return $clonedWidget->load('financialVariable');
        });
    }

    public function installWidget(User $installer, FormulaWidget $sourceWidget): FormulaWidget
    {
        if (! $sourceWidget->is_public || $sourceWidget->user_id === $installer->id) {
            throw ValidationException::withMessages([
                'widget' => 'Questo widget non è disponibile per l\'installazione.',
            ]);
        }

        $sourceWidget->loadMissing('financialVariable');

        return DB::transaction(function () use ($installer, $sourceWidget) {
            $variableMap = [];
            $clonedVariable = $this->cloneVariableTree(
                $installer,
                $sourceWidget->financialVariable,
                $variableMap,
            );

            $clonedWidget = FormulaWidget::create([
                'user_id' => $installer->id,
                'financial_variable_id' => $clonedVariable->id,
                'name' => $sourceWidget->name,
                'display_type' => $sourceWidget->display_type,
                'period_preset' => $sourceWidget->period_preset,
                'chart_config' => $sourceWidget->chart_config,
                'default_size' => $sourceWidget->default_size,
                'is_public' => false,
                'downloads_count' => 0,
                'source_id' => $sourceWidget->id,
            ]);

            $sourceWidget->increment('downloads_count');
            $sourceWidget->financialVariable?->increment('downloads_count');

            return $clonedWidget->load('financialVariable');
        });
    }

    /**
     * @param  array<int, FinancialVariable>  $variableMap
     */
    private function cloneVariableTree(User $installer, FinancialVariable $source, array &$variableMap): FinancialVariable
    {
        if (isset($variableMap[$source->id])) {
            return $variableMap[$source->id];
        }

        if ($source->isFormula()) {
            foreach ($this->tokenParser->extract((string) $source->formula_string) as $token) {
                if ($this->systemVariableResolver->isSystemCode($token)) {
                    continue;
                }

                $dependency = FinancialVariable::query()
                    ->where('user_id', $source->user_id)
                    ->where('code', $token)
                    ->first();

                if ($dependency !== null) {
                    $this->cloneVariableTree($installer, $dependency, $variableMap);
                }
            }

            // Solo system token → riusa formula già in libreria (evita Uscite 30g + Speso).
            if ($this->formulaUsesOnlySystemTokens((string) $source->formula_string)) {
                $reusable = app(FinancialVariableLibraryService::class)
                    ->findReusableByFormula($installer, (string) $source->formula_string);

                if ($reusable !== null) {
                    $variableMap[$source->id] = $reusable;

                    return $reusable;
                }
            }
        }

        $newCode = $this->uniqueCodeForUser($installer, $source->code);

        $clone = FinancialVariable::create([
            'user_id' => $installer->id,
            'code' => $newCode,
            'name' => $source->name,
            'type' => $source->type,
            'static_value' => $source->static_value,
            'formula_string' => $source->formula_string,
            'is_public' => false,
            'downloads_count' => 0,
            'source_id' => $source->id,
            'is_official_template' => false,
            'template_slug' => null,
        ]);

        $variableMap[$source->id] = $clone;

        return $clone;
    }

    private function formulaUsesOnlySystemTokens(string $formula): bool
    {
        foreach ($this->tokenParser->extract($formula) as $token) {
            if (! $this->systemVariableResolver->isSystemCode($token)) {
                return false;
            }
        }

        return true;
    }

    private function uniqueCodeForUser(User $user, string $baseCode): string
    {
        $code = $baseCode;
        $suffix = 1;

        while (FinancialVariable::query()->where('user_id', $user->id)->where('code', $code)->exists()) {
            $code = "{$baseCode}_{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
