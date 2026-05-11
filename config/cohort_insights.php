<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cohort insights (analisi aggregata + notifiche in-app)
    |--------------------------------------------------------------------------
    */
    'enabled' => env('COHORT_INSIGHTS_ENABLED', true),

    'k_min' => (int) env('COHORT_INSIGHTS_K_MIN', 50),

    /** Soglia minima (€, valuta base) di uscite classificate per distribuzione nel periodo. */
    'min_classified_expense_base' => (float) env('COHORT_INSIGHTS_MIN_EXPENSE', 100),

    /** Differenza minima (punti percentuali sulla scala 0–100 a step 5) sopra la mediana del cohort. */
    'median_gap_pct_points' => (int) env('COHORT_INSIGHTS_MEDIAN_GAP', 15),

    'income_bands' => [
        'prefer_not' => 'Preferisco non indicare',
        'under_15k' => 'Fino a circa 15.000 €/anno',
        '15k_25k' => 'Circa 15.000 – 25.000 €/anno',
        '25k_35k' => 'Circa 25.000 – 35.000 €/anno',
        '35k_50k' => 'Circa 35.000 – 50.000 €/anno',
        '50k_70k' => 'Circa 50.000 – 70.000 €/anno',
        '70k_100k' => 'Circa 70.000 – 100.000 €/anno',
        'over_100k' => 'Oltre 100.000 €/anno',
    ],

    'macro_regions' => [
        'prefer_not' => 'Preferisco non indicare',
        'nord_ovest' => 'Nord-Ovest',
        'nord_est' => 'Nord-Est',
        'centro' => 'Centro',
        'sud' => 'Sud',
        'isole' => 'Isole',
        'estero' => 'Estero',
    ],
];
