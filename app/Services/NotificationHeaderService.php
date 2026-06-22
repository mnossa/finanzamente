<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationHeaderService
{
    public function __construct(
        private readonly NotificationThrottleService $notificationThrottleService,
    ) {}

    /**
     * @return array{unread_count: int, items: list<array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        return [
            'unread_count' => AppNotification::where('user_id', $user->id)->where('read', false)->count(),
            'items' => AppNotification::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'read' => $notification->read,
                    'action_url' => $this->resolveNotificationActionUrl($notification->notification_key),
                    'severity' => $this->notificationThrottleService->severityForKey($notification->notification_key),
                    'created_at' => $notification->created_at->diffForHumans(),
                ])
                ->toArray(),
        ];
    }

    private function resolveNotificationActionUrl(?string $notificationKey): ?string
    {
        if (! $notificationKey) {
            return null;
        }

        if (str_starts_with($notificationKey, 'recurring_detect_')) {
            return route('recurrence-detection.index');
        }

        if (str_starts_with($notificationKey, 'recurring_remind_')) {
            $parts = explode('_', $notificationKey);
            $recurringId = $parts[2] ?? null;
            if ($recurringId && is_numeric($recurringId)) {
                return route('recurring-transactions.show', (int) $recurringId);
            }
        }

        if (str_starts_with($notificationKey, 'recurring_sync_')) {
            $parts = explode('_', $notificationKey);
            $recurringId = $parts[2] ?? null;
            if ($recurringId && is_numeric($recurringId)) {
                return route('recurring-transactions.show', (int) $recurringId);
            }
        }

        if (str_starts_with($notificationKey, 'inbox_telegram_')) {
            return route('inbox.index');
        }

        if (str_starts_with($notificationKey, 'cohort_wants_share_')) {
            return route('dashboard');
        }

        if (str_starts_with($notificationKey, 'duplicates_detect_')) {
            return route('transactions.duplicates.index');
        }

        if (str_starts_with($notificationKey, 'monthly_spending_')) {
            $parts = explode('_', $notificationKey);
            $yearMonth = $parts[2] ?? null;
            if ($yearMonth && preg_match('/^\d{4}-\d{2}$/', $yearMonth)) {
                return route('transactions.index', [
                    'type' => 'expense',
                    'from' => $yearMonth.'-01',
                    'to' => date('Y-m-t', strtotime($yearMonth.'-01')),
                ]);
            }
        }

        if (str_starts_with($notificationKey, 'investment_pac_remind_')) {
            $parts = explode('_', $notificationKey);
            $pacId = $parts[3] ?? null;
            if ($pacId && is_numeric($pacId)) {
                return route('investment-pacs.show', (int) $pacId);
            }
        }

        if (str_starts_with($notificationKey, 'pac_unlinked_')) {
            $parts = explode('_', $notificationKey);
            $pacId = $parts[2] ?? null;
            if ($pacId && is_numeric($pacId)) {
                return route('investment-pacs.edit', (int) $pacId);
            }
        }

        if (str_starts_with($notificationKey, 'recurring_due_week_')) {
            return route('transactions.index');
        }

        return null;
    }
}
