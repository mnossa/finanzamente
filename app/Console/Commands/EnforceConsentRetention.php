<?php

namespace App\Console\Commands;

use App\Models\ConsentEvent;
use App\Models\RetentionPolicy;
use Illuminate\Console\Command;

class EnforceConsentRetention extends Command
{
    protected $signature = 'consents:enforce-retention {--dry-run : Mostra conteggi senza applicare modifiche}';

    protected $description = 'Applica policy di retention ai consent_events (anonymize + prune)';

    public function handle(): int
    {
        $policy = RetentionPolicy::query()
            ->where('policy_key', 'consent_events_default')
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $policy) {
            $this->warn('Nessuna retention policy attiva per consent_events_default.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $anonymized = 0;
        if ($policy->anonymize_after_days !== null) {
            $anonymizeBefore = $now->copy()->subDays($policy->anonymize_after_days);
            $toAnonymize = ConsentEvent::query()
                ->where('occurred_at', '<', $anonymizeBefore)
                ->where(function ($query) {
                    $query->whereNotNull('ip_hash')->orWhereNotNull('user_agent_hash');
                });

            $anonymized = (clone $toAnonymize)->count();
            if (! $dryRun && $anonymized > 0) {
                $toAnonymize->update([
                    'ip_hash' => null,
                    'user_agent_hash' => null,
                ]);
            }
        }

        $deleteBefore = $now->copy()->subDays($policy->retention_days);
        $toDelete = ConsentEvent::query()->where('occurred_at', '<', $deleteBefore);
        $deleted = (clone $toDelete)->count();
        if (! $dryRun && $deleted > 0) {
            $toDelete->delete();
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.'Anonymized events: '.$anonymized);
        $this->info($prefix.'Deleted events: '.$deleted);

        return self::SUCCESS;
    }
}
