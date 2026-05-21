<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class SimulationController extends Controller
{
    /**
     * Mostra la pagina delle simulazioni finanziarie.
     */
    public function index()
    {
        return Inertia::render('Simulations/PublicIndex', [
            'presetScenarios' => $this->getPresetScenarios(),
            'historicalData' => $this->getHistoricalData(),
            'crisisScenarios' => $this->getCrisisScenarios(),
        ]);
    }

    /**
     * Scenari di portafoglio predefiniti basati su dati storici.
     */
    private function getPresetScenarios(): array
    {
        return [
            [
                'id' => 'conservative',
                'name' => 'Conservativo',
                'return' => 3.0,
                'description' => 'Portafoglio a basso rischio (obbligazioni, liquidità)',
            ],
            [
                'id' => 'moderate',
                'name' => 'Moderato',
                'return' => 6.0,
                'description' => 'Portafoglio bilanciato (60% azioni, 40% obbligazioni)',
            ],
            [
                'id' => 'aggressive',
                'name' => 'Aggressivo',
                'return' => 9.0,
                'description' => 'Portafoglio azionario (simile a S&P 500 storico)',
            ],
        ];
    }

    /**
     * Dati storici di riferimento per le simulazioni.
     */
    private function getHistoricalData(): array
    {
        return [
            'sp500_avg_return' => 10.5,
            'avg_inflation_italy' => 2.1,
            'avg_bond_return' => 3.5,
            'avg_savings_account' => 1.5,
        ];
    }

    /**
     * Scenari di crisi storici per lo stress test.
     * I rendimenti mensili sono approssimazioni basate sull'indice S&P 500.
     */
    private function getCrisisScenarios(): array
    {
        return [
            [
                'id' => 'crisis_2008',
                'name' => 'Crisi Finanziaria 2008',
                'description' => 'La grande crisi finanziaria globale causata dai mutui subprime americani.',
                'peak_drop' => -57,
                'recovery_months' => 49,
                'monthly_returns' => [
                    // 2007
                    1.4, 0.1, 1.0, 4.3, 3.3, -1.8, -3.1, 1.3, 3.6, 1.5, -4.4, -0.9,
                    // 2008
                    -6.1, -3.5, -0.6, 4.8, 1.1, -8.6, -1.0, 1.2, -9.1, -16.9, -7.5, 1.0,
                    // 2009
                    -8.6, -11.0, 8.5, 9.4, 5.3, 0.0, 7.4, 3.4, 3.6, -2.0, 5.7, 1.8,
                    // 2010
                    -3.7, 2.9, 5.9, 1.5, -8.2, -5.4, 6.9, -4.7, 8.8, 3.7, 0.0, 6.5,
                ],
                'labels' => [
                    'Gen 07', 'Feb 07', 'Mar 07', 'Apr 07', 'Mag 07', 'Giu 07',
                    'Lug 07', 'Ago 07', 'Set 07', 'Ott 07', 'Nov 07', 'Dic 07',
                    'Gen 08', 'Feb 08', 'Mar 08', 'Apr 08', 'Mag 08', 'Giu 08',
                    'Lug 08', 'Ago 08', 'Set 08', 'Ott 08', 'Nov 08', 'Dic 08',
                    'Gen 09', 'Feb 09', 'Mar 09', 'Apr 09', 'Mag 09', 'Giu 09',
                    'Lug 09', 'Ago 09', 'Set 09', 'Ott 09', 'Nov 09', 'Dic 09',
                    'Gen 10', 'Feb 10', 'Mar 10', 'Apr 10', 'Mag 10', 'Giu 10',
                    'Lug 10', 'Ago 10', 'Set 10', 'Ott 10', 'Nov 10', 'Dic 10',
                ],
            ],
            [
                'id' => 'covid_2020',
                'name' => 'Pandemia COVID-19',
                'description' => 'Il rapido crollo e rimbalzo dei mercati azionari durante la pandemia.',
                'peak_drop' => -34,
                'recovery_months' => 5,
                'monthly_returns' => [
                    // 2020
                    5.0, -8.4, -12.5, 12.7, 4.8, 1.8, 5.5, 7.2, -3.9, -2.8, 10.8, 3.7,
                    // 2021
                    -1.1, 2.8, 4.2, 5.3, 0.6, 2.2, 2.4, 3.0, -4.8, 7.0, -0.8, 4.5,
                ],
                'labels' => [
                    'Gen 20', 'Feb 20', 'Mar 20', 'Apr 20', 'Mag 20', 'Giu 20',
                    'Lug 20', 'Ago 20', 'Set 20', 'Ott 20', 'Nov 20', 'Dic 20',
                    'Gen 21', 'Feb 21', 'Mar 21', 'Apr 21', 'Mag 21', 'Giu 21',
                    'Lug 21', 'Ago 21', 'Set 21', 'Ott 21', 'Nov 21', 'Dic 21',
                ],
            ],
            [
                'id' => 'dot_com',
                'name' => 'Bolla Dot-Com',
                'description' => 'Il crollo delle aziende tecnologiche dopo la bolla speculativa degli anni 2000.',
                'peak_drop' => -49,
                'recovery_months' => 85,
                'monthly_returns' => [
                    // 1999
                    5.2, -2.0, 9.7, -3.1, -2.2, 2.4, -1.6, 6.2, -3.3, 6.3, 2.0, 5.8,
                    // 2000
                    -5.1, -2.0, 9.7, -3.1, -2.1, 2.4, -1.6, 6.2, -3.3, -0.5, -8.0, 0.5,
                    // 2001
                    3.5, -9.2, -6.4, 7.7, 0.7, -2.5, -1.1, -6.4, -8.2, 1.8, 7.5, 0.8,
                    // 2002
                    -1.6, -2.1, 3.7, -6.1, -0.9, -7.2, -7.9, 0.5, -11.0, 8.6, 5.7, -6.0,
                    // 2003
                    -2.7, -1.7, 0.8, 8.1, 5.1, 1.1, 1.6, 1.8, -1.2, 5.5, 0.7, 5.1,
                ],
                'labels' => [
                    'Gen 99', 'Feb 99', 'Mar 99', 'Apr 99', 'Mag 99', 'Giu 99',
                    'Lug 99', 'Ago 99', 'Set 99', 'Ott 99', 'Nov 99', 'Dic 99',
                    'Gen 00', 'Feb 00', 'Mar 00', 'Apr 00', 'Mag 00', 'Giu 00',
                    'Lug 00', 'Ago 00', 'Set 00', 'Ott 00', 'Nov 00', 'Dic 00',
                    'Gen 01', 'Feb 01', 'Mar 01', 'Apr 01', 'Mag 01', 'Giu 01',
                    'Lug 01', 'Ago 01', 'Set 01', 'Ott 01', 'Nov 01', 'Dic 01',
                    'Gen 02', 'Feb 02', 'Mar 02', 'Apr 02', 'Mag 02', 'Giu 02',
                    'Lug 02', 'Ago 02', 'Set 02', 'Ott 02', 'Nov 02', 'Dic 02',
                    'Gen 03', 'Feb 03', 'Mar 03', 'Apr 03', 'Mag 03', 'Giu 03',
                    'Lug 03', 'Ago 03', 'Set 03', 'Ott 03', 'Nov 03', 'Dic 03',
                ],
            ],
        ];
    }
}
