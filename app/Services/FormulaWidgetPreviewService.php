<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FormulaWidgetPreviewService
{
    public function __construct(
        private readonly FormulaWidgetConfigValidator $configValidator,
        private readonly FormulaWidgetPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{payload: array<string, mixed>}
     */
    public function build(User $user, array $input): array
    {
        $variable = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->findOrFail($input['financial_variable_id']);

        $this->configValidator->validate(
            (string) $input['display_type'],
            $input['period_preset'] ?? null,
            $input['chart_config'] ?? null,
            $variable->formula_string,
        );

        $widget = FormulaWidget::make([
            'name' => trim((string) ($input['name'] ?? '')) !== ''
                ? trim((string) $input['name'])
                : $variable->name,
            'display_type' => $input['display_type'],
            'period_preset' => ! empty($input['period_preset']) ? $input['period_preset'] : null,
            'chart_config' => $input['chart_config'] ?? null,
        ]);
        $widget->setRelation('financialVariable', $variable);

        $runtimeOverrides = $input['runtime_params'] ?? [];

        try {
            $payload = $this->payloadBuilder->build($widget, $user, $runtimeOverrides);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'preview' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'preview' => 'Impossibile calcolare l\'anteprima con la configurazione attuale.',
            ]);
        }

        return ['payload' => $payload];
    }

    /**
     * @param  array{template_slug?: string|null, source_widget_id?: int|null}  $input
     * @return array{payload: array<string, mixed>}
     */
    public function buildFromMarketplace(User $user, array $input): array
    {
        $source = $this->resolveMarketplaceSource(
            filled($input['template_slug'] ?? null) ? (string) $input['template_slug'] : null,
            isset($input['source_widget_id']) ? (int) $input['source_widget_id'] : null,
        );

        $variable = $source->financialVariable;

        if ($variable === null) {
            throw ValidationException::withMessages([
                'preview' => 'Variabile del widget non disponibile.',
            ]);
        }

        $this->configValidator->validate(
            $source->display_type,
            $source->period_preset,
            $source->chart_config,
            $variable->formula_string,
        );

        $widget = FormulaWidget::make([
            'name' => $source->name,
            'display_type' => $source->display_type,
            'period_preset' => $source->period_preset,
            'chart_config' => $source->chart_config,
        ]);
        $widget->setRelation('financialVariable', $variable);

        try {
            $payload = $this->payloadBuilder->build($widget, $user);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'preview' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'preview' => 'Impossibile calcolare l\'anteprima con la configurazione attuale.',
            ]);
        }

        return ['payload' => $payload];
    }

    private function resolveMarketplaceSource(?string $templateSlug, ?int $sourceWidgetId): FormulaWidget
    {
        if ($templateSlug !== null) {
            $retiredSlugs = config('financial_variables.retired_official_template_slugs', []);

            if (in_array($templateSlug, $retiredSlugs, true)) {
                throw ValidationException::withMessages([
                    'template_slug' => 'Template non disponibile.',
                ]);
            }

            $source = FormulaWidget::query()
                ->where('template_slug', $templateSlug)
                ->where('is_official_template', true)
                ->where('is_public', true)
                ->with('financialVariable')
                ->first();

            if ($source === null) {
                throw ValidationException::withMessages([
                    'template_slug' => 'Template non trovato nella galleria.',
                ]);
            }

            return $source;
        }

        if ($sourceWidgetId === null) {
            throw ValidationException::withMessages([
                'source_widget_id' => 'Widget non trovato nella galleria.',
            ]);
        }

        $source = FormulaWidget::query()
            ->where('id', $sourceWidgetId)
            ->where('is_public', true)
            ->where('is_official_template', false)
            ->with('financialVariable')
            ->first();

        if ($source === null) {
            throw ValidationException::withMessages([
                'source_widget_id' => 'Widget non trovato nella galleria.',
            ]);
        }

        return $source;
    }
}
