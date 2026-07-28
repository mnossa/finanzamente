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

        $this->merge([
            'coupon_frequency' => $frequency === '' ? null : $frequency,
            'next_coupon_date' => $nextDate === '' ? null : $nextDate,
            'coupon_rate_percent' => $rate === '' ? null : $rate,
        ]);
    }

    public function rules(): array
    {
        return [
            'coupon_frequency' => ['nullable', 'string', Rule::in(InvestmentCouponService::FREQUENCIES)],
            'next_coupon_date' => ['nullable', 'date'],
            'coupon_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
