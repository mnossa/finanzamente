<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Datasource disponibili per metric_query nei widget a formula.
    |--------------------------------------------------------------------------
    */
    'default_row_limit' => 10,
    'max_row_limit' => 50,

    'datasources' => [
        'transactions' => [
            'label' => 'Transazioni',
            'requires_period' => true,
            'measures' => ['count', 'sum', 'avg', 'min', 'max', 'net', 'sum_abs'],
            'filter_fields' => [
                'tag', 'category', 'currency', 'account', 'debt_credit',
                'transaction_type', 'tax_deductible', 'amount_min', 'amount_max',
                'has_tag', 'is_private', 'is_split',
            ],
            'list_columns' => ['date', 'description', 'amount', 'category', 'account'],
            'group_by_fields' => ['category', 'account', 'tag', 'currency', 'transaction_type'],
            'sort_fields' => ['date', 'amount', 'description'],
            'default_sort' => ['field' => 'date', 'direction' => 'desc'],
        ],
        'debts_credits' => [
            'label' => 'Debiti e crediti',
            'requires_period' => false,
            'measures' => ['count', 'sum_remaining', 'sum_initial', 'sum_paid'],
            'filter_fields' => ['type', 'status', 'currency', 'counterparty'],
            'list_columns' => ['counterparty', 'type', 'status', 'remaining', 'due_date', 'currency'],
            'group_by_fields' => ['type', 'status', 'currency'],
            'sort_fields' => ['due_date', 'amount', 'counterparty', 'created_at'],
            'default_sort' => ['field' => 'due_date', 'direction' => 'asc'],
        ],
        'investment_pacs' => [
            'label' => 'PAC',
            'requires_period' => false,
            'measures' => ['count', 'sum'],
            'filter_fields' => ['status', 'frequency', 'account', 'asset'],
            'list_columns' => ['asset', 'amount', 'frequency', 'status', 'account', 'start_date'],
            'group_by_fields' => ['status', 'frequency', 'asset', 'account'],
            'sort_fields' => ['start_date', 'amount', 'created_at'],
            'default_sort' => ['field' => 'start_date', 'direction' => 'desc'],
        ],
    ],

    'operators' => ['in', 'not_in', 'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'is_null', 'is_not_null'],

    /*
    | amount_base = default per aggregazioni cross-conto/cross-valuta (EUR normalizzato).
    | amount = solo quando filtro esplicito su currency_code o singolo account.
    */
    'amount_fields' => ['amount_base', 'amount'],

    'default_amount_field' => 'amount_base',

    'transaction_types' => [
        'income' => 'Entrate',
        'expense' => 'Uscite',
        'all' => 'Tutte',
    ],

    'runtime_parameter_types' => [
        'account' => ['label' => 'Conto', 'all_value' => 'all'],
        'period_nav' => ['label' => 'Mese'],
        'tag' => ['label' => 'Tag', 'all_value' => 'all'],
        'category' => ['label' => 'Categoria', 'all_value' => 'all'],
        'currency' => ['label' => 'Valuta', 'all_value' => 'all'],
        'debt_credit' => ['label' => 'Debito/Credito', 'all_value' => 'all'],
        'transaction_type' => ['label' => 'Tipo transazione', 'all_value' => 'all'],
    ],

    'formula_functions' => [
        'IF' => 'IF(condizione, valoreSeVero, valoreSeFalso)',
        'WHEN' => 'WHEN(condizione, valore) — restituisce valore se condizione vera, altrimenti 0',
        'ABS' => 'ABS(valore)',
        'MIN' => 'MIN(a, b)',
        'MAX' => 'MAX(a, b)',
        'ROUND' => 'ROUND(valore, decimali)',
    ],

    'formula_comparators' => ['>', '>=', '<', '<=', '==', '!='],
];
