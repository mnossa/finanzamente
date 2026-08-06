<?php

namespace App\Http\Requests;

use App\Models\FinancialVariable;
use App\Services\FinancialVariableLibraryService;
use App\Services\FormulaResolverService;
use App\Support\FormulaTokenParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnsureFinancialVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
            ],
            'type' => ['required', Rule::in([FinancialVariable::TYPE_STATIC, FinancialVariable::TYPE_FORMULA])],
            'static_value' => ['nullable', 'numeric', 'required_if:type,static'],
            'formula_string' => ['nullable', 'string', 'max:2000', 'required_if:type,formula'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome della metrica è obbligatorio.',
            'type.required' => 'Seleziona il tipo di metrica.',
            'formula_string.required_if' => 'Inserisci una formula valida.',
            'static_value.required_if' => 'Inserisci un valore statico.',
            'code.regex' => 'Il codice può contenere solo lettere minuscole, numeri e underscore.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('code')) {
            try {
                $this->merge([
                    'code' => app(FormulaTokenParser::class)->sanitizeCode($this->input('name')),
                ]);
            } catch (ValidationException) {
                // Lascia code vuoto: uniqueCodeForUser userà un fallback.
            }
        }

        if ($this->input('type') === FinancialVariable::TYPE_FORMULA && $this->filled('formula_string')) {
            $aliased = app(FinancialVariableLibraryService::class)
                ->applyFormulaAlias((string) $this->input('formula_string'));

            if ($aliased !== (string) $this->input('formula_string')) {
                $this->merge(['formula_string' => $aliased]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('type') !== FinancialVariable::TYPE_FORMULA) {
                return;
            }

            $formula = (string) $this->input('formula_string', '');
            if ($formula === '') {
                return;
            }

            try {
                app(FormulaResolverService::class)->validateDependencies(
                    $this->user(),
                    $formula,
                    null,
                    (string) $this->input('code', ''),
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
