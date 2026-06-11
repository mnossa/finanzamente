<?php

namespace App\Http\Requests;

use App\Models\FormulaWidget;
use App\Services\FormulaWidgetConfigValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreFormulaWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'financial_variable_id' => [
                'required',
                'integer',
                Rule::exists('financial_variables', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'display_type' => ['required', Rule::in(FormulaWidget::displayTypes())],
            'period_preset' => ['nullable', 'string', 'max:32'],
            'chart_config' => ['nullable', 'array'],
            'default_size' => ['nullable', Rule::in(FormulaWidget::SIZES)],
            'is_public' => ['sometimes', 'boolean'],
            'pin_to_dashboard' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            try {
                app(FormulaWidgetConfigValidator::class)->validate(
                    (string) $this->input('display_type'),
                    $this->input('period_preset'),
                    $this->input('chart_config'),
                );
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $v->errors()->add($field, $message);
                    }
                }
            }
        });
    }
}
