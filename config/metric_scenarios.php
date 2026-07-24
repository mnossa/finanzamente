<?php

/**
 * Scenari metriche wizard (allineati a resources/js/utils/financialVariableScenarios.ts).
 * Usati da ensure/repair e migration one-shot.
 *
 * @return array<string, string> nome visualizzato → formula canonica
 */
return [
    'names' => [
        'Incassato nel periodo' => '[period_income]',
        'Speso nel periodo' => '[period_expenses]',
        'Bilancio conto' => '[period_net]',
        'Risparmio solo se positivo' => 'WHEN([period_net] > 0, [period_net])',
        'Liquidità attuale' => '[household_balance]',
        'Patrimonio netto' => '[patrimonio_total]',
        'Reddito netto' => '[net_income]',
        'Tasso di risparmio' => '[period_net] / [period_income] * 100',
        'Alert spese elevate' => 'MAX([period_expenses], 0)',
        'Versamenti PAC mensili' => '[pac_monthly_total]',
        'Versamenti investimenti nel periodo' => '[investment_purchases]',
    ],

    /**
     * Alias formula legacy → canonica (token non-system o typo).
     *
     * @var array<string, string>
     */
    'formula_aliases' => [
        '[totale_investimenti]' => '[total_investments]',
    ],
];
