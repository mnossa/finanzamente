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
    public function rememberIndexPayload(User $user, callable $builder, ?int $boardId = null): mixed
    {
        return Cache::remember(
            $this->indexCacheKey($user, $boardId),
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
    public function rememberFormulaPayloads(
        User $user,
        array $widgetIds,
        callable $builder,
        string $runtimeParamsKey = '',
    ): mixed {
        return Cache::remember(
            $this->formulaCacheKey($user, $widgetIds, $runtimeParamsKey),
            self::TTL_SECONDS,
            $builder,
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $builder
     * @return T
     */
    public function rememberDeferredWidgets(User $user, callable $builder): mixed
    {
        return Cache::remember(
            $this->deferredWidgetsCacheKey($user),
            self::TTL_SECONDS,
            $builder,
        );
    }

    private function indexCacheKey(User $user, ?int $boardId = null): string
    {
        $version = $this->dashboardDataVersionService->resolveForUser($user);

        return sprintf(
            'dashboard:%d:%d:%s:index:b%d',
            $user->id,
            $user->active_household_id ?? 0,
            $version,
            $boardId ?? 0,
        );
    }

    /**
     * @param  list<int>  $widgetIds
     */
    private function formulaCacheKey(User $user, array $widgetIds, string $runtimeParamsKey = ''): string
    {
        $version = $this->dashboardDataVersionService->resolveForUser($user);
        $sortedIds = $widgetIds;
        sort($sortedIds);

        $paramsSuffix = $runtimeParamsKey !== '' ? ':'.md5($runtimeParamsKey) : '';

        return sprintf(
            'dashboard:%d:%d:%s:formula:%s%s',
            $user->id,
            $user->active_household_id ?? 0,
            $version,
            md5(implode(',', $sortedIds)),
            $paramsSuffix,
        );
    }

    private function deferredWidgetsCacheKey(User $user): string
    {
        $version = $this->dashboardDataVersionService->resolveForUser($user);

        return sprintf(
            'dashboard:%d:%d:%s:deferred',
            $user->id,
            $user->active_household_id ?? 0,
            $version,
        );
    }
}
