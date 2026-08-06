<?php

namespace App\Services\CohortInsights;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Log;

/**
 * Converte output Python (codici + parametri whitelisted) in notifiche in-app.
 */
class CohortInsightNotificationWriter
{
    private const ALLOWED_CODES = [
        'cohort_wants_share_above_median',
    ];

    /**
     * @param  list<array{subject_ref: string, insight_code: string, params?: array<string, mixed>}>  $insights
     * @param  array<string, int>  $subjectToUserId
     */
    public function write(string $period, array $insights, array $subjectToUserId): int
    {
        $created = 0;

        foreach ($insights as $insight) {
            if (! is_array($insight)) {
                continue;
            }

            $code = $insight['insight_code'] ?? null;
            $subjectRef = $insight['subject_ref'] ?? null;
            if (! is_string($code) || ! is_string($subjectRef)) {
                continue;
            }

            if (! in_array($code, self::ALLOWED_CODES, true)) {
                Log::warning('cohort-insights — insight_code ignorato', ['code' => $code]);

                continue;
            }

            $userId = $subjectToUserId[$subjectRef] ?? null;
            if (! is_int($userId) && ! is_numeric($userId)) {
                continue;
            }
            $userId = (int) $userId;

            $params = $insight['params'] ?? [];
            if (! is_array($params)) {
                $params = [];
            }

            if ($code === 'cohort_wants_share_above_median') {
                $range = $params['approx_diff_range'] ?? null;
                if (! is_string($range) || ! preg_match('/^\d{1,3}-\d{1,3}$/', $range)) {
                    continue;
                }

                $notificationKey = "cohort_wants_share_{$period}_{$userId}";

                $already = AppNotification::where('user_id', $userId)
                    ->where('notification_key', $notificationKey)
                    ->exists();

                if ($already) {
                    continue;
                }

                AppNotification::create([
                    'user_id' => $userId,
                    'title' => __('cohort_insights.wants_above_median.title'),
                    'message' => $this->buildMessage($range),
                    'read' => false,
                    'notification_key' => $notificationKey,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function buildMessage(string $range): string
    {
        $body = __('cohort_insights.wants_above_median.message', ['range' => $range.' %']);
        $suggestion = __('cohort_insights.wants_above_median.suggestion');

        return trim($body."\n\n".$suggestion);
    }
}
