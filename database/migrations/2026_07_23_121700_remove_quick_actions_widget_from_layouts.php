<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-shot: rimuove il widget quick_actions da tutti i layout salvati.
 */
return new class extends Migration
{
    public function up(): void
    {
        $layouts = DB::table('dashboard_layouts')->select(['id', 'config'])->get();

        foreach ($layouts as $layout) {
            $config = is_string($layout->config)
                ? json_decode($layout->config, true)
                : (array) $layout->config;

            if (! is_array($config) || ! isset($config['widgets']) || ! is_array($config['widgets'])) {
                continue;
            }

            $widgets = array_values(array_filter(
                $config['widgets'],
                fn ($entry) => ($entry['id'] ?? '') !== 'quick_actions',
            ));

            if (count($widgets) === count($config['widgets'])) {
                continue;
            }

            foreach ($widgets as $index => $entry) {
                $widgets[$index]['position'] = $index;
            }

            $config['widgets'] = $widgets;

            DB::table('dashboard_layouts')->where('id', $layout->id)->update([
                'config' => json_encode($config),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversibile.
    }
};
