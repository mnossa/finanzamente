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
 *  - SoftwareApplication → homepage + tutte le landing page
 *  - BreadcrumbList    → landing page target (/per-*, /crescita-personale)
 */
class StructuredDataService
{
    public function __construct(private readonly PlanService $planService) {}

    /**
     * Homepage: WebSite + SoftwareApplication con offers dei piani disponibili.
     */
    public function forHomepage(): void
    {
        JsonLdMulti::newJsonLd();
        JsonLdMulti::setType('WebSite');
        JsonLdMulti::setTitle('Finanzamente');
        JsonLdMulti::addValue('url', url('/'));
        JsonLdMulti::addValue('description', 'Webapp di gestione finanziaria personale per chi vive in Italia.');
        JsonLdMulti::addValue('inLanguage', 'it-IT');

        JsonLdMulti::newJsonLd();
        JsonLdMulti::setType('SoftwareApplication');
        JsonLdMulti::setTitle('Finanzamente');
        JsonLdMulti::addValue('url', url('/'));
        JsonLdMulti::addValue('applicationCategory', 'FinanceApplication');
        JsonLdMulti::addValue('operatingSystem', 'Web');
        JsonLdMulti::addValue('inLanguage', 'it-IT');
        JsonLdMulti::addValue('offers', $this->buildOffers());
    }

    /**
     * Landing page target: SoftwareApplication + BreadcrumbList.
     *
     * @param string $pageUrl      URL canonico della pagina (es. url('/per-investitori'))
     * @param string $breadcrumbLabel  Label visibile nel breadcrumb (es. "Per Investitori")
     */
    public function forLandingPage(string $pageUrl, string $breadcrumbLabel): void
    {
        JsonLdMulti::newJsonLd();
        JsonLdMulti::setType('SoftwareApplication');
        JsonLdMulti::setTitle('Finanzamente');
        JsonLdMulti::addValue('url', url('/'));
        JsonLdMulti::addValue('applicationCategory', 'FinanceApplication');
        JsonLdMulti::addValue('operatingSystem', 'Web');
        JsonLdMulti::addValue('inLanguage', 'it-IT');
        JsonLdMulti::addValue('offers', $this->buildOffers());

        JsonLdMulti::newJsonLd();
        JsonLdMulti::setType('BreadcrumbList');
        JsonLdMulti::addValue('itemListElement', [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $breadcrumbLabel, 'item' => $pageUrl],
        ]);
    }

    /**
     * Costruisce l'array di offers per SoftwareApplication dai piani configurati.
     */
    private function buildOffers(): array
    {
        $plans = $this->planService->getPlansForFrontend();
        $offers = [];

        foreach ($plans as $plan) {
            $offer = [
                '@type'         => 'Offer',
                'name'          => $plan['name'],
                'price'         => number_format($plan['price_monthly'], 2, '.', ''),
                'priceCurrency' => $plan['currency'],
            ];

            if ($plan['price_monthly'] > 0) {
                $offer['priceSpecification'] = [
                    '@type'     => 'UnitPriceSpecification',
                    'price'     => number_format($plan['price_monthly'], 2, '.', ''),
                    'priceCurrency' => $plan['currency'],
                    'unitText'  => 'MONTH',
                    'priceType' => 'https://schema.org/RecurringCharge',
                ];
            }

            $offers[] = $offer;
        }

        return $offers;
    }
}
