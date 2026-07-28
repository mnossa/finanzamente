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
     *     rate_steps: list<float>,
     *     is_step_up: bool
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

        if ($frequency === null || $next === null || ! in_array($frequency, self::FREQUENCIES, true)) {
            return [
                'next_dates' => [],
                'next_items' => [],
                'frequency' => $frequency,
                'rate_percent' => $rate,
                'rate_steps' => $steps,
                'is_step_up' => $isStepUp,
            ];
        }

        $cursor = Carbon::parse($next)->startOfDay();
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'date' => $cursor->toDateString(),
                'rate_percent' => $this->rateForStepIndex($i, $steps, $rate),
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
            $description = 'Cedola - '.$assetName;
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
     *     coupon_rate_steps?: list<float|int|string>|null
     * }  $data
     */
    public function updateSchedule(InvestmentAsset $asset, array $data): void
    {
        $steps = $this->normalizeRateSteps($data['coupon_rate_steps'] ?? null);

        $asset->update([
            'coupon_frequency' => $data['coupon_frequency'] ?? null,
            'next_coupon_date' => $data['next_coupon_date'] ?? null,
            'coupon_rate_percent' => $steps === []
                ? ($data['coupon_rate_percent'] ?? null)
                : null,
            'coupon_rate_steps' => $steps === [] ? null : $steps,
        ]);
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
     * @return list<float>
     */
    public function normalizeRateSteps(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $steps = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (! is_numeric($value)) {
                continue;
            }
            $rate = round((float) $value, 4);
            if ($rate < 0 || $rate > 100) {
                continue;
            }
            $steps[] = $rate;
        }

        return array_values($steps);
    }

    /**
     * @param  list<float>  $steps
     */
    private function rateForStepIndex(int $index, array $steps, ?float $fallback): ?float
    {
        if ($steps === []) {
            return $fallback;
        }

        if (isset($steps[$index])) {
            return $steps[$index];
        }

        return $steps[array_key_last($steps)];
    }
}
