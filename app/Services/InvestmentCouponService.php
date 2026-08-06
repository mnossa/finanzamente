<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvestmentCouponService
{
    public const FREQUENCIES = ['annual', 'semi_annual', 'quarterly', 'monthly'];

    public const INCOME_POLICIES = ['accumulating', 'distributing'];

    public function __construct(
        private readonly InvestmentTransactionSyncService $syncService,
    ) {}

    /**
     * @return Collection<int, Transaction>
     */
    public function listForInvestment(Investment $investment): Collection
    {
        return Transaction::query()
            ->where('investment_id', $investment->id)
            ->where('investment_event', 'coupon')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    public function totalCoupons(Investment $investment): float
    {
        return (float) Transaction::query()
            ->where('investment_id', $investment->id)
            ->where('investment_event', 'coupon')
            ->sum('amount');
    }

    /**
     * @return array{
     *     next_dates: list<string>,
     *     next_items: list<array{date: string, rate_percent: float|null}>,
     *     frequency: string|null,
     *     rate_percent: float|null,
     *     rate_steps: list<array{from: string|null, rate: float}>,
     *     is_step_up: bool,
     *     has_dated_steps: bool
     * }
     */
    public function couponSchedulePreview(Investment $investment, int $count = 6): array
    {
        $investment->loadMissing('asset');
        $asset = $investment->asset;
        $frequency = $asset?->coupon_frequency;
        $next = $asset?->next_coupon_date;
        $rate = $asset?->coupon_rate_percent !== null ? (float) $asset->coupon_rate_percent : null;
        $steps = $this->normalizeRateSteps($asset?->coupon_rate_steps);
        $isStepUp = $steps !== [];
        $hasDatedSteps = $isStepUp && collect($steps)->contains(fn (array $step) => $step['from'] !== null);

        if ($frequency === null || $next === null || ! in_array($frequency, self::FREQUENCIES, true)) {
            return [
                'next_dates' => [],
                'next_items' => [],
                'frequency' => $frequency,
                'rate_percent' => $rate,
                'rate_steps' => $steps,
                'is_step_up' => $isStepUp,
                'has_dated_steps' => $hasDatedSteps,
            ];
        }

        $cursor = Carbon::parse($next)->startOfDay();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $paymentDate = $cursor->toDateString();
            $items[] = [
                'date' => $paymentDate,
                'rate_percent' => $this->rateForPayment($paymentDate, $i, $steps, $rate),
            ];
            $cursor = match ($frequency) {
                'monthly' => $cursor->copy()->addMonth(),
                'quarterly' => $cursor->copy()->addMonths(3),
                'semi_annual' => $cursor->copy()->addMonths(6),
                default => $cursor->copy()->addYear(),
            };
        }

        return [
            'next_dates' => array_column($items, 'date'),
            'next_items' => $items,
            'frequency' => $frequency,
            'rate_percent' => $rate,
            'rate_steps' => $steps,
            'is_step_up' => $isStepUp,
            'has_dated_steps' => $hasDatedSteps,
        ];
    }

    /**
     * @param  array{amount: float|int|string, date: string, description?: string|null, account_id?: int|null}  $data
     */
    public function record(Investment $investment, array $data): Transaction
    {
        if ($investment->account_id === null && empty($data['account_id'])) {
            throw ValidationException::withMessages([
                'account_id' => 'Seleziona un conto su cui accreditare la cedola.',
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'L\'importo della cedola deve essere maggiore di zero.',
            ]);
        }

        $investment->loadMissing(['asset', 'account']);
        $accountId = (int) ($data['account_id'] ?? $investment->account_id);
        $category = $this->syncService->resolveCouponCategory($investment->household_id);
        $assetName = $investment->asset?->name ?? 'Asset';
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $label = in_array($investment->asset?->type, ['stock', 'etf'], true) ? 'Dividendo' : 'Cedola';
            $description = $label.' - '.$assetName;
        }

        $account = $investment->account_id === $accountId
            ? $investment->account
            : Account::query()->findOrFail($accountId);

        return Transaction::query()->create([
            'user_id' => $investment->user_id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => $amount,
            'currency_code' => $account->currency_code,
            'date' => $data['date'],
            'description' => mb_substr($description, 0, 1000),
            'investment_id' => $investment->id,
            'investment_event' => 'coupon',
            'is_private' => $investment->is_private,
        ]);
    }

    public function delete(Investment $investment, Transaction $transaction): void
    {
        if ((int) $transaction->investment_id !== (int) $investment->id
            || $transaction->investment_event !== 'coupon') {
            abort(404);
        }

        $transaction->delete();
    }

    /**
     * @param  array{
     *     coupon_frequency?: string|null,
     *     next_coupon_date?: string|null,
     *     coupon_rate_percent?: float|int|string|null,
     *     coupon_rate_steps?: list<mixed>|null,
     *     income_policy?: string|null
     * }  $data
     */
    public function updateSchedule(InvestmentAsset $asset, array $data): void
    {
        $steps = $this->normalizeRateSteps($data['coupon_rate_steps'] ?? null);
        $incomePolicy = $data['income_policy'] ?? null;
        if ($incomePolicy !== null && ! in_array($incomePolicy, self::INCOME_POLICIES, true)) {
            $incomePolicy = null;
        }

        $payload = [
            'coupon_frequency' => $data['coupon_frequency'] ?? null,
            'next_coupon_date' => $data['next_coupon_date'] ?? null,
            'coupon_rate_percent' => $steps === []
                ? ($data['coupon_rate_percent'] ?? null)
                : null,
            'coupon_rate_steps' => $steps === [] ? null : $steps,
        ];

        if (array_key_exists('income_policy', $data)) {
            $payload['income_policy'] = $incomePolicy === '' ? null : $incomePolicy;
        }

        $asset->update($payload);
    }

    public function clearSchedule(InvestmentAsset $asset): void
    {
        $asset->update([
            'coupon_frequency' => null,
            'next_coupon_date' => null,
            'coupon_rate_percent' => null,
            'coupon_rate_steps' => null,
        ]);
    }

    /**
     * Accetta:
     * - legacy: [3.25, 3.5]
     * - dated: [{from: '2025-05-15', rate: 3.25}, ...]
     *
     * @return list<array{from: string|null, rate: float}>
     */
    public function normalizeRateSteps(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $steps = [];
        foreach ($raw as $value) {
            if (is_array($value)) {
                $rateRaw = $value['rate'] ?? $value['coupon_rate_percent'] ?? null;
                $fromRaw = $value['from'] ?? $value['effective_from'] ?? null;
                if ($rateRaw === null || $rateRaw === '' || ! is_numeric($rateRaw)) {
                    continue;
                }
                $rate = round((float) $rateRaw, 4);
                if ($rate < 0 || $rate > 100) {
                    continue;
                }
                $from = null;
                if (is_string($fromRaw) && $fromRaw !== '') {
                    try {
                        $from = Carbon::parse($fromRaw)->toDateString();
                    } catch (\Throwable) {
                        $from = null;
                    }
                }
                $steps[] = ['from' => $from, 'rate' => $rate];

                continue;
            }

            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }
            $rate = round((float) $value, 4);
            if ($rate < 0 || $rate > 100) {
                continue;
            }
            $steps[] = ['from' => null, 'rate' => $rate];
        }

        usort($steps, function (array $a, array $b): int {
            if ($a['from'] === null && $b['from'] === null) {
                return 0;
            }
            if ($a['from'] === null) {
                return 1;
            }
            if ($b['from'] === null) {
                return -1;
            }

            return strcmp($a['from'], $b['from']);
        });

        return array_values($steps);
    }

    /**
     * @param  list<array{from: string|null, rate: float}>  $steps
     */
    private function rateForPayment(string $paymentDate, int $index, array $steps, ?float $fallback): ?float
    {
        if ($steps === []) {
            return $fallback;
        }

        $dated = array_values(array_filter($steps, fn (array $step) => $step['from'] !== null));
        if ($dated !== []) {
            $applicable = null;
            foreach ($dated as $step) {
                if ($step['from'] <= $paymentDate) {
                    $applicable = $step['rate'];
                }
            }

            if ($applicable !== null) {
                return $applicable;
            }

            return $dated[0]['rate'];
        }

        if (isset($steps[$index])) {
            return $steps[$index]['rate'];
        }

        return $steps[array_key_last($steps)]['rate'];
    }
}
