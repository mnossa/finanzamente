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
        $incomePolicy = $this->input('income_policy');

        if (is_string($steps)) {
            $decoded = json_decode($steps, true);
            $steps = is_array($decoded) ? $decoded : [];
        }

        $normalizedSteps = [];
        if (is_array($steps)) {
            foreach (array_values($steps) as $step) {
                if (is_array($step)) {
                    $normalizedSteps[] = [
                        'from' => ($step['from'] ?? '') === '' ? null : ($step['from'] ?? null),
                        'rate' => ($step['rate'] ?? '') === '' ? null : ($step['rate'] ?? null),
                    ];
                } elseif (is_numeric($step)) {
                    $normalizedSteps[] = [
                        'from' => null,
                        'rate' => $step,
                    ];
                }
            }
        }

        $this->merge([
            'coupon_frequency' => $frequency === '' ? null : $frequency,
            'next_coupon_date' => $nextDate === '' ? null : $nextDate,
            'coupon_rate_percent' => $rate === '' ? null : $rate,
            'coupon_rate_steps' => $normalizedSteps,
            'income_policy' => $incomePolicy === '' ? null : $incomePolicy,
        ]);
    }

    public function rules(): array
    {
        return [
            'coupon_frequency' => ['nullable', 'string', Rule::in(InvestmentCouponService::FREQUENCIES)],
            'next_coupon_date' => ['nullable', 'date'],
            'coupon_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coupon_rate_steps' => ['nullable', 'array', 'max:40'],
            'coupon_rate_steps.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coupon_rate_steps.*.from' => ['nullable', 'date'],
            'income_policy' => ['nullable', 'string', Rule::in(InvestmentCouponService::INCOME_POLICIES)],
        ];
    }

    public function messages(): array
    {
        return [
            'coupon_rate_steps.max' => 'Puoi inserire al massimo 40 tassi crescenti.',
            'coupon_rate_steps.*.rate.numeric' => 'Ogni tasso deve essere un numero valido.',
            'coupon_rate_steps.*.from.date' => 'Ogni data di cambio tasso deve essere valida.',
            'income_policy.in' => 'Seleziona Accumulo o Distribuzione.',
        ];
    }
}
