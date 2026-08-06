<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * One-shot bootstrap for the Formula Widget Platform release.
 *
 * Run automatically once via migration 2026_06_10_100200_backfill_formula_widget_release_bootstrap.
 * Do not add to docker/entrypoint.sh — see docs/DEPLOY.md.
 */
class FormulaWidgetReleaseBootstrapCommand extends Command
{
    protected $signature = 'formula-widgets:release-bootstrap
                            {--user= : Limit dashboard migration to a single user id}';

    protected $description = 'One-shot release task: seed official marketplace templates and migrate dashboard layouts';

    public function handle(): int
    {
        $this->components->info('Formula Widget Platform — release bootstrap (one-shot)');

        $seedExit = $this->call('formula-templates:seed');
        if ($seedExit !== self::SUCCESS) {
            $this->components->error('Template seed failed.');

            return self::FAILURE;
        }

        $migrateOptions = [];
        if ($userId = $this->option('user')) {
            $migrateOptions['--user'] = $userId;
        }

        $migrateExit = $this->call('formula-widgets:migrate-dashboard-layouts', $migrateOptions);
        if ($migrateExit !== self::SUCCESS) {
            $this->components->error('Dashboard layout migration failed.');

            return self::FAILURE;
        }

        $this->components->info('Release bootstrap completed.');

        return self::SUCCESS;
    }
}
