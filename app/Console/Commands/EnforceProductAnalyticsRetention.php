<?php

namespace App\Console\Commands;

use App\Models\ProductAnalyticsDaily;
use App\Models\RetentionPolicy;
use Illuminate\Console\Command;

class EnforceProductAnalyticsRetention extends Command
{
    protected $signature = 'product-analytics:enforce-retention {--dry-run : Mostra conteggi senza applicare modifiche}';

    protected $description = 'Applica retention agli aggregati product analytics (purge sole)';

    public function handle(): int
    {
        $policyKey = (string) config('product_analytics.retention_policy_key', 'product_analytics_daily');

        $policy = RetentionPolicy::query()
            ->where('policy_key', $policyKey)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $policy) {
            $this->warn('Nessuna retention policy attiva per '.$policyKey.'.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $deleteBefore = now()->subDays($policy->retention_days)->toDateString();

        $toDelete = ProductAnalyticsDaily::query()->where('day', '<', $deleteBefore);
        $deleted = (clone $toDelete)->count();

        if (! $dryRun && $deleted > 0) {
            $toDelete->delete();
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.'Deleted daily aggregates: '.$deleted);

        return self::SUCCESS;
    }
}
