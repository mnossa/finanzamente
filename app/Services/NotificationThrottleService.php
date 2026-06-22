<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationThrottleService
{
    public const MAX_UNREAD_SUGGESTIONS = 3;

    /** @var list<string> */
    private const SUGGESTION_PREFIXES = [
        'trend_',
        'cohort_',
        'duplicates_detect_',
        'pac_unlinked_',
        'recurring_due_week_',
    ];

    public function suggestionsEnabled(User $user): bool
    {
        $prefs = data_get($user->preferences, 'notifications.educational_suggestions', ['enabled' => true]);

        return (bool) ($prefs['enabled'] ?? true);
    }

    public function isSuggestionKey(string $notificationKey): bool
    {
        foreach (self::SUGGESTION_PREFIXES as $prefix) {
            if (str_starts_with($notificationKey, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function canCreateSuggestion(User $user, string $notificationKey): bool
    {
        if (! $this->suggestionsEnabled($user)) {
            return false;
        }

        if (! $this->isSuggestionKey($notificationKey)) {
            return true;
        }

        if (AppNotification::where('user_id', $user->id)
            ->where('notification_key', $notificationKey)
            ->exists()) {
            return false;
        }

        $unreadSuggestions = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('read', false)
            ->get()
            ->filter(fn (AppNotification $notification) => $this->isSuggestionKey((string) $notification->notification_key))
            ->count();

        return $unreadSuggestions < self::MAX_UNREAD_SUGGESTIONS;
    }

    public function severityForKey(?string $notificationKey): string
    {
        if ($notificationKey === null) {
            return 'info';
        }

        if (str_starts_with($notificationKey, 'budget_') && str_contains($notificationKey, '_100_')) {
            return 'critical';
        }

        if (str_starts_with($notificationKey, 'budget_')) {
            return 'warning';
        }

        if (str_starts_with($notificationKey, 'investment_pac_remind_')
            || str_starts_with($notificationKey, 'recurring_remind_')) {
            return 'warning';
        }

        if ($this->isSuggestionKey($notificationKey)) {
            return 'info';
        }

        return 'info';
    }
}
