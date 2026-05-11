<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['income_band', 'macro_region'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'default_currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'income_band' => ['nullable', 'string', Rule::in(array_keys(config('cohort_insights.income_bands', [])))],
            'macro_region' => ['nullable', 'string', Rule::in(array_keys(config('cohort_insights.macro_regions', [])))],
        ];
    }
}
