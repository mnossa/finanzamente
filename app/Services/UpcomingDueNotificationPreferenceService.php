<?php

namespace App\Services;

use App\Models\User;

class UpcomingDueNotificationPreferenceService
{
    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_NEVER = 'never';

    /**
     * @return self::FREQUENCY_*
     */
    public function frequency(User $user): string
    {
        $frequency = data_get($user->preferences, 'notifications.upcoming_due_dates.frequency');

        if (is_string($frequency) && in_array($frequency, [
            self::FREQUENCY_DAILY,
            self::FREQUENCY_WEEKLY,
            self::FREQUENCY_NEVER,
        ], true)) {
            return $frequency;
        }

        $recurringEnabled = (bool) data_get($user->preferences, 'notifications.recurring_reminder.enabled', true);
        $pacEnabled = (bool) data_get($user->preferences, 'notifications.investment_pac_reminder.enabled', true);

        if (! $recurringEnabled && ! $pacEnabled) {
            return self::FREQUENCY_NEVER;
        }

        return self::FREQUENCY_DAILY;
    }

    public function isDaily(User $user): bool
    {
        return $this->frequency($user) === self::FREQUENCY_DAILY;
    }

    public function isWeekly(User $user): bool
    {
        return $this->frequency($user) === self::FREQUENCY_WEEKLY;
    }

    public function isNever(User $user): bool
    {
        return $this->frequency($user) === self::FREQUENCY_NEVER;
    }

    /**
     * @return list<'in_app'|'email'|'push'>
     */
    public function channels(User $user): array
    {
        $channels = data_get($user->preferences, 'notifications.upcoming_due_dates.channels');

        if (is_array($channels) && $channels !== []) {
            return array_values(array_unique(array_filter(
                $channels,
                fn ($channel) => in_array($channel, ['in_app', 'email', 'push'], true),
            )));
        }

        $legacyChannels = array_merge(
            data_get($user->preferences, 'notifications.recurring_reminder.channels', ['in_app', 'email']),
            data_get($user->preferences, 'notifications.investment_pac_reminder.channels', ['in_app', 'email']),
        );

        $normalized = array_values(array_unique(array_filter(
            $legacyChannels,
            fn ($channel) => in_array($channel, ['in_app', 'email', 'push'], true),
        )));

        return $normalized !== [] ? $normalized : ['in_app', 'email'];
    }

    public function allowsChannel(User $user, string $channel): bool
    {
        return in_array($channel, $this->channels($user), true);
    }
}
