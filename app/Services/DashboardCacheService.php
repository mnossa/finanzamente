<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardCacheService
{
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly DashboardDataVersionService $dashboardDataVersionService,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $builder
     * @return T
     */
    public function rememberIndexPayload(User $user, callable $builder): mixed
    {
        return Cache::remember(
            $this->indexCacheKey($user),
            self::TTL_SECONDS,
            $builder,
        );
    }

    /**
     * @template T
     *
     * @param  list<int>  $widgetIds
     * @param  callable(): T  $builder
     * @return T
     */
    public function rememberFormulaPayloads(User $user, array $widgetIds, callable $builder): mixed
    {
        return Cache::remember(
            $this->formulaCacheKey($user, $widgetIds),
            self::TTL_SECONDS,
            $builder,
        );
    }

    private function indexCacheKey(User $user): string
    {
        $version = $this->dashboardDataVersionService->resolveForUser($user);

        return sprintf(
            'dashboard:%d:%d:%s:index',
            $user->id,
            $user->active_household_id ?? 0,
            $version,
        );
    }

    /**
     * @param  list<int>  $widgetIds
     */
    private function formulaCacheKey(User $user, array $widgetIds): string
    {
        $version = $this->dashboardDataVersionService->resolveForUser($user);
        $sortedIds = $widgetIds;
        sort($sortedIds);

        return sprintf(
            'dashboard:%d:%d:%s:formula:%s',
            $user->id,
            $user->active_household_id ?? 0,
            $version,
            md5(implode(',', $sortedIds)),
        );
    }
}
