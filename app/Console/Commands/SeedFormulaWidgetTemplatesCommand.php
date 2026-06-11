<?php

namespace App\Console\Commands;

use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Console\Command;

class SeedFormulaWidgetTemplatesCommand extends Command
{
    protected $signature = 'formula-templates:seed';

    protected $description = 'Seed official formula widget marketplace templates';

    public function handle(): int
    {
        $this->call(FormulaWidgetTemplateSeeder::class);
        $this->info('Official formula widget templates seeded.');

        return self::SUCCESS;
    }
}
