<?php

namespace App\Services\CohortInsights;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Costruisce uno snapshot anonimo (solo numeri aggregati e bucket) per il servizio Python.
 * Nessun testo libero di transazioni o nomi categoria viene incluso.
 */
class CohortInsightSnapshotBuilder
{
    /**
     * @return array{rows: list<array<string, mixed>>, subject_to_user_id: array<string, int>}
     */
    public function buildForPeriod(Carbon $periodStart, Carbon $periodEnd): array
    {
        $subjectToUserId = [];

        $users = User::query()
            ->where('status', 'active')
            ->whereNotNull('income_band')
            ->where('income_band', '!=', 'prefer_not')
            ->whereNotNull('active_household_id')
            ->get(['id', 'active_household_id', 'income_band', 'macro_region']);

        $rows = [];

        foreach ($users as $user) {
            $householdId = (int) $user->active_household_id;
            $userId = (int) $user->id;

            $sums = $this->sumExpenseByDistribution($householdId, $userId, $periodStart, $periodEnd);

            $needs = (float) ($sums['needs'] ?? 0);
            $wants = (float) ($sums['wants'] ?? 0);
            $investments = (float) ($sums['investments'] ?? 0);
            $classified = $needs + $wants + $investments;

            $minTotal = (float) config('cohort_insights.min_classified_expense_base', 100);
            if ($classified < $minTotal) {
                continue;
            }

            $wantsShareRatio = $wants / $classified;
            $wantsSharePctBucket = $this->bucketWantsSharePercent($wantsShareRatio);

            $macroRegion = $user->macro_region;
            if ($macroRegion === 'prefer_not') {
                $macroRegion = null;
            }

            $subjectRef = (string) Str::uuid();
            $subjectToUserId[$subjectRef] = $userId;

            $rows[] = [
                'subject_ref' => $subjectRef,
                'income_band' => (string) $user->income_band,
                'macro_region' => $macroRegion,
                'wants_share_pct_bucket' => $wantsSharePctBucket,
            ];
        }

        return [
            'rows' => $rows,
            'subject_to_user_id' => $subjectToUserId,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function sumExpenseByDistribution(int $householdId, int $userId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $raw = Transaction::query()
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId)
                    ->where('active', true);
            })
            ->where(function ($q) use ($userId) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $userId);
            })
            ->where('amount', '<', 0)
            ->whereNull('transfer_id')
            ->excludeInterHouseholdStats()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.type', 'expense')
            ->whereNotNull('categories.expense_distribution')
            ->selectRaw('categories.expense_distribution as dist, SUM(ABS(transactions.amount_base)) as total')
            ->groupBy('categories.expense_distribution')
            ->pluck('total', 'dist');

        return $raw instanceof Collection ? $raw->map(fn ($v) => (float) $v)->all() : (array) $raw;
    }

    /**
     * Arrotonda la quota Extra (0–1) a multipli di 5% sulla scala 0–100.
     */
    public function bucketWantsSharePercent(float $wantsShareRatio): int
    {
        $pct = $wantsShareRatio * 100.0;
        $bucket = (int) (round($pct / 5.0) * 5);

        return max(0, min(100, $bucket));
    }
}
