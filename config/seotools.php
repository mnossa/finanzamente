<?php
/**
 * @see https://github.com/artesaos/seotools
 */

return [
    'inertia' => env('SEO_TOOLS_INERTIA', false),
    'meta' => [
        'defaults' => [
            'title'        => 'FinanzaMente - Gestisci le tue finanze con intelligenza',
            'titleBefore'  => false,
            'description'  => 'FinanzaMente è la webapp di gestione finanziaria personale pensata per chi vive in Italia. Controlla le tue spese, pianifica il futuro e raggiungi i tuoi obiettivi finanziari con semplicità.',
            'separator'    => ' | ',
            'keywords'     => ['gestione finanze', 'budget personale', 'risparmio', 'spese', 'finanza personale', 'vivere in Italia', 'webapp finanze'],
            'canonical'    => 'full',
            'robots'       => 'index, follow',
        ],
        'webmaster_tags' => [
            'google'    => env('GOOGLE_SITE_VERIFICATION'),
            'bing'      => null,
            'alexa'     => null,
            'pinterest' => null,
            'yandex'    => null,
            'norton'    => null,
        ],
        'add_notranslate_class' => false,
    ],
    'opengraph' => [
        'defaults' => [
            'title'       => 'FinanzaMente - Gestisci le tue finanze con intelligenza',
            'description' => 'Prendi il controllo totale delle tue finanze. Gestisci ogni transazione, pianifica il tuo budget e raggiungi i tuoi obiettivi finanziari. Per tutti chi vive in Italia.',
            'url'         => null,
            'type'        => 'website',
            'site_name'   => 'FinanzaMente',
            'images'      => [],
        ],
    ],
    'twitter' => [
        'defaults' => [
            'card' => 'summary_large_image',
        ],
    ],
    'json-ld' => [
        'defaults' => [
            'title'       => 'FinanzaMente',
            'description' => 'Webapp di gestione finanziaria personale per chi vive in Italia.',
            'url'         => null,
            'type'        => 'WebSite',
            'images'      => [],
        ],
    ],
];
