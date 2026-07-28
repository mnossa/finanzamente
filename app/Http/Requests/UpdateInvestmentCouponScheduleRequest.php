<?php

namespace App\Http\Requests;

use App\Services\InvestmentCouponService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestmentCouponScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $frequency = $this->input('coupon_frequency');
        $nextDate = $this->input('next_coupon_date');
        $rate = $this->input('coupon_rate_percent');
        $steps = $this->input('coupon_rate_steps');

        if (is_string($steps)) {
            $decoded = json_decode($steps, true);
            $steps = is_array($decoded) ? $decoded : [];
        }

        $this->merge([
            'coupon_frequency' => $frequency === '' ? null : $frequency,
            'next_coupon_date' => $nextDate === '' ? null : $nextDate,
            'coupon_rate_percent' => $rate === '' ? null : $rate,
            'coupon_rate_steps' => is_array($steps) ? array_values($steps) : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'coupon_frequency' => ['nullable', 'string', Rule::in(InvestmentCouponService::FREQUENCIES)],
            'next_coupon_date' => ['nullable', 'date'],
            'coupon_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coupon_rate_steps' => ['nullable', 'array', 'max:40'],
            'coupon_rate_steps.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'coupon_rate_steps.max' => 'Puoi inserire al massimo 40 tassi crescenti.',
            'coupon_rate_steps.*.numeric' => 'Ogni tasso deve essere un numero valido.',
        ];
    }
}
