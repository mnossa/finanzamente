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
}
