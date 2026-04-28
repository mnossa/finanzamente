<?php

namespace Database\Seeders;

use App\Models\MagazineCategory;
use Illuminate\Database\Seeder;

class MagazineCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'risparmio',
                'name' => 'Risparmio',
                'description' => 'Strategie pratiche per risparmiare, costruire un fondo di emergenza e tagliare le spese superflue.',
                'color' => '#10B981',
                'sort_order' => 1,
            ],
            [
                'slug' => 'investimenti',
                'name' => 'Investimenti',
                'description' => 'ETF, azioni, obbligazioni e fondi: come iniziare a investire in modo consapevole.',
                'color' => '#6366F1',
                'sort_order' => 2,
            ],
            [
                'slug' => 'budgeting',
                'name' => 'Budgeting',
                'description' => 'Metodi di budget, tracciamento delle spese e regole pratiche per gestire il denaro al meglio.',
                'color' => '#8B5CF6',
                'sort_order' => 3,
            ],
            [
                'slug' => 'pensione',
                'name' => 'Pensione',
                'description' => 'TFR, fondi pensione, FIRE movement: come pianificare il futuro economico in Italia.',
                'color' => '#F59E0B',
                'sort_order' => 4,
            ],
            // [
            //     'slug'        => 'tasse',
            //     'name'        => 'Tasse',
            //     'description' => 'Detrazioni fiscali, dichiarazione dei redditi e come ottimizzare il carico fiscale nel rispetto della legge italiana.',
            //     'color'       => '#EF4444',
            //     'sort_order'  => 5,
            // ],
            // [
            //     'slug'        => 'conti-e-banche',
            //     'name'        => 'Conti e Banche',
            //     'description' => 'Conti correnti, carte di credito e banche digitali: quale scegliere e come risparmiare sulle commissioni.',
            //     'color'       => '#3B82F6',
            //     'sort_order'  => 6,
            // ],
            [
                'slug' => 'mindset',
                'name' => 'Mindset',
                'description' => 'Psicologia dei soldi, abitudini finanziarie sane e come cambiare il rapporto con il denaro.',
                'color' => '#14B8A6',
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $data) {
            MagazineCategory::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
