<?php

return [
    'max_series_months' => 24,
    'max_formula_depth' => 3,
    'max_formula_tokens' => 20,
    'system_user_email' => 'formula-templates@system.internal',

    'period_presets' => [
        'current_month' => [
            'label' => 'Mese corrente',
            'previous_period_label' => 'mese precedente',
        ],
        'rolling_30' => [
            'label' => 'Ultimi 30 giorni',
            'previous_period_label' => '30 giorni precedenti',
        ],
        'full_history' => [
            'label' => 'Storico completo',
        ],
        'calendar_ytd' => [
            'label' => 'Anno corrente',
        ],
    ],

    'system_variables' => [
        'total_income' => [
            'label' => 'Entrate totali',
            'requires_period' => true,
            'resolver' => 'financial_metrics',
            'field' => 'gross_income',
        ],
        'total_expenses' => [
            'label' => 'Uscite totali',
            'requires_period' => true,
            'resolver' => 'financial_metrics',
            'field' => 'total_expenses',
        ],
        'net_income' => [
            'label' => 'Reddito netto',
            'requires_period' => true,
            'resolver' => 'financial_metrics',
            'field' => 'net_income',
        ],
        'effective_expenses' => [
            'label' => 'Spese effettive',
            'requires_period' => true,
            'resolver' => 'financial_metrics',
            'field' => 'effective_expenses',
        ],
        'lifestyle_score' => [
            'label' => 'Lifestyle Inflation Score',
            'requires_period' => true,
            'resolver' => 'financial_metrics',
            'field' => 'lifestyle_score',
        ],
        'household_balance' => [
            'label' => 'Liquidità attuale',
            'requires_period' => false,
            'resolver' => 'household_balance',
        ],
        'total_investments' => [
            'label' => 'Totale investimenti',
            'requires_period' => false,
            'resolver' => 'portfolio_snapshot',
            'field' => 'investedValue',
        ],
        'investments_linked' => [
            'label' => 'Investimenti collegati al ledger',
            'requires_period' => false,
            'resolver' => 'portfolio_snapshot',
            'field' => 'investedLinkedValue',
        ],
        'investments_unlinked' => [
            'label' => 'Investimenti non collegati',
            'requires_period' => false,
            'resolver' => 'portfolio_snapshot',
            'field' => 'investedUnlinkedValue',
        ],
        'patrimonio_total' => [
            'label' => 'Patrimonio netto',
            'requires_period' => false,
            'resolver' => 'portfolio_snapshot',
            'field' => 'totalValue',
        ],
        'investment_purchases' => [
            'label' => 'Versamenti investimenti nel periodo',
            'requires_period' => true,
            'resolver' => 'investment_purchases',
        ],
        'period_income' => [
            'label' => 'Entrate periodo',
            'requires_period' => true,
            'resolver' => 'period_stats',
            'field' => 'income',
        ],
        'period_expenses' => [
            'label' => 'Uscite periodo',
            'requires_period' => true,
            'resolver' => 'period_stats',
            'field' => 'expenses',
        ],
        'period_net' => [
            'label' => 'Bilancio conto (saldo periodo)',
            'requires_period' => true,
            'resolver' => 'period_stats',
            'field' => 'net',
        ],
        'expense_needs' => [
            'label' => 'Spese necessità',
            'requires_period' => true,
            'resolver' => 'expense_distribution',
            'field' => 'needs',
        ],
        'expense_wants' => [
            'label' => 'Spese extra',
            'requires_period' => true,
            'resolver' => 'expense_distribution',
            'field' => 'wants',
        ],
        'expense_investments' => [
            'label' => 'Spese investimenti',
            'requires_period' => true,
            'resolver' => 'expense_distribution',
            'field' => 'investments',
        ],
        'pac_monthly_total' => [
            'label' => 'Versamenti mensili PAC attivi',
            'requires_period' => false,
            'resolver' => 'investment_pac_metrics',
            'field' => 'monthly_total',
        ],
        'pac_ytd_contributions' => [
            'label' => 'Versamenti PAC da inizio anno',
            'requires_period' => false,
            'resolver' => 'investment_pac_metrics',
            'field' => 'ytd_contributions',
        ],
        'pac_projected_contributions' => [
            'label' => 'Versamenti PAC proiettati (12 mesi)',
            'requires_period' => false,
            'resolver' => 'investment_pac_metrics',
            'field' => 'projected_contributions',
        ],
        'pac_projected_patrimonio' => [
            'label' => 'Patrimonio PAC proiettato (12 mesi)',
            'requires_period' => false,
            'resolver' => 'investment_pac_metrics',
            'field' => 'projected_patrimonio',
        ],
        'pac_active_count' => [
            'label' => 'Numero PAC attivi',
            'requires_period' => false,
            'resolver' => 'investment_pac_metrics',
            'field' => 'active_count',
        ],
    ],

    /**
     * Variabili di contesto (data, calendario, durata periodo).
     * Valori calcolati rispetto alla fine del periodo del widget (o al mese nel grafico mensile).
     */
    'context_variables' => [
        'current_year' => [
            'label' => 'Anno (fine periodo)',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'year',
        ],
        'current_month' => [
            'label' => 'Mese (1–12)',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'month',
        ],
        'current_day' => [
            'label' => 'Giorno del mese',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'day',
        ],
        'day_of_year' => [
            'label' => 'Giorno dell\'anno',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'day_of_year',
        ],
        'quarter' => [
            'label' => 'Trimestre (1–4)',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'quarter',
        ],
        'week_of_year' => [
            'label' => 'Settimana dell\'anno (ISO)',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'iso_week',
        ],
        'days_in_month' => [
            'label' => 'Giorni nel mese',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'days_in_month',
        ],
        'days_in_year' => [
            'label' => 'Giorni nell\'anno',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'days_in_year',
        ],
        'days_elapsed_in_month' => [
            'label' => 'Giorni trascorsi nel mese',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'days_elapsed_in_month',
        ],
        'days_remaining_in_month' => [
            'label' => 'Giorni rimanenti nel mese',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'days_remaining_in_month',
        ],
        'days_elapsed_in_year' => [
            'label' => 'Giorni trascorsi nell\'anno',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'days_elapsed_in_year',
        ],
        'days_remaining_in_year' => [
            'label' => 'Giorni rimanenti nell\'anno',
            'category' => 'context',
            'requires_period' => false,
            'resolver' => 'context',
            'field' => 'days_remaining_in_year',
        ],
        'days_in_period' => [
            'label' => 'Giorni nel periodo widget',
            'category' => 'context',
            'requires_period' => true,
            'resolver' => 'context',
            'field' => 'days_in_period',
        ],
    ],

    /**
     * Esempi d'uso mostrati in guida, autocomplete e pagina variabili.
     * Formula di riferimento che include la variabile (o uso tipico come KPI singolo).
     */
    'variable_examples' => [
        'total_income' => '[total_income] - [total_expenses]',
        'total_expenses' => '[total_expenses] / [days_in_period]',
        'net_income' => '[net_income]',
        'effective_expenses' => '[effective_expenses] + [expense_wants]',
        'lifestyle_score' => '[lifestyle_score]',
        'household_balance' => '[household_balance]',
        'total_investments' => '[household_balance] + [total_investments]',
        'investments_linked' => '[investments_linked]',
        'investments_unlinked' => '[investments_unlinked]',
        'patrimonio_total' => '[patrimonio_total]',
        'investment_purchases' => '[investment_purchases]',
        'period_income' => '[period_income]',
        'period_expenses' => '[period_expenses] / [days_elapsed_in_month]',
        'period_net' => '[period_income] - [period_expenses]',
        'expense_needs' => '[expense_needs] / [period_expenses] * 100',
        'expense_wants' => '[expense_wants]',
        'expense_investments' => '[expense_investments]',
        'pac_monthly_total' => '[pac_monthly_total]',
        'pac_ytd_contributions' => '[pac_ytd_contributions]',
        'pac_projected_contributions' => '[pac_projected_contributions]',
        'pac_projected_patrimonio' => '[pac_projected_patrimonio]',
        'pac_active_count' => '[pac_active_count]',
        'current_year' => '[current_year]',
        'current_month' => '[period_expenses] / [current_month]',
        'current_day' => '[period_net] / [current_day]',
        'day_of_year' => '[period_net] / [day_of_year]',
        'quarter' => '[period_income] / [quarter]',
        'week_of_year' => '[week_of_year]',
        'days_in_month' => '[period_expenses] / [days_elapsed_in_month] * [days_in_month]',
        'days_in_year' => '[period_income] / [days_elapsed_in_year] * [days_in_year]',
        'days_elapsed_in_month' => '[period_expenses] / [days_elapsed_in_month]',
        'days_remaining_in_month' => '[period_expenses] / [days_elapsed_in_month] * [days_remaining_in_month]',
        'days_elapsed_in_year' => '[period_income] / [days_elapsed_in_year]',
        'days_remaining_in_year' => '[period_income] / [days_elapsed_in_year] * [days_remaining_in_year]',
        'days_in_period' => '[period_net] / [days_in_period]',
    ],

    'chart_types' => [
        'kpi' => [
            'label' => 'Indicatore (KPI)',
            'description' => 'Valore singolo con eventuale confronto sul periodo precedente.',
            'guide' => 'Collega una variabile (formula o statica). Utile per saldi, totali e percentuali. Attiva il confronto periodo per freccia e colore trend.',
        ],
        'line' => [
            'label' => 'Linea',
            'description' => 'Andamento mensile della variabile collegata.',
            'guide' => 'Usa la variabile collegata come serie unica. Scegli un periodo con almeno due mesi di dati (es. storico o anno corrente).',
        ],
        'area' => [
            'label' => 'Area',
            'description' => 'Come la linea, con area sotto la curva.',
            'guide' => 'Stessa logica del grafico a linea: la variabile collegata viene aggregata per mese nel periodo scelto.',
        ],
        'bar' => [
            'label' => 'Barre verticali',
            'description' => 'Confronto tra più voci nello stesso periodo.',
            'guide' => 'Definisci almeno due serie con variabili di sistema (es. liquidità vs investimenti). Il periodo filtra il calcolo.',
        ],
        'horizontal_bar' => [
            'label' => 'Barre orizzontali',
            'description' => 'Confronto tra voci, layout orizzontale.',
            'guide' => 'Come le barre verticali: ideale quando le etichette sono lunghe o vuoi classificare importi per grandezza.',
        ],
        'stacked_bar' => [
            'label' => 'Barre impilate',
            'description' => 'Più serie mensili sovrapposte (stile cashflow).',
            'guide' => 'Ogni serie è una variabile di sistema. Il periodo viene suddiviso in mesi con barre impilate.',
        ],
        'pie' => [
            'label' => 'Torta',
            'description' => 'Ripartizione percentuale tra più voci.',
            'guide' => 'Le fette mostrano la quota di ogni serie sul totale del periodo. Servono almeno due serie con valori positivi.',
        ],
        'treemap' => [
            'label' => 'Mappa ad albero',
            'description' => 'Proporzioni visive tra categorie (treemap).',
            'guide' => 'Ogni rettangolo rappresenta una serie; l\'area è proporzionale al valore nel periodo selezionato.',
        ],
        'progress' => [
            'label' => 'Avanzamento',
            'description' => 'Barra di progresso verso una soglia.',
            'guide' => 'Scegli valore attuale e soglia tra le variabili di sistema (es. entrate vs obiettivo).',
        ],
    ],

    'guest_preview_defaults' => [
        'current_year' => 2026,
        'current_month' => 6,
        'current_day' => 10,
        'days_in_month' => 30,
        'days_in_period' => 30,
        'total_income' => 3200.00,
        'total_expenses' => 2100.00,
        'net_income' => 2800.00,
        'household_balance' => 12500.00,
        'total_investments' => 18000.00,
        'patrimonio_total' => 30500.00,
        'period_income' => 2800.00,
        'period_expenses' => 1950.00,
        'period_net' => 850.00,
    ],

    'bootstrap_template_slugs' => [
        'official.saldo_liquidita',
        'official.entrate_30gg',
        'official.uscite_30gg',
        'official.cashflow_mensile',
        'official.patrimonio_nel_tempo',
    ],

    /**
     * KPI pinati in cima alla Home Essenziale (D3: Saldo full-width, poi Entrate/Uscite).
     * Gli altri bootstrap restano in libreria utente, non auto-merge su Home.
     *
     * @var array<string, string> slug => size (sm|md|lg|xl)
     */
    'home_essential_formula_widgets' => [
        'official.saldo_liquidita' => 'xl',
        'official.entrate_30gg' => 'md',
        'official.uscite_30gg' => 'md',
    ],

    /** @deprecated Usare home_essential_formula_widgets */
    'home_essential_formula_slugs' => [
        'official.saldo_liquidita',
        'official.entrate_30gg',
        'official.uscite_30gg',
    ],

    'legacy_widget_replacements' => [
        'total_balance' => [
            'official.saldo_liquidita',
        ],
        'monthly_stats' => [
            'official.entrate_30gg',
            'official.uscite_30gg',
        ],
        'net_worth' => ['official.patrimonio_nel_tempo'],
        'cash_flow' => ['official.cashflow_mensile'],
    ],

    /** Template ufficiali ritirati dalla galleria (sostituiti da widget built-in). */
    'retired_official_template_slugs' => [
        'official.distribuzione_spese',
        'official.lifestyle_score',
        'official.fatturato_annuo',
    ],
];
