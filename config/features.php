<?php

/**
 * Form di creazione guidati (wizard step-by-step).
 *
 * Un solo switch d'ambiente: FEATURE_GUIDED_CREATE_FORMS (true/false).
 * Le chiavi per entità restano esposte a Inertia per compatibilità, tutte legate al master.
 */
$guidedCreateForms = filter_var(env('FEATURE_GUIDED_CREATE_FORMS', true), FILTER_VALIDATE_BOOLEAN);

$guidedEntityFlags = [
    'guided_transaction_create',
    'guided_account_create',
    'guided_category_create',
    'guided_tag_create',
    'guided_budget_create',
    'guided_transfer_create',
    'guided_debt_credit_create',
    'guided_financial_goal_create',
    'guided_recurring_transaction_create',
    'guided_investment_asset_create',
    'guided_investment_create',
    'guided_refund_create',
    'guided_inter_household_transfer_create',
    'guided_household_create',
];

return array_merge(
    [
        'guided_create_forms' => $guidedCreateForms,
    ],
    array_fill_keys($guidedEntityFlags, $guidedCreateForms),
);
