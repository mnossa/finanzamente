<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modalità Pre-Lancio
    |--------------------------------------------------------------------------
    |
    | Quando attiva, solo l'email del proprietario può accedere alla dashboard
    | e registrarsi. Gli altri utenti vedono un messaggio di accesso limitato.
    |
    | PRE_LAUNCH_MODE=true per attivare, false per disattivare.
    | PRE_LAUNCH_OWNER_EMAIL: email del proprietario autorizzato.
    |
    */
    'enabled' => env('PRE_LAUNCH_MODE', false),
    'owner_email' => env('PRE_LAUNCH_OWNER_EMAIL', ''),

    /*
    |--------------------------------------------------------------------------
    | Waitlist Pro
    |--------------------------------------------------------------------------
    |
    | Quando attiva, la sezione prezzi mostra il form di iscrizione alla waitlist
    | al posto del CTA di acquisto Pro.
    |
    | PRO_WAITLIST_ENABLED=true per sostituire il CTA Pro con il form waitlist.
    |
    */
    'waitlist_enabled' => env('PRO_WAITLIST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Tally.so — Micro-sondaggio interesse
    |--------------------------------------------------------------------------
    |
    | ID del form Tally.so da mostrare nella card Pro (modalità waitlist).
    | Viene mostrato un link "Dimmi cosa ti serve" che apre il popup Tally.
    | Lascia vuoto per non mostrare il link al sondaggio.
    |
    | Es: TALLY_SURVEY_FORM_ID=wMeXYZ
    |
    */
    'tally_form_id' => env('TALLY_SURVEY_FORM_ID', ''),
];
