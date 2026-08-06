<?php

return [
    [
        'template_slug' => 'official.saldo_liquidita',
        'name' => 'Liquidità attuale',
        'display_type' => 'kpi',
        'period_preset' => null,
        'default_size' => 'xl',
        'chart_config' => ['format' => 'currency', 'variant' => 'balance_summary'],
        'variable' => [
            'code' => 'saldo_liquidita',
            'name' => 'Liquidità attuale',
            'type' => 'formula',
            'formula_string' => '[household_balance]',
        ],
    ],
    [
        'template_slug' => 'official.totale_investimenti',
        'name' => 'Totale investimenti',
        'display_type' => 'kpi',
        'period_preset' => null,
        'default_size' => 'lg',
        'chart_config' => ['format' => 'currency'],
        'variable' => [
            'code' => 'totale_investimenti',
            'name' => 'Totale investimenti',
            'type' => 'formula',
            'formula_string' => '[total_investments]',
        ],
    ],
    [
        'template_slug' => 'official.patrimonio_netto',
        'name' => 'Patrimonio netto',
        'display_type' => 'kpi',
        'period_preset' => null,
        'default_size' => 'lg',
        'chart_config' => ['format' => 'currency'],
        'variable' => [
            'code' => 'patrimonio_netto',
            'name' => 'Patrimonio netto',
            'type' => 'formula',
            'formula_string' => '[patrimonio_total]',
        ],
    ],
    [
        'template_slug' => 'official.entrate_30gg',
        'name' => 'Entrate (30 gg)',
        'display_type' => 'kpi',
        'period_preset' => 'rolling_30',
        'default_size' => 'md',
        'chart_config' => [
            'show_delta' => true,
            'format' => 'currency',
            'delta_polarity' => 'higher_is_better',
        ],
        'variable' => [
            'code' => 'entrate_30gg',
            'name' => 'Entrate 30 giorni',
            'type' => 'formula',
            'formula_string' => '[period_income]',
        ],
    ],
    [
        'template_slug' => 'official.uscite_30gg',
        'name' => 'Uscite (30 gg)',
        'display_type' => 'kpi',
        'period_preset' => 'rolling_30',
        'default_size' => 'md',
        'chart_config' => [
            'show_delta' => true,
            'format' => 'currency',
            'delta_polarity' => 'lower_is_better',
        ],
        'variable' => [
            'code' => 'uscite_30gg',
            'name' => 'Uscite 30 giorni',
            'type' => 'formula',
            'formula_string' => '[period_expenses]',
        ],
    ],
    [
        'template_slug' => 'official.investimenti_vs_liquidita',
        'name' => 'Investimenti vs liquidità',
        'display_type' => 'bar',
        'period_preset' => null,
        'default_size' => 'md',
        'chart_config' => [
            'series' => [
                ['code' => 'total_investments', 'label' => 'Investimenti', 'color' => '#6366f1'],
                ['code' => 'household_balance', 'label' => 'Liquidità', 'color' => '#10b981'],
            ],
        ],
        'variable' => [
            'code' => 'investimenti_vs_liquidita',
            'name' => 'Investimenti vs liquidità',
            'type' => 'formula',
            'formula_string' => '[total_investments]',
        ],
    ],
    [
        'template_slug' => 'official.perc_investito',
        'name' => '% investito sul patrimonio',
        'display_type' => 'kpi',
        'period_preset' => null,
        'default_size' => 'md',
        'chart_config' => ['format' => 'percent'],
        'variable' => [
            'code' => 'perc_investito',
            'name' => 'Percentuale investita',
            'type' => 'formula',
            'formula_string' => '([total_investments] / [patrimonio_total]) * 100',
        ],
    ],
    [
        'template_slug' => 'official.cashflow_mensile',
        'name' => 'Panoramica cashflow',
        'display_type' => 'stacked_bar',
        'period_preset' => 'full_history',
        'default_size' => 'md',
        'chart_config' => [
            'series' => [
                ['code' => 'period_income', 'label' => 'Entrate', 'color' => '#10b981'],
                ['code' => 'period_expenses', 'label' => 'Uscite', 'color' => '#f97316'],
                ['code' => 'period_net', 'label' => 'Risparmio', 'color' => '#3b82f6'],
            ],
        ],
        'variable' => [
            'code' => 'cashflow_mensile',
            'name' => 'Cashflow mensile',
            'type' => 'formula',
            'formula_string' => '[period_net]',
        ],
    ],
    [
        'template_slug' => 'official.patrimonio_nel_tempo',
        'name' => 'Patrimonio nel tempo',
        'display_type' => 'area',
        'period_preset' => 'full_history',
        'default_size' => 'md',
        'chart_config' => [
            'series' => [
                ['code' => 'patrimonio_total', 'label' => 'Patrimonio', 'color' => '#3b82f6'],
            ],
        ],
        'variable' => [
            'code' => 'patrimonio_nel_tempo',
            'name' => 'Patrimonio nel tempo',
            'type' => 'formula',
            'formula_string' => '[patrimonio_total]',
        ],
    ],
    [
        'template_slug' => 'official.tasso_risparmio',
        'name' => 'Tasso di risparmio',
        'display_type' => 'kpi',
        'period_preset' => 'rolling_30',
        'default_size' => 'md',
        'chart_config' => ['format' => 'percent'],
        'variable' => [
            'code' => 'tasso_risparmio',
            'name' => 'Tasso di risparmio',
            'type' => 'formula',
            'formula_string' => '(([period_income] - [period_expenses]) / [period_income]) * 100',
        ],
    ],
    [
        'template_slug' => 'official.ultime_transazioni',
        'name' => 'Ultime transazioni',
        'display_type' => 'table',
        'period_preset' => 'current_month',
        'default_size' => 'lg',
        'chart_config' => [
            'metric_query' => [
                'datasource' => 'transactions',
                'measure' => 'count',
                'amount_field' => 'amount_base',
                'filters' => [],
            ],
            'table' => [
                'mode' => 'rows',
                'row_limit' => 10,
                'sort' => ['field' => 'date', 'direction' => 'desc'],
            ],
        ],
        'variable' => [
            'code' => 'table_tx_placeholder',
            'name' => 'Placeholder tabella transazioni',
            'type' => 'formula',
            'formula_string' => '[period_net]',
        ],
    ],
    [
        'template_slug' => 'official.pac_attivi',
        'name' => 'PAC attivi',
        'display_type' => 'table',
        'period_preset' => null,
        'default_size' => 'md',
        'chart_config' => [
            'metric_query' => [
                'datasource' => 'investment_pacs',
                'measure' => 'count',
                'filters' => [
                    ['field' => 'status', 'operator' => 'eq', 'value' => 'active'],
                ],
            ],
            'table' => [
                'mode' => 'rows',
                'row_limit' => 10,
                'sort' => ['field' => 'start_date', 'direction' => 'desc'],
            ],
        ],
        'variable' => [
            'code' => 'table_pac_placeholder',
            'name' => 'Placeholder tabella PAC',
            'type' => 'formula',
            'formula_string' => '[total_investments]',
        ],
    ],
    [
        'template_slug' => 'official.spese_per_categoria',
        'name' => 'Spese per categoria',
        'display_type' => 'table',
        'period_preset' => 'current_month',
        'default_size' => 'lg',
        'chart_config' => [
            'metric_query' => [
                'datasource' => 'transactions',
                'measure' => 'sum_abs',
                'amount_field' => 'amount_base',
                'filters' => [
                    ['field' => 'transaction_type', 'operator' => 'eq', 'value' => 'expense'],
                ],
            ],
            'table' => [
                'mode' => 'aggregate',
                'group_by' => 'category',
                'row_limit' => 15,
            ],
        ],
        'variable' => [
            'code' => 'table_cat_placeholder',
            'name' => 'Placeholder tabella categorie',
            'type' => 'formula',
            'formula_string' => '[period_expenses]',
        ],
    ],
];
