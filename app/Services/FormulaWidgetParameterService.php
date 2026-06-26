<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FormulaWidgetParameterService
{
    public const ACCOUNT_ALL = 'all';

    public const PERIOD_NAV_TYPE = 'period_nav';

    public const PERIOD_NAV_MIN_OFFSET = -36;

    public const PERIOD_NAV_MAX_OFFSET = 0;

    /** @var list<string> */
    public const SUPPORTED_TYPES = ['account', self::PERIOD_NAV_TYPE];

    /**
     * @param  array<string, mixed>|null  $chartConfig
     * @param  array<string, string|int|null>  $runtimeOverrides
     * @return array<string, string>
     */
    public function resolveValues(User $user, ?array $chartConfig, array $runtimeOverrides = []): array
    {
        $definitions = $this->parameterDefinitions($chartConfig);
        $resolved = [];

        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $raw = $runtimeOverrides[$key] ?? $this->defaultValue($definition);
            $resolved[$key] = $this->normalizeValue($user, $definition, $raw);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>|null  $chartConfig
     * @param  array<string, string>  $resolvedValues
     * @return list<array{key: string, type: string, label: string, value: string, options: list<array{value: string, label: string}>, min?: int, max?: int}>
     */
    public function buildPayloadMetadata(User $user, ?array $chartConfig, array $resolvedValues): array
    {
        $metadata = [];

        foreach ($this->parameterDefinitions($chartConfig) as $definition) {
            $key = $definition['key'];
            $entry = [
                'key' => $key,
                'type' => $definition['type'],
                'label' => $definition['label'],
                'value' => $resolvedValues[$key] ?? $this->defaultValue($definition),
                'options' => $this->buildOptions($user, $definition),
            ];

            if ($definition['type'] === self::PERIOD_NAV_TYPE) {
                $entry['min'] = self::PERIOD_NAV_MIN_OFFSET;
                $entry['max'] = self::PERIOD_NAV_MAX_OFFSET;
            }

            $metadata[] = $entry;
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>|null  $chartConfig
     * @return list<array{key: string, type: string, label: string, default?: string}>
     */
    public function parameterDefinitions(?array $chartConfig): array
    {
        $parameters = $chartConfig['parameters'] ?? [];

        if (! is_array($parameters)) {
            return [];
        }

        $definitions = [];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            $key = $parameter['key'] ?? null;
            $type = $parameter['type'] ?? null;
            $label = $parameter['label'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($type) || ! is_string($label)) {
                continue;
            }

            if (! in_array($type, self::SUPPORTED_TYPES, true)) {
                continue;
            }

            $definition = [
                'key' => $key,
                'type' => $type,
                'label' => $label,
            ];

            if (isset($parameter['default'])) {
                $definition['default'] = (string) $parameter['default'];
            }

            $definitions[] = $definition;
        }

        return $definitions;
    }

    /**
     * @param  array{key: string, type: string, label: string, default?: string}  $definition
     */
    private function defaultValue(array $definition): string
    {
        if (isset($definition['default'])) {
            return $definition['default'];
        }

        return $definition['type'] === self::PERIOD_NAV_TYPE
            ? (string) self::PERIOD_NAV_MAX_OFFSET
            : self::ACCOUNT_ALL;
    }

    /**
     * @param  array<string, mixed>|null  $chartConfig
     */
    public function validateChartConfig(?array $chartConfig, bool $isPublic = false): void
    {
        foreach ($this->parameterDefinitions($chartConfig) as $definition) {
            if ($isPublic && $definition['type'] === 'account') {
                throw ValidationException::withMessages([
                    'chart_config' => 'I widget pubblici non possono includere filtri per conto specifico.',
                ]);
            }
        }
    }

    /**
     * @param  array{key: string, type: string, label: string, default?: string}  $definition
     * @return list<array{value: string, label: string}>
     */
    private function buildOptions(User $user, array $definition): array
    {
        return match ($definition['type']) {
            'account' => $this->accountOptions($user),
            default => [],
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function accountOptions(User $user): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [
                ['value' => self::ACCOUNT_ALL, 'label' => 'Tutti i conti'],
            ];
        }

        $accounts = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $options = [
            ['value' => self::ACCOUNT_ALL, 'label' => 'Tutti i conti'],
        ];

        foreach ($accounts as $account) {
            $options[] = [
                'value' => (string) $account->id,
                'label' => $account->name,
            ];
        }

        return $options;
    }

    /**
     * @param  array{key: string, type: string, label: string, default?: string}  $definition
     */
    private function normalizeValue(User $user, array $definition, mixed $raw): string
    {
        $value = (string) $raw;

        if ($definition['type'] === self::PERIOD_NAV_TYPE) {
            $offset = is_numeric($value) ? (int) $value : 0;
            $offset = max(self::PERIOD_NAV_MIN_OFFSET, min(self::PERIOD_NAV_MAX_OFFSET, $offset));

            return (string) $offset;
        }

        if ($definition['type'] === 'account') {
            if ($value === self::ACCOUNT_ALL || $value === '') {
                return self::ACCOUNT_ALL;
            }

            if (! ctype_digit($value)) {
                return (string) ($definition['default'] ?? self::ACCOUNT_ALL);
            }

            $accountId = (int) $value;
            $allowed = collect($this->accountOptions($user))
                ->pluck('value')
                ->contains((string) $accountId);

            if (! $allowed) {
                return (string) ($definition['default'] ?? self::ACCOUNT_ALL);
            }

            return (string) $accountId;
        }

        return $value;
    }
}
