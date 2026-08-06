<?php

namespace App\Http\Requests;

use App\Models\FinancialVariable;
use App\Services\FormulaResolverService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateFinancialVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var FinancialVariable $variable */
        $variable = $this->route('financial_variable');
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('financial_variables', 'code')
                    ->where(fn ($q) => $q->where('user_id', $userId))
                    ->ignore($variable->id),
            ],
            'type' => ['sometimes', Rule::in([FinancialVariable::TYPE_STATIC, FinancialVariable::TYPE_FORMULA])],
            'static_value' => ['nullable', 'numeric'],
            'formula_string' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            /** @var FinancialVariable $variable */
            $variable = $this->route('financial_variable');
            $type = $this->input('type', $variable->type);
            if ($type !== FinancialVariable::TYPE_FORMULA) {
                return;
            }

            $formula = (string) $this->input('formula_string', $variable->formula_string ?? '');
            if ($formula === '') {
                return;
            }

            try {
                app(FormulaResolverService::class)->validateDependencies(
                    $this->user(),
                    $formula,
                    $variable->id,
                    (string) $this->input('code', $variable->code),
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
