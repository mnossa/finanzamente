<?php

namespace App\Services;

use Artesaos\SEOTools\Facades\JsonLdMulti;

/**
 * Genera i dati strutturati JSON-LD (schema.org) per le pagine pubbliche.
 *
 * Usa la facade JsonLdMulti di artesaos/seotools, già inclusa nel progetto.
 * I layout pubblici devono includere {!! JsonLdMulti::generate() !!} nel <head>.
 *
 * Schemi implementati:
 *  - WebSite           → homepage
 *  - SoftwareApplication → homepage
 *  - FAQPage           → homepage (se la pagina espone delle FAQ)
 */
class StructuredDataService
{
    /**
     * Homepage: WebSite + SoftwareApplication, più FAQPage quando la pagina mostra FAQ.
     *
     * @param  list<array{question: string, answer: string}>  $faqs
     */
    public function forHomepage(array $faqs = []): void
    {
        JsonLdMulti::newJsonLd();
        JsonLdMulti::setType('WebSite');
        JsonLdMulti::setTitle('Finanzamente');
        JsonLdMulti::addValue('url', url('/'));
        JsonLdMulti::addValue('description', 'App di finanza personale per chi vive in Italia: movimenti, budget, patrimonio, investimenti e detrazioni.');
        JsonLdMulti::addValue('inLanguage', 'it-IT');

        JsonLdMulti::newJsonLd();
        JsonLdMulti::setType('SoftwareApplication');
        JsonLdMulti::setTitle('Finanzamente');
        JsonLdMulti::addValue('url', url('/'));
        JsonLdMulti::addValue('description', 'App di finanza personale per chi vive in Italia. Registra i movimenti, imposta i budget, segui patrimonio, investimenti e spese detraibili in un unico quadro.');
        JsonLdMulti::addValue('applicationCategory', 'FinanceApplication');
        JsonLdMulti::addValue('operatingSystem', 'Web');
        JsonLdMulti::addValue('inLanguage', 'it-IT');
        JsonLdMulti::addValue('offers', [
            [
                '@type' => 'Offer',
                'name' => 'Open Source',
                'price' => '0',
                'priceCurrency' => 'EUR',
            ],
        ]);

        if ($faqs !== []) {
            JsonLdMulti::newJsonLd();
            JsonLdMulti::setType('FAQPage');
            JsonLdMulti::addValue('mainEntity', array_map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs));
        }
    }
}
